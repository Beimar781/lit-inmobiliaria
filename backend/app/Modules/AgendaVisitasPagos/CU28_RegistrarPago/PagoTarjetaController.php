<?php

namespace App\Modules\AgendaVisitasPagos\CU28_RegistrarPago;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Modules\AgendaVisitasPagos\Compartido\CuentaDelCliente;
use App\Modules\AgendaVisitasPagos\Compartido\PagoConTarjeta;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;

/**
 * CU28 con tarjeta: a dónde vuelve el cliente desde la página de Stripe.
 *  - éxito:     se le pregunta a Stripe (desde el servidor) si el pago se cobró; recién ahí pasa a PAGADO y se genera el comprobante (CU30).
 *               Nunca se confía en lo que traiga la URL: se usa la sesión guardada en el pago.
 *  - cancelado: el pago pendiente queda ANULADO, sin comprobante, con la opción de intentarlo de nuevo.
 * Ambas son seguras de repetir: no duplican el comprobante.
 */
class PagoTarjetaController extends Controller
{
    public function exito(Pago $pago, StripeService $stripe, PagoConTarjeta $tarjeta): RedirectResponse
    {
        abort_unless($pago->metodo === Pago::TARJETA, 404);

        // Si ya estaba pagado (refrescar la página, o el webhook llegó primero), simplemente se muestra el comprobante.
        if ($pago->estado === Pago::PAGADO) {
            return redirect()->route('pagos.comprobante', $pago);
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
            return redirect()->route('pagos.comprobante', $resultado['pago'])
                ->with('status', 'Pago con tarjeta confirmado. Su comprobante está listo para imprimir.')
                ->with(isset($resultado['cuenta']) ? CuentaDelCliente::mensajes($resultado['cuenta'], $resultado['pago']->contrato->cliente) : []);
        }

        return $this->volver($pago, ($resultado['motivo'] ?? 'Stripe todavía no confirma el pago.') . ' Si ya se descontó de la tarjeta, se confirmará solo en unos minutos.');
    }

    public function cancelado(Pago $pago, PagoConTarjeta $tarjeta): RedirectResponse
    {
        abort_unless($pago->metodo === Pago::TARJETA, 404);

        $pago = $tarjeta->cancelar($pago);

        // Si ya estaba pagado (el cliente pagó y luego volvió atrás), no se anula.
        if ($pago->estado === Pago::PAGADO) {
            return redirect()->route('pagos.comprobante', $pago)->with('status', 'Ese pago ya estaba confirmado.');
        }

        return $this->volver($pago, 'El pago con tarjeta se canceló y no se cobró nada. Puedes intentarlo de nuevo.');
    }

    private function volver(Pago $pago, string $mensaje): RedirectResponse
    {
        return redirect()->route('pagos.create', ['contrato' => $pago->idcontrato])->with('error', $mensaje);
    }
}
