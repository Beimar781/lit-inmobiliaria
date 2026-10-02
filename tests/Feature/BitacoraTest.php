<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Services\BitacoraService;

/** Bitácora del sistema (solo Administrador): consulta con filtros y registro de eventos. */
class BitacoraTest extends SistemaTestCase
{
    private function registro(string $accion, string $modulo, string $descripcion, $usuario = null, ?string $fecha = null, ?array $detalle = null): Bitacora
    {
        return Bitacora::create([
            'idusuario' => $usuario?->idusuario,
            'accion' => $accion,
            'modulo' => $modulo,
            'descripcion' => $descripcion,
            'detalle' => $detalle,
            'ip' => '10.0.0.5',
            'fecha' => $fecha ?? now(),
        ]);
    }

    public function test_solo_el_administrador_ve_la_bitacora(): void
    {
        $this->get(route('bitacora.index'))->assertRedirect(route('login'));

        foreach ([$this->agente(), $this->asistente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario)->get(route('bitacora.index'))->assertForbidden();
        }

        $this->actingAs($this->admin())->get(route('bitacora.index'))->assertOk()->assertSee('Bitácora del sistema');
    }

    public function test_sin_registros_muestra_un_aviso(): void
    {
        $this->actingAs($this->admin())->get(route('bitacora.index'))
            ->assertSee('No hay registros con esos filtros.');
    }

    public function test_muestra_fecha_usuario_modulo_accion_descripcion_e_ip(): void
    {
        $agente = $this->agente(['nombre' => 'Carla Torres']);
        $this->registro('PROPIEDAD_REGISTRADA', 'Propiedades', 'Registró la propiedad «Casa X»', $agente, '2026-03-05 16:20:00');

        $this->actingAs($this->admin())->get(route('bitacora.index'))
            ->assertSee('05/03/2026 16:20:00')
            ->assertSee('Carla Torres')
            ->assertSee('Propiedades')
            ->assertSee('Propiedad registrada')
            ->assertSee('Registró la propiedad')
            ->assertSee('10.0.0.5');
    }

    public function test_un_registro_sin_usuario_se_muestra_como_visitante(): void
    {
        $this->registro('LOGIN_FALLIDO', 'Autenticación', 'Intento fallido');

        $this->actingAs($this->admin())->get(route('bitacora.index'))->assertSee('Visitante / sistema');
    }

    public function test_el_detalle_se_puede_ver_como_json(): void
    {
        $this->registro('PROPIEDAD_BAJA', 'Propiedades', 'Dio de baja', null, null, ['motivo' => 'Ya no se ofrece']);

        $this->actingAs($this->admin())->get(route('bitacora.index'))
            ->assertSee('Ver detalle')
            ->assertSee('Ya no se ofrece')
            ->assertSee('&quot;motivo&quot;: &quot;Ya no se ofrece&quot;', false); // JSON formateado, no texto escapado dos veces
    }

    public function test_lo_mas_reciente_aparece_primero_y_pagina_de_25_en_25(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->registro('INICIO_SESION', 'Autenticación', "Evento {$i}");
        }
        $this->actingAs($this->admin());

        $this->get(route('bitacora.index'))->assertViewHas('registros', function ($p) {
            return $p->count() === 25 && $p->total() === 30 && $p->first()->descripcion === 'Evento 30';
        });
        $this->get(route('bitacora.index', ['page' => 2]))->assertViewHas('registros', fn ($p) => $p->count() === 5);
    }

    public function test_filtra_por_usuario_modulo_y_accion(): void
    {
        $ana = $this->agente(['nombre' => 'Ana']);
        $beto = $this->agente(['nombre' => 'Beto']);
        $this->registro('INICIO_SESION', 'Autenticación', 'Entró Ana', $ana);
        $this->registro('PROPIEDAD_BAJA', 'Propiedades', 'Baja de Beto', $beto);
        $this->registro('USUARIO_CREADO', 'Usuarios', 'Creación hecha por Ana', $ana);
        $this->actingAs($this->admin());

        $this->get(route('bitacora.index', ['idusuario' => $beto->idusuario]))
            ->assertSee('Baja de Beto')->assertDontSee('Entró Ana');

        $this->get(route('bitacora.index', ['modulo' => 'Usuarios']))
            ->assertSee('Creación hecha por Ana')->assertDontSee('Baja de Beto');

        $this->get(route('bitacora.index', ['accion' => 'INICIO_SESION']))
            ->assertSee('Entró Ana')->assertDontSee('Creación hecha por Ana');

        $this->get(route('bitacora.index', ['idusuario' => $ana->idusuario, 'modulo' => 'Propiedades']))
            ->assertDontSee('Entró Ana')->assertDontSee('Baja de Beto');
    }

    public function test_filtra_por_rango_de_fechas_incluyendo_los_dos_extremos(): void
    {
        $this->registro('INICIO_SESION', 'Autenticación', 'Muy antiguo', null, '2026-01-10 08:00:00');
        $this->registro('INICIO_SESION', 'Autenticación', 'Primer día', null, '2026-02-01 00:00:30');
        $this->registro('INICIO_SESION', 'Autenticación', 'Último día', null, '2026-02-28 23:59:30');
        $this->registro('INICIO_SESION', 'Autenticación', 'Muy nuevo', null, '2026-04-01 08:00:00');
        $this->actingAs($this->admin());

        $this->get(route('bitacora.index', ['desde' => '2026-02-01', 'hasta' => '2026-02-28']))
            ->assertSee('Primer día')->assertSee('Último día')
            ->assertDontSee('Muy antiguo')->assertDontSee('Muy nuevo');

        $this->get(route('bitacora.index', ['desde' => '2026-03-01']))->assertSee('Muy nuevo')->assertDontSee('Último día');
        $this->get(route('bitacora.index', ['hasta' => '2026-01-31']))->assertSee('Muy antiguo')->assertDontSee('Primer día');
    }

    public function test_una_fecha_invalida_se_ignora_en_lugar_de_dar_error(): void
    {
        $this->registro('INICIO_SESION', 'Autenticación', 'Visible siempre');

        $this->actingAs($this->admin())->get(route('bitacora.index', ['desde' => 'no-es-fecha']))
            ->assertOk()->assertSee('Visible siempre');
    }

    public function test_busca_texto_en_la_descripcion_o_por_ip(): void
    {
        $this->registro('INICIO_SESION', 'Autenticación', 'Inició sesión: ana@test.com');
        $this->registro('INICIO_SESION', 'Autenticación', 'Inició sesión: beto@test.com');
        Bitacora::where('descripcion', 'like', '%beto%')->update(['ip' => '192.168.1.77']);
        $this->actingAs($this->admin());

        $this->get(route('bitacora.index', ['q' => 'ana@test']))->assertSee('ana@test.com')->assertDontSee('beto@test.com');
        $this->get(route('bitacora.index', ['q' => '192.168.1.77']))->assertSee('beto@test.com')->assertDontSee('ana@test.com');
    }

    public function test_el_servicio_guarda_ip_fecha_y_el_usuario_con_sesion(): void
    {
        $agente = $this->agente();
        $this->actingAs($agente);

        BitacoraService::registrar('PROPIEDAD_MODIFICADA', 'Propiedades', 'Prueba', ['a' => 1]);

        $registro = Bitacora::first();
        $this->assertSame($agente->idusuario, $registro->idusuario);
        $this->assertNotNull($registro->ip);
        $this->assertNotNull($registro->fecha);
    }

    public function test_el_detalle_se_guarda_y_se_lee_como_arreglo_con_tildes(): void
    {
        BitacoraService::registrar('PROPIEDAD_BAJA', 'Propiedades', 'Prueba', ['motivo' => 'Anulación por notaría', 'n' => 3], null);

        $detalle = Bitacora::first()->detalle;

        $this->assertIsArray($detalle);
        $this->assertSame('Anulación por notaría', $detalle['motivo']);
        $this->assertSame(3, $detalle['n']);
    }

    public function test_sin_detalle_queda_vacio(): void
    {
        BitacoraService::registrar('INICIO_SESION', 'Autenticación', 'Prueba');

        $this->assertNull(Bitacora::first()->detalle);
    }

    public function test_un_fallo_al_escribir_la_bitacora_no_rompe_la_operacion(): void
    {
        // idusuario inexistente: la llave foránea falla, pero el servicio no debe lanzar excepción
        BitacoraService::registrar('INICIO_SESION', 'Autenticación', 'Prueba', null, 99999);

        $this->assertTrue(true);
    }
}
