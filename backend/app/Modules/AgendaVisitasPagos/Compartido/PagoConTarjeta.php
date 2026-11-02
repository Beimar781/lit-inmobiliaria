<?php

namespace App\Modules\AgendaVisitasPagos\Compartido;

use App\Models\Contrato;
use App\Models\Pago;
use App\Models\Usuario;
use App\Services\BitacoraService;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;

/**
 * Pago con tarjeta de un contrato con Stripe Checkout (modo de prueba). Siempre se registra en dólares (USD) por el monto del
 * pago, que es lo que Stripe liquida. Con Adaptive Pricing activo en Stripe, el cliente puede elegir pagar en bolivianos en
 * la página de Stripe con el tipo de cambio de Stripe (no con el manual de la empresa): el pago sigue en USD y además se
 * guarda lo que el cliente pagó (moneda_cliente, monto_cliente, tipocambio_stripe). El pago nace PENDIENTE y pasa a PAGADO
 * únicamente cuando Stripe confirma que liquidó ese monto en dólares.
 *
 * confirmar() es la función idempotente que usan la página de éxito (consulta la sesión a Stripe desde el servidor) y el webhook:
 * si el pago ya estaba PAGADO no hace nada, así refrescar la página o recibir el webhook dos veces nunca duplica el comprobante.
 */
class PagoConTarjeta
{
    /** Monto mínimo que Stripe permite cobrar en dólares. */
    public const MINIMO_USD = 0.50;

    public function __construct(private StripeService $stripe, private ComprobanteDePago $comprobantes, private CuentaDelCliente $cuentas)
    {
    }

    public static function centavos(float|string $montoUsd): int
    {
        return (int) round((float) $montoUsd * 100);
    }

    /** Monedas sin decimales en Stripe (su monto no va en centavos). */
    private const SIN_DECIMALES = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];

    /** Mensaje si el monto no llega al mínimo de Stripe en dólares; null si está bien. */
    public static function errorDeMinimo(float $montoUsd): ?string
    {
        return $montoUsd >= self::MINIMO_USD
            ? null
            : 'El pago con tarjeta debe ser de al menos USD ' . number_format(self::MINIMO_USD, 2) . '.';
    }

    /**
     * Lee una sesión de Stripe y dice cuántos dólares liquidó y, si el cliente pagó en otra moneda (Adaptive Pricing),
     * cuánto pagó él y a qué tipo de cambio de Stripe. Devuelve null si la sesión no tiene una forma conocida.
     *
     * Dos formas, según la versión de la API de Stripe:
     *  - actual: la sesión queda en USD (currency / amount_total) y presentment_details trae la moneda y el monto del cliente;
     *  - anterior (sesiones creadas antes del 2025-03-31): la sesión queda en la moneda del cliente y currency_conversion
     *    trae los dólares (amount_total) y el tipo de cambio (fx_rate).
     *
     * @return array{usd_centavos: int, cliente: ?array{moneda: string, monto: float, tipocambio: float}}|null
     */
    public static function liquidacion(array $sesion): ?array
    {
        $moneda = strtolower((string) ($sesion['currency'] ?? ''));
        $total = $sesion['amount_total'] ?? null;
        if (! is_numeric($total) || $moneda === '') {
            return null;
        }

        $conversion = $sesion['currency_conversion'] ?? null;
        if ($moneda !== 'usd' && is_array($conversion) && is_numeric($conversion['amount_total'] ?? null)) {
            if (strtolower((string) ($conversion['source_currency'] ?? '')) !== 'usd' || ! is_numeric($conversion['fx_rate'] ?? null)) {
                return null;
            }

            return [
                'usd_centavos' => (int) $conversion['amount_total'],
                'cliente' => ['moneda' => strtoupper($moneda), 'monto' => self::deMenorUnidad((int) $total, $moneda), 'tipocambio' => round((float) $conversion['fx_rate'], 6)],
            ];
        }

        if ($moneda !== 'usd') {
            return null; // cobrado en otra moneda sin datos de conversión: no se sabe cuántos dólares son
        }

        $usd = (int) $total;
        $presentado = $sesion['presentment_details'] ?? null;
        $monedaCliente = is_array($presentado) ? strtolower((string) ($presentado['presentment_currency'] ?? '')) : '';
        if ($monedaCliente === '' || $monedaCliente === 'usd' || ! is_numeric($presentado['presentment_amount'] ?? null) || $usd <= 0) {
            return ['usd_centavos' => $usd, 'cliente' => null];
        }

        $montoCliente = self::deMenorUnidad((int) $presentado['presentment_amount'], $monedaCliente);

        return [
            'usd_centavos' => $usd,
            // El tipo de cambio de Stripe sale de lo que se cobró: unidades de la moneda del cliente por dólar.
            'cliente' => ['moneda' => strtoupper($monedaCliente), 'monto' => $montoCliente, 'tipocambio' => round($montoCliente / ($usd / 100), 6)],
        ];
    }

    private static function deMenorUnidad(int $monto, string $moneda): float
    {
        return in_array($moneda, self::SIN_DECIMALES, true) ? (float) $monto : $monto / 100;
    }

    /**
     * Guarda el pago como PENDIENTE (método TARJETA, en dólares) y crea la sesión de Stripe Checkout por ese monto.
     *
     * @return array{pago?: Pago, url?: string, error?: string, campo?: string}
     */
    public function iniciar(Contrato $contrato, float $montoUsd, ?Usuario $actor, string $fecha, string $rutaExito = 'pagos.tarjeta.exito', string $rutaCancelado = 'pagos.tarjeta.cancelado'): array
    {
        $resultado = DB::transaction(function () use ($contrato, $montoUsd, $actor, $fecha) {
            $actual = Contrato::with(['propiedad', 'cliente'])->lockForUpdate()->findOrFail($contrato->idcontrato);

            if ($actual->estado !== Contrato::VIGENTE) {
                return ['error' => 'Solo se pueden registrar pagos en un contrato vigente: este contrato está ' . strtolower($actual->estado_etiqueta) . '.', 'campo' => 'idcontrato'];
            }
            $saldo = $actual->saldoUsd();
            if ($montoUsd > $saldo) {
                return ['error' => 'El pago equivale a USD ' . number_format($montoUsd, 2, '.', ',') . ' y supera el saldo del contrato (USD ' . number_format($saldo, 2, '.', ',') . ').', 'campo' => 'monto'];
            }

            $pago = Pago::create([
                'idcontrato' => $actual->idcontrato,
                'monto' => $montoUsd,
                'moneda' => Pago::USD,
                'fechapago' => $fecha,
                'estado' => Pago::PENDIENTE,
                'montousd' => $montoUsd,
                'metodo' => Pago::TARJETA,
                'idusuario' => $actor?->idusuario,
            ]);
            return ['pago' => $pago, 'contrato' => $actual];
        });

        if (isset($resultado['error'])) {
            return $resultado;
        }

        /** @var Pago $pago */
        $pago = $resultado['pago'];
        $actual = $resultado['contrato'];
        $descripcion = "Pago contrato {$actual->idcontrato} - {$actual->propiedad->titulo}";

        // La llamada a Stripe va fuera de la transacción: es una conexión a internet y puede fallar.
        try {
            $sesion = $this->stripe->crearSesionDePago(
                self::centavos($montoUsd),
                $descripcion,
                (string) $pago->idpago,
                ['idpago' => (string) $pago->idpago, 'idcontrato' => (string) $actual->idcontrato],
                route($rutaExito, $pago) . '?session_id={CHECKOUT_SESSION_ID}',
                route($rutaCancelado, $pago),
            );
        } catch (\Throwable $error) {
            report($error);
            $pago->update(['estado' => Pago::ANULADO]);
            BitacoraService::registrar('PAGO_TARJETA_FALLIDO', 'Pagos', "No se pudo iniciar el pago con tarjeta #{$pago->idpago} del contrato #{$actual->idcontrato}: Stripe no respondió", [
                'idpago' => $pago->idpago, 'idcontrato' => $actual->idcontrato,
            ]);

            return ['error' => 'No se pudo conectar con Stripe para iniciar el pago con tarjeta. Intenta de nuevo en unos minutos.', 'campo' => 'metodo'];
        }

        $pago->update(['stripe_session_id' => $sesion['id']]);

        BitacoraService::registrar('PAGO_TARJETA_INICIADO', 'Pagos', "Inició el pago con tarjeta #{$pago->idpago} del contrato #{$actual->idcontrato} por USD " . number_format($montoUsd, 2, '.', ','), [
            'idpago' => $pago->idpago, 'idcontrato' => $actual->idcontrato, 'montousd' => number_format($montoUsd, 2, '.', ''), 'stripe_session_id' => $sesion['id'],
        ]);

        return ['pago' => $pago, 'url' => $sesion['url']];
    }

    /**
     * Confirma el pago con los datos de la sesión de Stripe (los trae la consulta a Stripe o el webhook). Idempotente.
     *
     * @param array $sesion datos de la sesión: id, payment_status, payment_intent, amount_total, currency, currency_conversion, presentment_details
     * @return array{pago: Pago, confirmado: bool, nuevo: bool, motivo?: string, cuenta?: array} cuenta: lo que pasó con la cuenta de acceso del cliente
     */
    public function confirmar(Pago $pago, array $sesion): array
    {
        $nuevo = false;
        $motivo = null;

        $liquidacion = self::liquidacion($sesion);

        $actual = DB::transaction(function () use ($pago, $sesion, $liquidacion, &$nuevo, &$motivo) {
            $actual = Pago::lockForUpdate()->findOrFail($pago->idpago);

            // Ya estaba pagado (se refrescó la página de éxito o llegó el webhook dos veces): no se hace nada.
            if ($actual->estado === Pago::PAGADO) {
                return $actual;
            }

            if (($sesion['payment_status'] ?? null) !== 'paid') {
                $motivo = 'Stripe todavía no confirma el pago.';

                return $actual;
            }

            // La sesión debe ser la de este pago y Stripe debe haber liquidado en dólares el monto del pago, ya sea que el cliente
            // pagara en USD o en bolivianos (Adaptive Pricing): no se confía en datos sueltos.
            $coincide = ($actual->stripe_session_id === null || $actual->stripe_session_id === ($sesion['id'] ?? null))
                && $liquidacion !== null
                && $liquidacion['usd_centavos'] === self::centavos($actual->montousd);
            if (! $coincide) {
                $motivo = 'Los datos de Stripe no coinciden con el pago.';

                return $actual;
            }

            $actual->update([
                'estado' => Pago::PAGADO,
                'stripe_session_id' => $actual->stripe_session_id ?? $sesion['id'],
                'stripe_payment_intent' => $sesion['payment_intent'] ?? null,
                'fechapago' => AgendaDeVisitas::ahora()->toDateTimeString(),
                // Si el cliente pagó en bolivianos en la página de Stripe: lo que pagó y el tipo de cambio que aplicó Stripe.
                'moneda_cliente' => $liquidacion['cliente']['moneda'] ?? null,
                'monto_cliente' => $liquidacion['cliente']['monto'] ?? null,
                'tipocambio_stripe' => $liquidacion['cliente']['tipocambio'] ?? null,
            ]);
            $actual = $this->comprobantes->asegurar($actual);
            $nuevo = true;

            return $actual;
        });

        if ($nuevo) {
            BitacoraService::registrar('PAGO_TARJETA_CONFIRMADO', 'Pagos', "Se confirmó el pago con tarjeta #{$actual->idpago} del contrato #{$actual->idcontrato} (comprobante {$actual->comprobante})", [
                'idpago' => $actual->idpago, 'idcontrato' => $actual->idcontrato, 'montousd' => (string) $actual->montousd,
                'comprobante' => $actual->comprobante, 'stripe_payment_intent' => $actual->stripe_payment_intent,
                'moneda_cliente' => $actual->moneda_cliente, 'monto_cliente' => $actual->monto_cliente !== null ? (string) $actual->monto_cliente : null,
                'tipocambio_stripe' => $actual->tipocambio_stripe !== null ? (string) $actual->tipocambio_stripe : null,
            ]);
        } elseif ($motivo !== null && str_contains($motivo, 'no coinciden')) {
            BitacoraService::registrar('PAGO_TARJETA_DISCREPANCIA', 'Pagos', "Stripe informó un cobro que no coincide con el pago #{$actual->idpago}", [
                'idpago' => $actual->idpago, 'sesion' => $sesion['id'] ?? null,
            ]);
        }

        // Tras el primer pago confirmado, el cliente recibe su cuenta de acceso (si tiene correo y todavía no la tiene).
        $cuenta = $nuevo ? $this->cuentas->asegurar($actual->contrato->cliente) : null;

        return ['pago' => $actual, 'confirmado' => $actual->estado === Pago::PAGADO, 'nuevo' => $nuevo]
            + ($motivo ? ['motivo' => $motivo] : [])
            + ($cuenta ? ['cuenta' => $cuenta] : []);
    }

    /** El cliente canceló en la página de Stripe: el pago pendiente queda ANULADO, sin comprobante. Si ya estaba pagado, no se toca. */
    public function cancelar(Pago $pago): Pago
    {
        $anulado = false;

        $actual = DB::transaction(function () use ($pago, &$anulado) {
            $actual = Pago::lockForUpdate()->findOrFail($pago->idpago);

            if ($actual->estado === Pago::PENDIENTE) {
                $actual->update(['estado' => Pago::ANULADO]);
                $anulado = true;
            }

            return $actual;
        });

        if ($anulado) {
            BitacoraService::registrar('PAGO_TARJETA_CANCELADO', 'Pagos', "Se canceló el pago con tarjeta #{$actual->idpago} del contrato #{$actual->idcontrato}", [
                'idpago' => $actual->idpago, 'idcontrato' => $actual->idcontrato,
            ]);
        }

        return $actual;
    }
}
