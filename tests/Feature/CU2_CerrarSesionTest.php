<?php

namespace Tests\Feature;

use App\Models\Bitacora;

/** CU2: Cerrar sesión. */
class CU2_CerrarSesionTest extends SistemaTestCase
{
    public function test_cerrar_sesion_termina_la_sesion_y_vuelve_al_login(): void
    {
        $this->actingAs($this->agente());

        $this->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Sesión cerrada correctamente.');

        $this->assertGuest();
    }

    public function test_despues_de_cerrar_sesion_las_pantallas_protegidas_piden_iniciar_sesion(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('logout'));

        $this->get(route('panel'))->assertRedirect(route('login'));
        $this->get(route('propiedades.index'))->assertRedirect(route('login'));
        $this->get(route('usuarios.index'))->assertRedirect(route('login'));
    }

    public function test_cada_rol_puede_cerrar_sesion(): void
    {
        foreach (['admin', 'agente', 'asistente', 'cliente'] as $rol) {
            $this->actingAs($this->{$rol}())->post(route('logout'))->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_quien_no_inicio_sesion_no_puede_cerrar_sesion(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_se_cambia_el_token_de_seguridad_al_cerrar_sesion(): void
    {
        $this->actingAs($this->agente())->startSession();
        $tokenAntes = csrf_token();

        $this->post(route('logout'));

        $this->assertNotSame($tokenAntes, csrf_token());
    }

    public function test_el_cierre_de_sesion_queda_en_la_bitacora(): void
    {
        $agente = $this->agente();

        $this->actingAs($agente)->post(route('logout'));

        $registro = Bitacora::where('accion', 'CIERRE_SESION')->first();
        $this->assertNotNull($registro);
        $this->assertSame($agente->idusuario, $registro->idusuario);
        $this->assertStringContainsString($agente->email, $registro->descripcion);
    }

    public function test_el_menu_muestra_el_boton_cerrar_sesion_con_nombre_y_rol(): void
    {
        $this->actingAs($this->agente(['nombre' => 'Carla Torres']))->get(route('panel'))
            ->assertSee('Cerrar sesión')
            ->assertSee('Carla Torres')
            ->assertSee('Agente Inmobiliario');
    }
}
