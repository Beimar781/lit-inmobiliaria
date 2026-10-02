<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Usuario;

/** CU1: Iniciar sesión (+ panel de inicio y cuenta desactivada con sesión abierta). */
class CU1_IniciarSesionTest extends SistemaTestCase
{
    public function test_el_invitado_ve_la_pantalla_de_login(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Iniciar sesión')
            ->assertSee('Correo electrónico')
            ->assertSee('¿Olvidaste tu contraseña?');
    }

    public function test_cada_rol_puede_iniciar_sesion_y_llega_al_panel(): void
    {
        foreach (['admin', 'agente', 'asistente', 'cliente'] as $rol) {
            $usuario = $this->{$rol}();

            $this->post(route('login.ingresar'), ['email' => $usuario->email, 'password' => self::CLAVE])
                ->assertRedirect(route('panel'));

            $this->assertAuthenticatedAs($usuario);
            $this->post(route('logout'));
        }
    }

    public function test_despues_de_iniciar_sesion_vuelve_a_la_pagina_que_queria_visitar(): void
    {
        $admin = $this->admin();

        $this->get(route('usuarios.index'))->assertRedirect(route('login'));

        $this->post(route('login.ingresar'), ['email' => $admin->email, 'password' => self::CLAVE])
            ->assertRedirect(route('usuarios.index'));
    }

    public function test_contrasena_incorrecta_no_inicia_sesion(): void
    {
        $usuario = $this->agente();

        $this->from(route('login'))
            ->post(route('login.ingresar'), ['email' => $usuario->email, 'password' => 'otra-clave'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'El correo o la contraseña son incorrectos.']);

        $this->assertGuest();
    }

    public function test_correo_no_registrado_da_el_mismo_mensaje_generico(): void
    {
        $this->post(route('login.ingresar'), ['email' => 'nadie@test.com', 'password' => self::CLAVE])
            ->assertSessionHasErrors(['email' => 'El correo o la contraseña son incorrectos.']);

        $this->assertGuest();
    }

    public function test_cuenta_inactiva_con_contrasena_correcta_muestra_mensaje_de_cuenta_inactiva(): void
    {
        $usuario = $this->agente(['estado' => Usuario::INACTIVO]);

        $this->post(route('login.ingresar'), ['email' => $usuario->email, 'password' => self::CLAVE])
            ->assertSessionHasErrors(['email' => 'Tu cuenta está inactiva o bloqueada. Comunícate con el administrador.']);

        $this->assertGuest();
    }

    public function test_cuenta_inactiva_con_contrasena_incorrecta_no_revela_que_existe(): void
    {
        $usuario = $this->agente(['estado' => Usuario::INACTIVO]);

        $this->post(route('login.ingresar'), ['email' => $usuario->email, 'password' => 'incorrecta'])
            ->assertSessionHasErrors(['email' => 'El correo o la contraseña son incorrectos.']);
    }

    public function test_los_campos_son_obligatorios_y_el_correo_debe_ser_valido(): void
    {
        $this->post(route('login.ingresar'), [])
            ->assertSessionHasErrors([
                'email' => 'Ingresa tu correo electrónico.',
                'password' => 'Ingresa tu contraseña.',
            ]);

        $this->post(route('login.ingresar'), ['email' => 'no-es-correo', 'password' => 'x'])
            ->assertSessionHasErrors(['email' => 'Ingresa un correo electrónico válido.']);
    }

    public function test_la_sesion_se_regenera_al_iniciar_sesion(): void
    {
        $usuario = $this->admin();
        $this->startSession();
        $idAntes = session()->getId();

        $this->post(route('login.ingresar'), ['email' => $usuario->email, 'password' => self::CLAVE]);

        $this->assertNotSame($idAntes, session()->getId());
    }

    public function test_el_login_tiene_limite_de_10_intentos_por_minuto(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('login.ingresar'), ['email' => 'x@test.com', 'password' => 'mal'])->assertStatus(302);
        }

        $this->post(route('login.ingresar'), ['email' => 'x@test.com', 'password' => 'mal'])->assertStatus(429);
    }

    public function test_quien_ya_inicio_sesion_no_ve_el_login(): void
    {
        $this->actingAs($this->agente())->get(route('login'))->assertRedirect();
    }

    public function test_la_raiz_redirige_segun_haya_sesion_o_no(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->actingAs($this->agente())->get('/')->assertRedirect(route('panel'));
    }

    // ------------------------------------------------------------------- bitácora

    public function test_el_inicio_de_sesion_queda_en_la_bitacora(): void
    {
        $usuario = $this->agente();

        $this->post(route('login.ingresar'), ['email' => $usuario->email, 'password' => self::CLAVE]);

        $registro = Bitacora::where('accion', 'INICIO_SESION')->first();
        $this->assertNotNull($registro);
        $this->assertSame($usuario->idusuario, $registro->idusuario);
        $this->assertSame('Autenticación', $registro->modulo);
        $this->assertNotNull($registro->ip);
    }

    public function test_el_intento_fallido_queda_en_la_bitacora_sin_usuario(): void
    {
        $this->post(route('login.ingresar'), ['email' => 'intruso@test.com', 'password' => 'mal']);

        $registro = Bitacora::where('accion', 'LOGIN_FALLIDO')->first();
        $this->assertNotNull($registro);
        $this->assertNull($registro->idusuario);
        $this->assertStringContainsString('intruso@test.com', $registro->descripcion);
    }

    // ---------------------------------------------------------------------- panel

    public function test_el_panel_exige_iniciar_sesion(): void
    {
        $this->get(route('panel'))->assertRedirect(route('login'));
    }

    public function test_el_panel_del_administrador_muestra_usuarios_propiedades_y_bitacora(): void
    {
        $admin = $this->admin(['nombre' => 'Ana Admin']);

        $this->actingAs($admin)->get(route('panel'))
            ->assertOk()
            ->assertSee('Bienvenido, Ana Admin')
            ->assertSee('Administrador')
            ->assertSee(route('usuarios.index'), false)
            ->assertSee(route('propiedades.index'), false)
            ->assertSee(route('bitacora.index'), false);
    }

    public function test_el_panel_del_agente_muestra_solo_propiedades(): void
    {
        $this->actingAs($this->agente())->get(route('panel'))
            ->assertOk()
            ->assertSee(route('propiedades.index'), false)
            ->assertDontSee(route('usuarios.index'), false)
            ->assertDontSee(route('bitacora.index'), false);
    }

    public function test_el_panel_del_asistente_y_del_cliente_no_tiene_modulos(): void
    {
        foreach ([$this->asistente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario)->get(route('panel'))
                ->assertOk()
                ->assertSee('no tiene opciones disponibles')
                ->assertDontSee(route('propiedades.index'), false)
                ->assertDontSee(route('usuarios.index'), false);
        }
    }

    // ------------------------------------------------- cuenta desactivada en vivo

    public function test_si_desactivan_la_cuenta_con_la_sesion_abierta_se_cierra_en_la_siguiente_accion(): void
    {
        $agente = $this->agente();
        $this->actingAs($agente)->get(route('panel'))->assertOk();

        $agente->update(['estado' => Usuario::INACTIVO]);

        $this->get(route('panel'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Tu cuenta fue desactivada. Comunícate con el administrador.']);

        $this->assertGuest();
    }
}
