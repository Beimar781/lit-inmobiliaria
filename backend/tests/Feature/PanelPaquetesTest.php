<?php

namespace Tests\Feature;

use App\Models\Rol;
use Illuminate\Support\Facades\Route;

/** El panel muestra los paquetes del documento y, dentro de cada uno, los casos de uso que el rol puede usar. */
class PanelPaquetesTest extends SistemaTestCase
{
    private const PAQUETES = [
        'P1' => 'Autentificación y Seguridad',
        'P2' => 'Gestión de Propiedades',
        'P3' => 'Búsqueda y Consulta de Inmuebles',
        'P4' => 'CRM y Gestión de Clientes',
        'P5' => 'Agenda, Visitas y Pagos',
        'P6' => 'Reportes y Administración del Sistema',
    ];

    private function panel($usuario)
    {
        return $this->actingAs($usuario)->get(route('panel'))->assertOk();
    }

    public function test_la_configuracion_tiene_los_6_paquetes_con_los_nombres_del_documento_en_orden(): void
    {
        $paquetes = config('paquetes');

        $this->assertSame(array_keys(self::PAQUETES), array_column($paquetes, 'codigo'));
        $this->assertSame(array_values(self::PAQUETES), array_column($paquetes, 'nombre'));
    }

    public function test_cada_caso_de_uso_de_la_configuracion_esta_completo_y_su_ruta_existe(): void
    {
        $codigos = [];

        foreach (config('paquetes') as $paquete) {
            foreach (array_merge($paquete['casos'], $paquete['administracion'] ?? []) as $caso) {
                $this->assertNotEmpty($caso['nombre']);
                $this->assertNotEmpty($caso['roles'], $caso['nombre']);
                $this->assertTrue(Route::has($caso['ruta']), "Falta la ruta {$caso['ruta']} de {$caso['nombre']}");

                if (isset($caso['codigo'])) {
                    $this->assertContains($caso['ciclo'], ['I', 'II', 'III'], $caso['codigo']);
                    $this->assertNotContains($caso['codigo'], $codigos, 'CU repetido');
                    $codigos[] = $caso['codigo'];
                }
            }
        }

        $this->assertSame(array_map(fn ($n) => "CU{$n}", range(1, 14)), $codigos);
    }

    public function test_el_administrador_ve_los_6_paquetes_y_los_cu_de_los_ciclos_i_ii_y_iii(): void
    {
        $respuesta = $this->panel($this->admin());

        foreach (self::PAQUETES as $nombre) {
            $respuesta->assertSee($nombre);
        }
        foreach ([
            'Iniciar sesión', 'Cerrar sesión', 'Recuperar contraseña', 'Gestionar usuarios',
            'Registrar propiedad', 'Modificar propiedad', 'Eliminar propiedad', 'Cambiar estado de propiedad',
            'Buscar propiedades', 'Filtrar propiedades', 'Ver detalle de propiedad',
            'Gestionar prospectos CRM', 'Agendar visita a inmueble',
        ] as $caso) {
            $respuesta->assertSee($caso);
        }
        $respuesta->assertSee(route('usuarios.index'), false)
            ->assertSee(route('propiedades.create'), false)
            ->assertSee(route('catalogo.index'), false)
            ->assertSee(route('prospectos.index'), false)
            ->assertSee('Administración')
            ->assertSee(route('bitacora.index'), false)
            // CU12 por la vía del agente: el administrador no tiene ese formulario
            ->assertDontSee('Registrar cliente interesado');
    }

    public function test_la_bitacora_va_en_un_bloque_de_administracion_dentro_de_p6(): void
    {
        $html = $this->panel($this->admin())->getContent();

        $p6 = strpos($html, 'Reportes y Administración del Sistema');
        $this->assertNotFalse($p6);
        $this->assertGreaterThan($p6, strpos($html, 'Administración</li>'));
        $this->assertGreaterThan($p6, strpos($html, route('bitacora.index')));
    }

    public function test_el_agente_ve_solo_los_cu_de_su_rol(): void
    {
        $respuesta = $this->panel($this->agente());

        foreach (['P1' => true, 'P2' => true, 'P3' => true, 'P4' => true, 'P5' => true, 'P6' => false] as $codigo => $visible) {
            $visible
                ? $respuesta->assertSee(self::PAQUETES[$codigo])
                : $respuesta->assertDontSee(self::PAQUETES[$codigo]);
        }
        $respuesta
            ->assertSee('Iniciar sesión')->assertSee('Cerrar sesión')->assertSee('Recuperar contraseña')
            ->assertSee('Registrar propiedad')->assertSee('Modificar propiedad')->assertSee('Cambiar estado de propiedad')
            ->assertSee('Buscar propiedades')->assertSee('Filtrar propiedades')->assertSee('Ver detalle de propiedad')
            ->assertSee('Registrar cliente interesado')->assertSee('Gestionar prospectos CRM')->assertSee('Agendar visita a inmueble')
            ->assertSee(route('prospectos.create'), false)
            // lo que no es del agente
            ->assertDontSee('Gestionar usuarios')
            ->assertDontSee('Eliminar propiedad')
            ->assertDontSee('Bitácora')
            ->assertDontSee(route('usuarios.index'), false)
            ->assertDontSee(route('bitacora.index'), false);
    }

    public function test_el_asistente_ve_solo_el_paquete_p1_sin_gestionar_usuarios(): void
    {
        $respuesta = $this->panel($this->asistente());

        $respuesta->assertSee(self::PAQUETES['P1'])
            ->assertSee('Cerrar sesión')
            ->assertDontSee('Gestionar usuarios')
            ->assertDontSee('Registrar propiedad')
            ->assertDontSee('Buscar propiedades')
            ->assertDontSee('Gestionar prospectos CRM')
            ->assertDontSee('Bitácora');

        foreach (['P2', 'P3', 'P4', 'P5', 'P6'] as $codigo) {
            $respuesta->assertDontSee(self::PAQUETES[$codigo]);
        }
    }

    public function test_el_cliente_no_ve_ningun_paquete_del_personal(): void
    {
        $respuesta = $this->panel($this->cliente());

        $respuesta->assertSee('Por ahora tu rol no tiene opciones disponibles');
        foreach (self::PAQUETES as $nombre) {
            $respuesta->assertDontSee($nombre);
        }
    }

    public function test_cerrar_sesion_aparece_como_boton_y_no_como_enlace(): void
    {
        $this->panel($this->agente())
            ->assertSee('action="' . route('logout') . '"', false)
            ->assertSee('<button type="submit" class="btn btn-link p-0 text-start text-decoration-none">', false);
    }

    public function test_un_cu_cuya_ruta_todavia_no_existe_no_se_muestra(): void
    {
        $paquetes = config('paquetes');
        $paquetes[4]['casos'][] = [
            'codigo' => 'CU99', 'nombre' => 'Caso sin ruta', 'ciclo' => 'III', 'ruta' => 'ruta.que.no.existe',
            'roles' => [Rol::ADMINISTRADOR],
        ];
        config(['paquetes' => $paquetes]);

        $this->panel($this->admin())->assertDontSee('Caso sin ruta');
    }

    public function test_un_cu_nuevo_de_la_configuracion_aparece_dentro_de_su_paquete_solo_para_sus_roles(): void
    {
        $paquetes = config('paquetes');
        $paquetes[4]['casos'][] = [
            'codigo' => 'CU98', 'nombre' => 'Caso de prueba del paquete cinco', 'ciclo' => 'III', 'ruta' => 'bitacora.index',
            'roles' => [Rol::ADMINISTRADOR, Rol::ASISTENTE],
        ];
        config(['paquetes' => $paquetes]);

        $this->panel($this->admin())->assertSee('Caso de prueba del paquete cinco');
        $this->panel($this->asistente())->assertSee('Caso de prueba del paquete cinco')->assertSee(self::PAQUETES['P5']);
        $this->panel($this->agente())->assertDontSee('Caso de prueba del paquete cinco');
    }

    public function test_el_panel_esta_preparado_para_celular(): void
    {
        $this->panel($this->admin())
            ->assertSee('name="viewport"', false)
            ->assertSee('col-12 col-md-6 col-xl-4', false);
    }
}
