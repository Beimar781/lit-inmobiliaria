<?php

namespace App\Modules\AgendaVisitasPagos\Compartido;

use App\Models\Contrato;
use App\Models\Pago;
use App\Models\TipoPago;
use App\Models\Usuario;
use App\Services\BitacoraService;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;

/**
 * Pago con tarjeta de un contrato con Stripe Checkout (modo de prueba). Por defecto solo en dólares (USD); en bolivianos (BOB)
 * solo si la cuenta de Stripe lo acepta y se activó STRIPE_ACEPTA_BOB. El pago nace PENDIENTE y pasa a PAGADO únicamente cuando Stripe confirma que se cobró.
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

    /** ¿Se puede cobrar con tarjeta en bolivianos? Solo si se activó STRIPE_ACEPTA_BOB. */
    public static function aceptaBolivianos(): bool
    {
        return (bool) config('services.stripe.acepta_bob');
    }

    /**
     * Guarda el pago como PENDIENTE (método TARJETA) y crea la sesión de Stripe Checkout.
     * $monto y $moneda es lo que se cobra en la tarjeta; $montoUsd es su equivalente en dólares (el que descuenta el saldo).
     *
     * @return array{pago?: Pago, url?: string, error?: string, campo?: string}
     */
    public function iniciar(Contrato $contrato, float $montoUsd, ?Usuario $actor, string $fecha, ?float $monto = null, string $moneda = Pago::USD, ?string $tipoCambio = null, string $rutaExito = 'pagos.tarjeta.exito', string $rutaCancelado = 'pagos.tarjeta.cancelado'): array
    {
        $monto ??= $montoUsd;

        $resultado = DB::transaction(function () use ($contrato, $montoUsd, $actor, $fecha, $monto, $moneda, $tipoCambio) {
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
                'monto' => $monto,
                'moneda' => $moneda,
                'tipocambio' => $moneda === Pago::BOB ? $tipoCambio : null,
                'fechapago' => $fecha,
                'estado' => Pago::PENDIENTE,
                'montousd' => $montoUsd,
                'metodo' => Pago::TARJETA,
                'idusuario' => $actor?->idusuario,
            ]);
            // En bolivianos se guarda el tipo de cambio del día del pago, como en el pago en efectivo.
            if ($moneda === Pago::BOB) {
                TipoPago::create(['idpago' => $pago->idpago, 'cambiodemonedalocal' => $tipoCambio, 'fecha' => substr($fecha, 0, 10)]);
            }

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
                self::centavos($monto),
                $descripcion,
                (string) $pago->idpago,
                ['idpago' => (string) $pago->idpago, 'idcontrato' => (string) $actual->idcontrato],
                route($rutaExito, $pago) . '?session_id={CHECKOUT_SESSION_ID}',
                route($rutaCancelado, $pago),
                strtolower($moneda),
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

        BitacoraService::registrar('PAGO_TARJETA_INICIADO', 'Pagos', "Inició el pago con tarjeta #{$pago->idpago} del contrato #{$actual->idcontrato} por {$moneda} " . number_format($monto, 2, '.', ','), [
            'idpago' => $pago->idpago, 'idcontrato' => $actual->idcontrato, 'monto' => number_format($monto, 2, '.', ''), 'moneda' => $moneda, 'montousd' => number_format($montoUsd, 2, '.', ''), 'stripe_session_id' => $sesion['id'],
        ]);

        return ['pago' => $pago, 'url' => $sesion['url']];
    }

    /**
     * Confirma el pago con los datos de la sesión de Stripe (los trae la consulta a Stripe o el webhook). Idempotente.
     *
     * @param array $sesion datos de la sesión: id, payment_status, payment_intent, amount_total, currency
     * @return array{pago: Pago, confirmado: bool, nuevo: bool, motivo?: string, cuenta?: array} cuenta: lo que pasó con la cuenta de acceso del cliente
     */
    public function confirmar(Pago $pago, array $sesion): array
    {
        $nuevo = false;
        $motivo = null;

        $actual = DB::transaction(function () use ($pago, $sesion, &$nuevo, &$motivo) {
            $actual = Pago::lockForUpdate()->findOrFail($pago->idpago);

            // Ya estaba pagado (se refrescó la página de éxito o llegó el webhook dos veces): no se hace nada.
            if ($actual->estado === Pago::PAGADO) {
                return $actual;
            }

            if (($sesion['payment_status'] ?? null) !== 'paid') {
                $motivo = 'Stripe todavía no confirma el pago.';

                return $actual;
            }

            // La sesión debe ser la de este pago, en la misma moneda y por el mismo monto: no se confía en datos sueltos.
            $coincide = ($actual->stripe_session_id === null || $actual->stripe_session_id === ($sesion['id'] ?? null))
                && ($sesion['currency'] ?? null) === strtolower($actual->moneda)
                && (int) ($sesion['amount_total'] ?? -1) === self::centavos($actual->monto);
            if (! $coincide) {
                $motivo = 'Los datos de Stripe no coinciden con el pago.';

                return $actual;
            }

            $actual->update([
                'estado' => Pago::PAGADO,
                'stripe_session_id' => $actual->stripe_session_id ?? $sesion['id'],
                'stripe_payment_intent' => $sesion['payment_intent'] ?? null,
                'fechapago' => AgendaDeVisitas::ahora()->toDateTimeString(),
            ]);
            $actual = $this->comprobantes->asegurar($actual);
            $nuevo = true;

            return $actual;
        });

        if ($nuevo) {
            BitacoraService::registrar('PAGO_TARJETA_CONFIRMADO', 'Pagos', "Se confirmó el pago con tarjeta #{$actual->idpago} del contrato #{$actual->idcontrato} (comprobante {$actual->comprobante})", [
                'idpago' => $actual->idpago, 'idcontrato' => $actual->idcontrato, 'montousd' => (string) $actual->montousd,
                'comprobante' => $actual->comprobante, 'stripe_payment_intent' => $actual->stripe_payment_intent,
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
