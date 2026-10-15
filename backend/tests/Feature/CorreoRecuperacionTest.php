<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

/** Correo real de recuperación de contraseña (CU3) por la API web de Brevo, usada en Railway. */
class CorreoRecuperacionTest extends SistemaTestCase
{
    private function usarBrevo(?string $clave = 'clave-de-prueba'): void
    {
        config([
            'mail.default' => 'brevo',
            'mail.from.address' => 'lit.remitente@gmail.com',
            'mail.from.name' => 'LIT Inmobiliaria',
            'services.brevo.key' => $clave,
        ]);
    }

    public function test_el_correo_sale_por_la_api_de_brevo_con_la_clave_y_los_datos_correctos(): void
    {
        $this->usarBrevo();
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => '<abc@brevo>'], 201)]);

        Mail::raw('Hola', fn ($m) => $m->to('ana@correo.com', 'Ana')->subject('Prueba'));

        Http::assertSent(fn ($r) => $r->url() === 'https://api.brevo.com/v3/smtp/email'
            && $r->hasHeader('api-key', 'clave-de-prueba')
            && $r['sender']['email'] === 'lit.remitente@gmail.com'
            && $r['sender']['name'] === 'LIT Inmobiliaria'
            && $r['to'][0]['email'] === 'ana@correo.com'
            && $r['to'][0]['name'] === 'Ana'
            && $r['subject'] === 'Prueba'
            && $r['textContent'] === 'Hola'
            && filled($r['htmlContent']));
    }

    public function test_si_brevo_rechaza_el_envio_se_lanza_un_error_de_transporte(): void
    {
        $this->usarBrevo();
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'Key not found'], 401)]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Brevo respondió 401');

        Mail::raw('Hola', fn ($m) => $m->to('ana@correo.com')->subject('Prueba'));
    }

    public function test_sin_clave_no_se_intenta_enviar(): void
    {
        $this->usarBrevo(null);
        Http::fake();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Falta BREVO_API_KEY');

        Mail::raw('Hola', fn ($m) => $m->to('ana@correo.com')->subject('Prueba'));

        Http::assertNothingSent();
    }

    public function test_el_correo_de_recuperacion_llega_en_espanol_con_el_enlace(): void
    {
        $this->usarBrevo();
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => '<abc@brevo>'], 201)]);
        $usuario = $this->agente();

        $this->post(route('password.email'), ['email' => $usuario->email])
            ->assertSessionHas('status', 'Te enviamos un enlace de recuperación a tu correo.');

        Http::assertSent(function ($r) use ($usuario) {
            $html = $r['htmlContent'];

            return $r['to'][0]['email'] === $usuario->email
                && $r['subject'] === 'Recupera tu contraseña - LIT Inmobiliaria'
                && str_contains($html, 'Hola, ' . $usuario->nombre)
                && str_contains($html, 'Restablecer contraseña')
                && str_contains($html, '/restablecer-password/')
                && str_contains($html, 'Todos los derechos reservados')
                && str_contains($r['textContent'], 'en tu navegador')
                && ! str_contains($html, 'Hello')
                && ! str_contains($html, 'Regards');
        });
    }

    public function test_si_el_proveedor_de_correo_falla_se_avisa_sin_error_500(): void
    {
        $this->usarBrevo();
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'Sender not valid'], 400)]);
        $usuario = $this->agente();

        $this->post(route('password.email'), ['email' => $usuario->email])
            ->assertRedirect()
            ->assertSessionHasErrors(['email' => 'No pudimos enviar el correo en este momento. Inténtalo de nuevo en unos minutos.']);
    }
}
