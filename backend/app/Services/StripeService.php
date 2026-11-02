<?php

namespace App\Services;

use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Todas las llamadas a Stripe están aquí (Stripe Checkout, modo de prueba). Así el resto del sistema no depende de Stripe
 * y las pruebas automáticas usan un doble de esta clase (tests/Support/StripeFalso.php): nunca llaman a Stripe de verdad.
 * Las llaves salen de config/services.php (STRIPE_SECRET y STRIPE_WEBHOOK_SECRET del .env).
 */
class StripeService
{
    private ?StripeClient $cliente = null;

    private function cliente(): StripeClient
    {
        $llave = config('services.stripe.secret');

        if (! $llave) {
            throw new \RuntimeException('Falta la llave STRIPE_SECRET en el .env.');
        }

        return $this->cliente ??= new StripeClient($llave);
    }

    /**
     * Crea una sesión de Stripe Checkout para pagar un monto en dólares. No se toca Adaptive Pricing (la conversión automática
     * de Stripe, que se administra en el Dashboard): si está activa, en la página de Stripe el cliente puede elegir pagar en
     * dólares o en su moneda local (por ejemplo bolivianos) con el tipo de cambio de Stripe; el pago siempre se liquida en USD.
     *
     * @param int    $centavos      monto en dólares, en centavos
     * @param string $descripcion   texto que ve el cliente en la página de Stripe
     * @param array  $metadata      datos propios (por ejemplo el id del pago)
     * @return array{id: string, url: string}
     */
    public function crearSesionDePago(int $centavos, string $descripcion, string $referencia, array $metadata, string $urlExito, string $urlCancelado): array
    {
        $sesion = $this->cliente()->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $centavos,
                    'product_data' => ['name' => $descripcion],
                ],
            ]],
            'client_reference_id' => $referencia,
            'metadata' => $metadata,
            'payment_intent_data' => ['description' => $descripcion, 'metadata' => $metadata],
            // {CHECKOUT_SESSION_ID} lo reemplaza Stripe por el número de la sesión al volver.
            'success_url' => $urlExito,
            'cancel_url' => $urlCancelado,
        ]);

        return ['id' => $sesion->id, 'url' => $sesion->url];
    }

    /**
     * Consulta una sesión a Stripe desde el servidor (no se confía en lo que traiga la URL de vuelta).
     *
     * Con Adaptive Pricing, la API actual deja la sesión en dólares y agrega presentment_details (lo que vio y pagó el cliente
     * en su moneda); la API anterior (sesiones creadas antes del 2025-03-31) deja la sesión en la moneda del cliente y agrega
     * currency_conversion (con los dólares y el tipo de cambio). Se devuelven ambos tal cual para que PagoConTarjeta los lea.
     *
     * @return array{id: string, payment_status: string, payment_intent: ?string, amount_total: ?int, currency: ?string, client_reference_id: ?string, metadata: array, currency_conversion: ?array, presentment_details: ?array}
     */
    public function obtenerSesion(string $id): array
    {
        $sesion = $this->cliente()->checkout->sessions->retrieve($id);
        $datos = $sesion->toArray();

        return [
            'id' => $sesion->id,
            'payment_status' => (string) $sesion->payment_status,
            'payment_intent' => $sesion->payment_intent ? (string) $sesion->payment_intent : null,
            'amount_total' => $sesion->amount_total,
            'currency' => $sesion->currency,
            'client_reference_id' => $sesion->client_reference_id,
            'metadata' => $sesion->metadata ? $sesion->metadata->toArray() : [],
            'currency_conversion' => $datos['currency_conversion'] ?? null,
            'presentment_details' => $datos['presentment_details'] ?? null,
        ];
    }

    /**
     * Verifica la firma de un mensaje del webhook y lo devuelve como arreglo.
     *
     * @throws \Stripe\Exception\SignatureVerificationException|\UnexpectedValueException si la firma o el contenido no son válidos
     * @return array{type: string, data: array{object: array}}
     */
    public function construirEvento(string $contenido, ?string $firma): array
    {
        $secreto = config('services.stripe.webhook_secret');

        if (! $secreto) {
            throw new \UnexpectedValueException('Falta STRIPE_WEBHOOK_SECRET en el .env.');
        }

        return Webhook::constructEvent($contenido, (string) $firma, $secreto)->toArray();
    }
}
