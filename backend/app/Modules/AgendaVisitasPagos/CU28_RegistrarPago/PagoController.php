<?php

namespace App\Modules\AgendaVisitasPagos\CU28_RegistrarPago;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Models\MovimientoReserva;
use App\Models\Pago;
use App\Models\TipoPago;
use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use App\Modules\AgendaVisitasPagos\Compartido\ComprobanteDePago;
use App\Modules\AgendaVisitasPagos\Compartido\DineroDeReservas;
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
 * dólares (monto / tipo de cambio, 2 decimales) es lo que descuenta el saldo del contrato. Hoy el método es efectivo; la tarjeta
 * (Stripe) se habilita en el siguiente prompt. Incluye el CU30: al guardar se genera el comprobante.
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
            'tipoCambioSugerido' => $this->ultimoTipoCambio(),
            'hoy' => AgendaDeVisitas::ahora()->toDateString(),
            'metodos' => Pago::METODOS,
        ]);
    }

    public function guardar(RegistrarPagoRequest $request, ComprobanteDePago $comprobantes): RedirectResponse
    {
        $usuario = $request->user();
        $moneda = $request->input('moneda');
        $monto = $request->input('monto');
        $tipoCambio = $moneda === Pago::BOB ? $request->input('tipocambio') : null;

        // La tarjeta (Stripe) se habilita más adelante: por ahora solo se registran pagos en efectivo.
        if ($request->input('metodo') !== Pago::EFECTIVO) {
            return back()->withInput()->withErrors(['metodo' => 'El pago con tarjeta todavía no está disponible: registra el pago en efectivo.']);
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
                    TipoPago::create(['idpago' => $pago->idpago, 'cambiodemonedalocal' => $tipoCambio, 'fecha' => $ahora->toDateString()]);
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

        // Paso 9: el pago registrado con su comprobante.
        return redirect()->route('pagos.comprobante', $pago)
            ->with('status', 'Pago registrado. Saldo restante del contrato: USD ' . number_format($resultado['saldo'], 2, '.', ',') . '.');
    }

    /** El último tipo de cambio usado en un pago o en una reserva en bolivianos; se sugiere, pero la persona debe confirmarlo. */
    private function ultimoTipoCambio(): ?string
    {
        $valor = TipoPago::orderByDesc('idpago')->value('cambiodemonedalocal')
            ?? MovimientoReserva::whereNotNull('tipocambio')->orderByDesc('idmovimiento')->value('tipocambio');

        return $valor !== null ? rtrim(rtrim(number_format((float) $valor, 4, '.', ''), '0'), '.') : null;
    }
}
