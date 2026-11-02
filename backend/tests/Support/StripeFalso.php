<?php

namespace Tests\Support;

use App\Services\StripeService;

/**
 * Doble de StripeService para las pruebas: NUNCA llama a Stripe. Guarda lo que se le pide y deja decidir
 * si la sesión "se pagó". La verificación de la firma del webhook sí es la real (se calcula sin internet).
 */
class StripeFalso extends StripeService
{
    /** @var array<string, array> sesiones creadas, por id */
    public array $sesiones = [];

    public bool $fallar = false;

    public bool $fallarAlConsultar = false;

    public function crearSesionDePago(int $centavos, string $descripcion, string $referencia, array $metadata, string $urlExito, string $urlCancelado): array
    {
        if ($this->fallar) {
            throw new \RuntimeException('Stripe no responde (simulado).');
        }

        $id = 'cs_test_' . (count($this->sesiones) + 1);
        $this->sesiones[$id] = [
            'id' => $id,
            'payment_status' => 'unpaid',
            'payment_intent' => null,
            'amount_total' => $centavos,
            'currency' => 'usd',
            'client_reference_id' => $referencia,
            'metadata' => $metadata,
            // lo que se le pidió a Stripe, para comprobarlo en las pruebas
            'descripcion' => $descripcion,
            'success_url' => $urlExito,
            'cancel_url' => $urlCancelado,
            'url' => "https://checkout.stripe.test/pay/{$id}",
        ];

        return ['id' => $id, 'url' => $this->sesiones[$id]['url']];
    }

    public function obtenerSesion(string $id): array
    {
        if ($this->fallarAlConsultar) {
            throw new \RuntimeException('Stripe no responde (simulado).');
        }

        return $this->sesiones[$id] ?? throw new \RuntimeException("Sesión {$id} desconocida.");
    }

    /** El cliente pagó con una tarjeta de prueba. */
    public function pagar(string $id): void
    {
        $this->sesiones[$id]['payment_status'] = 'paid';
        $this->sesiones[$id]['payment_intent'] = 'pi_test_' . $id;
    }

    /**
     * El cliente pagó en bolivianos en la página de Stripe (Adaptive Pricing). Stripe liquida los dólares de la sesión.
     * $forma 'actual': la sesión sigue en USD y trae presentment_details (API actual);
     * $forma 'anterior': la sesión queda en la moneda del cliente y trae currency_conversion (API anterior al 2025-03-31).
     */
    public function pagarEnMonedaLocal(string $id, int $centavosLocal, string $moneda = 'bob', float $tipoCambio = 6.96, string $forma = 'actual'): void
    {
        $this->pagar($id);
        $usd = $this->sesiones[$id]['amount_total'];

        if ($forma === 'anterior') {
            $this->sesiones[$id]['currency'] = $moneda;
            $this->sesiones[$id]['amount_total'] = $centavosLocal;
            $this->sesiones[$id]['currency_conversion'] = ['amount_subtotal' => $usd, 'amount_total' => $usd, 'fx_rate' => (string) $tipoCambio, 'source_currency' => 'usd'];
        } else {
            $this->sesiones[$id]['presentment_details'] = ['presentment_amount' => $centavosLocal, 'presentment_currency' => $moneda];
        }
    }

    public function ultimaSesion(): array
    {
        return end($this->sesiones) ?: [];
    }
}
