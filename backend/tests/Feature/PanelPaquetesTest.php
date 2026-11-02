<?php

namespace Tests\Feature;

use App\Models\Rol;
use Illuminate\Support\Facades\Route;

/** El panel muestra una tarjeta por módulo, solo las que el rol puede usar (config/paquetes.php). */
class PanelPaquetesTest extends SistemaTestCase
{
    private function panel($usuario)
    {
        return $this->actingAs($usuario)->get(route('panel'))->assertOk();
    }

    public function test_la_configuracion_tiene_los_6_paquetes_con_los_nombres_del_documento_en_orden(): void
    {
        $this->assertSame(['P1', 'P2', 'P3', 'P4', 'P5', 'P6'], array_keys(config('paquetes.paquetes')));
        $this->assertSame([
            'Autentificación y Seguridad',
            'Gestión de Propiedades',
            'Búsqueda y Consulta de Inmuebles',
            'CRM y Gestión de Clientes',
            'Agenda, Visitas y Pagos',
            'Reportes y Administración del Sistema',
        ], array_values(config('paquetes.paquetes')));
    }

    public function test_cada_tarjeta_esta_completa_pertenece_a_un_paquete_y_su_ruta_existe(): void
    {
        foreach (config('paquetes.tarjetas') as $tarjeta) {
            $this->assertArrayHasKey($tarjeta['paquete'], config('paquetes.paquetes'), $tarjeta['titulo']);
            $this->assertNotEmpty($tarjeta['titulo']);
            $this->assertNotEmpty($tarjeta['descripcion'], $tarjeta['titulo']);
            $this->assertNotEmpty($tarjeta['roles'], $tarjeta['titulo']);
            $this->assertTrue(Route::has($tarjeta['ruta']), "Falta la ruta {$tarjeta['ruta']} de {$tarjeta['titulo']}");
        }
    }

    public function test_el_administrador_ve_todas_las_tarjetas_del_personal(): void
    {
        $this->panel($this->admin())
            ->assertSee('Usuarios')->assertSee('Propiedades')->assertSee('Prospectos CRM')->assertSee('Catálogo')
            ->assertSee('Calendario de visitas')->assertSee('Reservas')->assertSee('Contratos')
            ->assertSee('Registrar pago')->assertSee('Historial de pagos')->assertSee('Bitácora')
            ->assertDontSee('Mis contratos y pagos');
    }

    public function test_el_agente_ve_solo_las_tarjetas_de_su_rol(): void
    {
        $this->panel($this->agente())
            ->assertSee('Propiedades')->assertSee('Prospectos CRM')->assertSee('Calendario de visitas')
            ->assertSee('Reservas')->assertSee('Contratos')
            ->assertDontSee('Usuarios')->assertDontSee('Bitácora')
            ->assertDontSee('Registrar pago')->assertDontSee('Historial de pagos');
    }

    public function test_el_asistente_ve_las_reservas_y_los_pagos(): void
    {
        $this->panel($this->asistente())
            ->assertSee('Reservas')->assertSee('Registrar pago')->assertSee('Historial de pagos')
            ->assertDontSee('Propiedades')->assertDontSee('Contratos')->assertDontSee('Calendario de visitas');
    }

    public function test_el_cliente_solo_ve_su_tarjeta_mis_contratos_y_pagos(): void
    {
        $this->panel($this->cliente())
            ->assertSee('Mis contratos y pagos')
            ->assertSee(route('portal.contratos'), false)
            ->assertDontSee('Propiedades')->assertDontSee('Reservas')->assertDontSee('Registrar pago');
    }

    public function test_el_personal_no_ve_la_tarjeta_del_cliente(): void
    {
        foreach ([$this->admin(), $this->agente(), $this->asistente()] as $usuario) {
            $this->panel($usuario)->assertDontSee('Mis contratos y pagos');
        }
    }

    public function test_el_panel_no_muestra_numeros_de_caso_de_uso(): void
    {
        $this->panel($this->admin())->assertDontSee('(CU');
    }

    public function test_un_rol_sin_tarjetas_ve_el_aviso(): void
    {
        $this->actingAs($this->admin());
        config(['paquetes.tarjetas' => [
            ['paquete' => 'P1', 'titulo' => 'Solo agentes', 'descripcion' => 'x', 'ruta' => 'propiedades.index', 'roles' => [Rol::AGENTE]],
        ]]);

        $this->get(route('panel'))->assertOk()->assertSee('no tiene opciones disponibles');
    }

    public function test_una_tarjeta_cuya_ruta_no_existe_no_se_muestra(): void
    {
        $this->actingAs($this->admin());
        config(['paquetes.tarjetas' => [
            ['paquete' => 'P1', 'titulo' => 'Tarjeta fantasma', 'descripcion' => 'x', 'ruta' => 'ruta.que.no.existe', 'roles' => [Rol::ADMINISTRADOR]],
        ]]);

        $this->get(route('panel'))->assertOk()->assertDontSee('Tarjeta fantasma');
    }
}
