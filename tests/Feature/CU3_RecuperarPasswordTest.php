<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Usuario;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/** CU3: Recuperar contraseña (solicitar enlace + definir la nueva contraseña). */
class CU3_RecuperarPasswordTest extends SistemaTestCase
{
    private const NUEVA = 'Nueva2026';

    private function restablecer(Usuario $usuario, string $token, array $cambios = [])
    {
        return $this->post(route('password.update'), array_merge([
            'token' => $token,
            'email' => $usuario->email,
            'password' => self::NUEVA,
            'password_confirmation' => self::NUEVA,
        ], $cambios));
    }

    // ------------------------------------------------------- parte 1: pedir el enlace

    public function test_se_muestra_la_pantalla_para_pedir_el_enlace(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Recuperar contraseña')
            ->assertSee('Enviar enlace');
    }

    public function test_el_enlace_de_recuperacion_se_envia_al_correo_de_una_cuenta_existente(): void
    {
        Notification::fake();
        $usuario = $this->agente();

        $this->post(route('password.email'), ['email' => $usuario->email])
            ->assertSessionHas('status', 'Te enviamos un enlace de recuperación a tu correo.');

        Notification::assertSentTo($usuario, ResetPassword::class);
    }

    public function test_correo_no_registrado_no_envia_nada_y_avisa(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nadie@test.com'])
            ->assertSessionHasErrors(['email' => 'No existe una cuenta registrada con ese correo.']);

        Notification::assertNothingSent();
    }

    public function test_el_correo_es_obligatorio_y_debe_ser_valido(): void
    {
        $this->post(route('password.email'), [])
            ->assertSessionHasErrors(['email' => 'Ingresa tu correo electrónico.']);

        $this->post(route('password.email'), ['email' => 'no-es-correo'])
            ->assertSessionHasErrors(['email' => 'Ingresa un correo electrónico válido.']);
    }

    public function test_no_se_puede_pedir_otro_enlace_de_inmediato(): void
    {
        Notification::fake();
        $usuario = $this->agente();

        $this->post(route('password.email'), ['email' => $usuario->email])->assertSessionHas('status');

        $this->post(route('password.email'), ['email' => $usuario->email])
            ->assertSessionHasErrors(['email' => 'Espera unos minutos antes de volver a solicitarlo.']);
    }

    public function test_la_solicitud_tiene_limite_de_5_por_minuto(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => 'nadie@test.com'])->assertStatus(302);
        }

        $this->post(route('password.email'), ['email' => 'nadie@test.com'])->assertStatus(429);
    }

    public function test_la_solicitud_queda_en_la_bitacora(): void
    {
        Notification::fake();
        $usuario = $this->agente();

        $this->post(route('password.email'), ['email' => $usuario->email]);

        $registro = Bitacora::where('accion', 'RECUPERACION_SOLICITADA')->first();
        $this->assertNotNull($registro);
        $this->assertStringContainsString($usuario->email, $registro->descripcion);
    }

    public function test_quien_ya_inicio_sesion_no_usa_la_recuperacion(): void
    {
        $this->actingAs($this->agente())->get(route('password.request'))->assertRedirect();
    }

    // ------------------------------------------------ parte 2: definir la contraseña

    public function test_el_enlace_del_correo_lleva_a_la_pantalla_de_nueva_contrasena(): void
    {
        Notification::fake();
        $usuario = $this->agente();
        $this->post(route('password.email'), ['email' => $usuario->email]);

        $token = null;
        Notification::assertSentTo($usuario, ResetPassword::class, function ($notificacion) use (&$token) {
            $token = $notificacion->token;

            return true;
        });

        $this->get(route('password.reset', ['token' => $token, 'email' => $usuario->email]))
            ->assertOk()
            ->assertSee('Nueva contraseña')
            ->assertSee($usuario->email);
    }

    public function test_con_un_enlace_valido_se_cambia_la_contrasena_y_ya_puede_iniciar_sesion(): void
    {
        $usuario = $this->agente();
        $token = Password::createToken($usuario);

        $this->restablecer($usuario, $token)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Tu contraseña fue actualizada. Ya puedes iniciar sesión.');

        $this->assertTrue(Hash::check(self::NUEVA, $usuario->fresh()->password));
        $this->assertFalse(Hash::check(self::CLAVE, $usuario->fresh()->password));

        $this->post(route('login.ingresar'), ['email' => $usuario->email, 'password' => self::NUEVA])
            ->assertRedirect(route('panel'));
    }

    public function test_la_contrasena_nueva_se_guarda_con_hash(): void
    {
        $usuario = $this->agente();
        $this->restablecer($usuario, Password::createToken($usuario));

        $guardada = DB::table('usuario')->where('idusuario', $usuario->idusuario)->value('password');

        $this->assertNotSame(self::NUEVA, $guardada);
        $this->assertStringStartsWith('$2y$', $guardada);
    }

    public function test_el_enlace_solo_sirve_una_vez(): void
    {
        $usuario = $this->agente();
        $token = Password::createToken($usuario);

        $this->restablecer($usuario, $token)->assertRedirect(route('login'));

        $this->restablecer($usuario, $token, ['password' => 'Otra2026x', 'password_confirmation' => 'Otra2026x'])
            ->assertSessionHasErrors(['email' => 'El enlace de recuperación es inválido o ha expirado. Solicita uno nuevo.']);

        $this->assertTrue(Hash::check(self::NUEVA, $usuario->fresh()->password));
    }

    public function test_un_enlace_expirado_se_rechaza(): void
    {
        $usuario = $this->agente();
        $token = Password::createToken($usuario);

        $this->travel(61)->minutes();

        $this->restablecer($usuario, $token)
            ->assertSessionHasErrors(['email' => 'El enlace de recuperación es inválido o ha expirado. Solicita uno nuevo.']);

        $this->assertTrue(Hash::check(self::CLAVE, $usuario->fresh()->password));
    }

    public function test_un_token_falso_se_rechaza(): void
    {
        $usuario = $this->agente();

        $this->restablecer($usuario, 'token-inventado')
            ->assertSessionHasErrors(['email' => 'El enlace de recuperación es inválido o ha expirado. Solicita uno nuevo.']);

        $this->assertTrue(Hash::check(self::CLAVE, $usuario->fresh()->password));
    }

    public function test_el_token_de_un_usuario_no_sirve_para_otro(): void
    {
        $victima = $this->agente();
        $atacante = $this->agente();
        $tokenAtacante = Password::createToken($atacante);

        $this->restablecer($victima, $tokenAtacante)->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::CLAVE, $victima->fresh()->password));
    }

    public function test_las_contrasenas_deben_coincidir(): void
    {
        $usuario = $this->agente();

        $this->restablecer($usuario, Password::createToken($usuario), ['password_confirmation' => 'Distinta123'])
            ->assertSessionHasErrors(['password' => 'Las contraseñas no coinciden.']);
    }

    public function test_la_nueva_contrasena_cumple_los_requisitos_de_seguridad(): void
    {
        $usuario = $this->agente();
        $token = Password::createToken($usuario);

        $casos = [
            'corta' => ['Ab1', 'La contraseña debe tener al menos 8 caracteres.'],
            'sin numeros' => ['SoloLetras', 'La contraseña debe incluir letras y números.'],
            'sin letras' => ['12345678', 'La contraseña debe incluir letras y números.'],
        ];

        foreach ($casos as [$clave, $mensaje]) {
            $this->restablecer($usuario, $token, ['password' => $clave, 'password_confirmation' => $clave])
                ->assertSessionHasErrors(['password' => $mensaje]);
        }

        $this->restablecer($usuario, $token, ['password' => '', 'password_confirmation' => ''])
            ->assertSessionHasErrors(['password' => 'Ingresa la nueva contraseña.']);
    }

    public function test_el_restablecimiento_queda_en_la_bitacora(): void
    {
        $usuario = $this->agente();
        $this->restablecer($usuario, Password::createToken($usuario));

        $registro = Bitacora::where('accion', 'PASSWORD_RESTABLECIDA')->first();
        $this->assertNotNull($registro);
        $this->assertSame($usuario->idusuario, $registro->idusuario);
    }
}
