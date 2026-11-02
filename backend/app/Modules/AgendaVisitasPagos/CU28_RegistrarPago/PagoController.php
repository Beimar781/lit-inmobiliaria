<?php

namespace App\Modules\AgendaVisitasPagos\CU28_RegistrarPago;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Models\Pago;
use App\Models\TipoPago;
use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use App\Modules\AgendaVisitasPagos\Compartido\ComprobanteDePago;
use App\Modules\AgendaVisitasPagos\Compartido\CuentaDelCliente;
use App\Modules\AgendaVisitasPagos\Compartido\DineroDeReservas;
use App\Modules\AgendaVisitasPagos\Compartido\PagoConTarjeta;
use App\Services\BitacoraService;
use App\Support\ParametrosConsulta;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CU28: Registrar pago (Asistente Administrativo y Administrador)
 * Registra los pagos de un contrato VIGENTE. Los contratos están en dólares (USD); el pago puede hacerse en dólares o en
 * bolivianos (BOB). En bolivianos el tipo de cambio oficial del día es obligatorio y lo escribe la persona; el equivalente en
 * dólares (monto / tipo de cambio, 2 decimales) es lo que descuenta el saldo del contrato. El método puede ser efectivo o tarjeta
 * (Stripe Checkout, siempre en dólares: el pago queda PENDIENTE hasta que Stripe confirma el cobro; en la página de Stripe el
 * cliente puede elegir pagar en bolivianos con el tipo de cambio de Stripe). Incluye el CU30: al confirmarse el pago se genera
 * el comprobante.
 * Flujo principal:
 *  1. El actor accede a "Registrar pago"                                      -> crear()
 *  2. El actor busca y selecciona el contrato vigente                         -> crear.blade.php (paso 1)
 *  3. El sistema muestra los datos del contrato, su saldo y los pagos ya registrados -> crear.blade.php (paso 2)
 *  4. El actor ingresa el monto, la moneda, el tipo de cambio (si corresponde), la fecha del pago y el método
 *  5. El actor confirma el registro                                            -> guardar()
 *  6. El sistema valida los datos y calcula el equivalente en dólares          -> RegistrarPagoRequest / guardar()
 *  7. El sistema guarda el pago (y el tipo de cambio usado)
 *  8. El sistema genera el comprobante del pago (CU30)                         -> ComprobanteDePago
 *  9. El sistema muestra el pago registrado con su comprobante                 -> ComprobanteController
 * Flujo alternativo: si el pago es en dólares no se pide el tipo de cambio. El contrato admite varios pagos sucesivos.
 * Excepciones: el contrato no existe o no está vigente, monto no válido (cero o negativo) o mayor al saldo, fecha de pago futura,
 * falta el tipo de cambio en un pago en bolivianos, método no disponible, error de base de datos.
 */
class PagoController extends Controller
{
    public function crear(Request $request): View
    {
        $id = ParametrosConsulta::entero($request, 'contrato');
        $contrato = $id ? Contrato::with(['propiedad', 'cliente', 'pagos'])->find($id) : null;

        // Paso 2: los contratos vigentes que todavía tienen saldo por pagar.
        $contratos = Contrato::vigentes()->with(['propiedad', 'cliente'])->orderByDesc('idcontrato')->get()
            ->filter(fn (Contrato $c) => $c->saldoUsd() > 0)
            ->mapWithKeys(fn (Contrato $c) => [$c->idcontrato => "N.º {$c->idcontrato} · {$c->cliente->nombre} · {$c->propiedad->codigo} {$c->propiedad->titulo} · saldo USD " . number_format($c->saldoUsd(), 2, '.', ',')])
            ->all();

        return view('agendavisitaspagos::CU28_RegistrarPago.crear', [
            'contratos' => $contratos,
            'contrato' => $contrato,
            'pagado' => $contrato?->totalPagadoUsd(),
            'saldo' => $contrato?->saldoUsd(),
            'tipoCambioSugerido' => DineroDeReservas::ultimoTipoCambioDePago(),
            'hoy' => AgendaDeVisitas::ahora()->toDateString(),
            'metodos' => Pago::METODOS,
        ]);
    }

    public function guardar(RegistrarPagoRequest $request, ComprobanteDePago $comprobantes, PagoConTarjeta $tarjeta, CuentaDelCliente $cuentas): RedirectResponse
    {
        $usuario = $request->user();
        $moneda = $request->input('moneda');
        $monto = $request->input('monto');
        $tipoCambio = $moneda === Pago::BOB ? $request->input('tipocambio') : null;

        // Pago con tarjeta (Stripe Checkout): siempre en dólares, de al menos USD 0.50 y sin pasar el saldo.
        if ($request->input('metodo') === Pago::TARJETA) {
            return $this->pagarConTarjeta($request, $tarjeta);
        }

        $montoUsd = DineroDeReservas::aUsd($monto, $moneda, $tipoCambio);
        if ($montoUsd <= 0) {
            return back()->withInput()->withErrors(['monto' => 'El monto es demasiado pequeño: su equivalente en dólares es cero.']);
        }

        // La fecha del pago se guarda con la hora actual de Bolivia.
        $ahora = AgendaDeVisitas::ahora();
        $fechaPago = $request->input('fechapago') . ' ' . $ahora->format('H:i:s');

        try {
            $resultado = DB::transaction(function () use ($request, $usuario, $moneda, $monto, $tipoCambio, $montoUsd, $fechaPago, $ahora, $comprobantes) {
                // El contrato se bloquea mientras se calcula el saldo y se guarda el pago: dos pagos casi simultáneos no lo sobrepasan.
                $contrato = Contrato::with(['propiedad', 'cliente'])->lockForUpdate()->findOrFail($request->input('idcontrato'));

                if ($contrato->estado !== Contrato::VIGENTE) {
                    return ['error' => 'Solo se pueden registrar pagos en un contrato vigente: este contrato está ' . strtolower($contrato->estado_etiqueta) . '.', 'campo' => 'idcontrato'];
                }

                // Paso 6: el equivalente en dólares no puede superar el saldo.
                $saldo = $contrato->saldoUsd();
                if ($montoUsd > $saldo) {
                    return ['error' => 'El pago equivale a USD ' . number_format($montoUsd, 2, '.', ',') . ' y supera el saldo del contrato (USD ' . number_format($saldo, 2, '.', ',') . ').', 'campo' => 'monto'];
                }

                // Paso 7: el pago queda registrado, con el tipo de cambio de ese día.
                $pago = Pago::create([
                    'idcontrato' => $contrato->idcontrato,
                    'monto' => $monto,
                    'moneda' => $moneda,
                    'tipocambio' => $tipoCambio,
                    'fechapago' => $fechaPago,
                    'estado' => Pago::PAGADO,
                    'montousd' => $montoUsd,
                    'metodo' => Pago::EFECTIVO,
                    'idusuario' => $usuario->idusuario,
                ]);
                if ($moneda === Pago::BOB) {
                    // El tipo de cambio es el del día en que se hizo el pago (no el día en que se registra).
                    TipoPago::create(['idpago' => $pago->idpago, 'cambiodemonedalocal' => $tipoCambio, 'fecha' => $request->input('fechapago')]);
                }

                // Paso 8: el comprobante del pago (CU30), con su número único.
                $pago = $comprobantes->asegurar($pago);

                return ['pago' => $pago, 'contrato' => $contrato, 'saldo' => $saldo - $montoUsd];
            });
        } catch (QueryException $error) {
            // Excepción: error de conexión o de la base de datos.
            report($error);

            return back()->withInput()->with('error', 'No se pudo registrar el pago por un problema con la base de datos. Intenta de nuevo en unos minutos.');
        }

        if (isset($resultado['error'])) {
            return back()->withInput()->withErrors([$resultado['campo'] => $resultado['error']]);
        }

        ['pago' => $pago, 'contrato' => $contrato] = $resultado;

        BitacoraService::registrar('PAGO_REGISTRADO', 'Pagos', "Registró el pago #{$pago->idpago} del contrato #{$contrato->idcontrato} de {$contrato->cliente->nombre}: {$pago->moneda} " . number_format($pago->monto, 2, '.', ',') . " (comprobante {$pago->comprobante})", [
            'idpago' => $pago->idpago,
            'idcontrato' => $contrato->idcontrato,
            'idcliente' => $contrato->idcliente,
            'metodo' => $pago->metodo,
            'monto' => (string) $pago->monto,
            'moneda' => $pago->moneda,
            'tipocambio' => $pago->tipocambio !== null ? (string) $pago->tipocambio : null,
            'montousd' => (string) $pago->montousd,
            'comprobante' => $pago->comprobante,
        ]);

        // Después del primer pago el cliente pasa a ser cliente formal: se le crea su cuenta de acceso (si tiene correo).
        $cuenta = CuentaDelCliente::mensajes($cuentas->asegurar($contrato->cliente), $contrato->cliente);

        // Paso 9: el pago registrado con su comprobante.
        return redirect()->route('pagos.comprobante', $pago)
            ->with('status', 'Pago registrado. Saldo restante del contrato: USD ' . number_format($resultado['saldo'], 2, '.', ',') . '.')
            ->with($cuenta);
    }

    /**
     * Pago con tarjeta: guarda el pago PENDIENTE y manda al cliente a la página de Stripe para escribir la tarjeta.
     * Siempre es en dólares y no lleva el tipo de cambio manual: si el cliente elige pagar en bolivianos en la página de
     * Stripe, Stripe aplica su propio tipo de cambio y el pago se sigue registrando por el monto en dólares.
     */
    private function pagarConTarjeta(RegistrarPagoRequest $request, PagoConTarjeta $tarjeta): RedirectResponse
    {
        if ($request->input('moneda') !== Pago::USD) {
            return back()->withInput()->withErrors(['moneda' => 'El pago con tarjeta se registra en dólares: en la página de Stripe el cliente puede elegir pagar en bolivianos.']);
        }

        $montoUsd = round((float) $request->input('monto'), 2);

        // El mínimo de Stripe es USD 0.50.
        if ($error = PagoConTarjeta::errorDeMinimo($montoUsd)) {
            return back()->withInput()->withErrors(['monto' => $error]);
        }

        $contrato = Contrato::findOrFail($request->input('idcontrato'));
        $resultado = $tarjeta->iniciar($contrato, $montoUsd, $request->user(), AgendaDeVisitas::ahora()->toDateTimeString());

        if (isset($resultado['error'])) {
            return back()->withInput()->withErrors([$resultado['campo'] => $resultado['error']]);
        }

        // A la página segura de Stripe (en prueba, con las tarjetas de prueba). Al terminar vuelve a la página de éxito o de cancelación.
        return redirect()->away($resultado['url']);
    }
}
