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

    public function crearSesionDePago(int $centavos, string $descripcion, string $referencia, array $metadata, string $urlExito, string $urlCancelado, string $moneda = 'usd'): array
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
            'currency' => strtolower($moneda),
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

    public function ultimaSesion(): array
    {
        return end($this->sesiones) ?: [];
    }
}
