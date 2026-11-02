<?php

namespace App\Modules\AgendaVisitasPagos\CU28_RegistrarPago;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Modules\AgendaVisitasPagos\Compartido\PagoConTarjeta;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook de Stripe (POST /stripe/webhook): sin sesión ni token CSRF; su seguridad es la FIRMA del mensaje (STRIPE_WEBHOOK_SECRET).
 * Atiende checkout.session.completed con la misma función idempotente que la página de éxito, así que recibirlo dos veces
 * (o después de que el cliente ya volvió a la página de éxito) no duplica el pago ni el comprobante.
 */
class StripeWebhookController extends Controller
{
    public function recibir(Request $request, StripeService $stripe, PagoConTarjeta $tarjeta): JsonResponse
    {
        try {
            $evento = $stripe->construirEvento($request->getContent(), $request->header('Stripe-Signature'));
        } catch (\Throwable) {
            // Firma inválida o mensaje mal formado: se rechaza sin tocar nada.
            return response()->json(['error' => 'Firma no válida.'], 400);
        }

        if (($evento['type'] ?? null) === 'checkout.session.completed') {
            $sesion = $evento['data']['object'] ?? [];

            $pago = Pago::query()
                ->where('metodo', Pago::TARJETA)
                ->where(function ($q) use ($sesion) {
                    $q->where('stripe_session_id', $sesion['id'] ?? '-')
                        ->orWhere('idpago', ctype_digit((string) ($sesion['client_reference_id'] ?? '')) ? (int) $sesion['client_reference_id'] : 0);
                })
                ->first();

            // Un pago que no es de este sistema se ignora (Stripe espera igual un 200).
            if ($pago) {
                $tarjeta->confirmar($pago, [
                    'id' => $sesion['id'] ?? null,
                    'payment_status' => $sesion['payment_status'] ?? null,
                    'payment_intent' => $sesion['payment_intent'] ?? null,
                    'amount_total' => $sesion['amount_total'] ?? null,
                    'currency' => $sesion['currency'] ?? null,
                    'currency_conversion' => $sesion['currency_conversion'] ?? null,
                    'presentment_details' => $sesion['presentment_details'] ?? null,
                ]);
            }
        }

        return response()->json(['recibido' => true]);
    }
}
