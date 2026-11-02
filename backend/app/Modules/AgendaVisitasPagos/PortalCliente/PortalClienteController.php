<?php

namespace App\Modules\AgendaVisitasPagos\PortalCliente;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use App\Modules\AgendaVisitasPagos\Compartido\ComprobanteDePago;
use App\Modules\AgendaVisitasPagos\Compartido\PagoConTarjeta;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Portal del cliente «Mis contratos y pagos» (solo rol Cliente). No es un caso de uso del documento v6: extiende el CU28, el CU29 y el CU30.
 * El cliente entra con la cuenta que se le creó después de su primer pago y ve SOLO lo suyo:
 *  - sus contratos (propiedad, tipo, monto, pagado, saldo y estado) y el detalle con sus pagos y comprobantes;
 *  - el botón «Pagar con tarjeta» (Stripe, en dólares; en la página de Stripe puede elegir pagar en bolivianos) en cada contrato vigente con saldo.
 * Lo que no es suyo (contratos, pagos o comprobantes de otro cliente) responde 404. El cliente no ve ningún módulo del personal.
 */
class PortalClienteController extends Controller
{
    public function contratos(Request $request): View
    {
        $cliente = $this->cliente($request);

        $contratos = $cliente
            ? Contrato::with(['propiedad', 'pagos'])->where('idcliente', $cliente->idcliente)->orderByDesc('idcontrato')->get()
            : collect();

        return view('agendavisitaspagos::PortalCliente.contratos', ['contratos' => $contratos, 'cliente' => $cliente]);
    }

    public function contrato(Request $request, Contrato $contrato): View
    {
        $this->propio($request, $contrato);

        return view('agendavisitaspagos::PortalCliente.contrato', [
            'contrato' => $contrato->load(['propiedad', 'pagos']),
            'pagado' => $contrato->totalPagadoUsd(),
            'saldo' => $contrato->saldoUsd(),
            'minimo' => PagoConTarjeta::MINIMO_USD,
        ]);
    }

    /** Comprobante de un pago propio (el mismo del CU30, con el mismo número). */
    public function comprobante(Request $request, ComprobanteDePago $comprobantes, string $pago): Response
    {
        $registro = ctype_digit($pago) ? Pago::with(['contrato.propiedad', 'contrato.cliente', 'tipopago', 'usuario'])->find($pago) : null;
        abort_if($registro === null, 404);
        $this->propio($request, $registro->contrato);

        if (! ComprobanteDePago::disponible($registro)) {
            return response()->view('agendavisitaspagos::CU30_GenerarComprobante.no-disponible', [
                'motivo' => 'El pago está ' . strtolower($registro->estado_etiqueta) . '.',
                'volver' => route('portal.contrato', $registro->contrato),
                'textoVolver' => 'Volver a mi contrato',
            ], 404);
        }

        $registro = $comprobantes->asegurar($registro)->load(['contrato.propiedad', 'contrato.cliente', 'tipopago', 'usuario']);

        return response()->view('agendavisitaspagos::CU30_GenerarComprobante.comprobante', [
            'pago' => $registro,
            'contrato' => $registro->contrato,
            'saldoRestante' => ComprobanteDePago::saldoDespuesDelPagoUsd($registro),
            'volver' => route('portal.contrato', $registro->contrato),
            'textoVolver' => 'Volver a mi contrato',
        ]);
    }

    /**
     * «Pagar con tarjeta»: el pago queda pendiente y el cliente va a la página de Stripe. Siempre es un monto en dólares; en
     * la página de Stripe el cliente puede elegir pagar en bolivianos (con el tipo de cambio de Stripe).
     */
    public function pagar(Request $request, Contrato $contrato, PagoConTarjeta $tarjeta): RedirectResponse
    {
        $this->propio($request, $contrato);

        $datos = $request->validate([
            'monto' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
        ], [
            'monto.required' => 'Indica cuánto quieres pagar.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.gt' => 'El monto debe ser mayor a cero.',
            'monto.max' => 'El monto es demasiado grande.',
            'monto.decimal' => 'El monto puede tener como máximo 2 decimales.',
        ]);

        $monto = round((float) $datos['monto'], 2);
        if ($error = PagoConTarjeta::errorDeMinimo($monto)) {
            return back()->withInput()->withErrors(['monto' => $error]);
        }

        $resultado = $tarjeta->iniciar(
            $contrato, $monto, $request->user(), AgendaDeVisitas::ahora()->toDateTimeString(),
            rutaExito: 'portal.tarjeta.exito', rutaCancelado: 'portal.tarjeta.cancelado'
        );

        if (isset($resultado['error'])) {
            return back()->withInput()->withErrors(['monto' => $resultado['error']]);
        }

        return redirect()->away($resultado['url']);
    }

    /** Vuelta de Stripe con el pago hecho: se confirma consultando a Stripe desde el servidor (nunca por la URL). */
    public function exito(Request $request, Pago $pago, StripeService $stripe, PagoConTarjeta $tarjeta): RedirectResponse
    {
        $this->propiaTarjeta($request, $pago);

        if ($pago->estado === Pago::PAGADO) {
            return redirect()->route('portal.comprobante', $pago);
        }
        if (! $pago->stripe_session_id) {
            return $this->volver($pago, 'Este pago con tarjeta no tiene una sesión de Stripe. Inicia el pago de nuevo.');
        }

        try {
            $sesion = $stripe->obtenerSesion($pago->stripe_session_id);
        } catch (\Throwable $error) {
            report($error);

            return $this->volver($pago, 'No se pudo consultar a Stripe si el pago se cobró. Espera unos minutos: el pago se confirmará solo cuando Stripe lo avise.');
        }

        $resultado = $tarjeta->confirmar($pago, $sesion);

        if ($resultado['confirmado']) {
            return redirect()->route('portal.comprobante', $resultado['pago'])
                ->with('status', 'Tu pago con tarjeta fue confirmado. Aquí está tu comprobante.');
        }

        return $this->volver($pago, ($resultado['motivo'] ?? 'Stripe todavía no confirma el pago.') . ' Si ya se descontó de tu tarjeta, se confirmará solo en unos minutos.');
    }

    public function cancelado(Request $request, Pago $pago, PagoConTarjeta $tarjeta): RedirectResponse
    {
        $this->propiaTarjeta($request, $pago);

        $pago = $tarjeta->cancelar($pago);

        if ($pago->estado === Pago::PAGADO) {
            return redirect()->route('portal.comprobante', $pago)->with('status', 'Ese pago ya estaba confirmado.');
        }

        return $this->volver($pago, 'El pago con tarjeta se canceló y no se cobró nada. Puedes intentarlo de nuevo.');
    }

    private function volver(Pago $pago, string $mensaje): RedirectResponse
    {
        return redirect()->route('portal.contrato', $pago->idcontrato)->with('error', $mensaje);
    }

    /** El cliente al que pertenece la cuenta que inició sesión (null si todavía no hay uno enlazado). */
    private function cliente(Request $request): ?Cliente
    {
        return Cliente::where('idusuariocuenta', $request->user()->idusuario)->first();
    }

    /** Un contrato que no es del cliente que inició sesión responde 404 (no se revela que existe). */
    private function propio(Request $request, Contrato $contrato): void
    {
        abort_unless($this->cliente($request)?->idcliente === $contrato->idcliente, 404);
    }

    private function propiaTarjeta(Request $request, Pago $pago): void
    {
        abort_unless($pago->metodo === Pago::TARJETA, 404);
        $this->propio($request, $pago->contrato);
    }
}
