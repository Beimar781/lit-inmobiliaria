<?php

namespace Tests\Feature;

use App\Models\Historial;
use App\Models\Propiedad;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/** CU31: Consultar historial de una propiedad (Administrador y Agente). Un test por paso del documento (6.2.4). */
class CU31_ConsultarHistorialPropiedadTest extends SistemaTestCase
{
    private function cambio(Propiedad $propiedad, string $tipo, string $fecha, ?Usuario $usuario = null, ?array $antes = null, ?array $despues = null, ?string $motivo = null): void
    {
        DB::table('historial')->insert([
            'idpropiedad' => $propiedad->idpropiedad,
            'idusuario' => $usuario?->idusuario,
            'tipo' => $tipo,
            'valoranterior' => $antes === null ? null : json_encode($antes),
            'valoractual' => $despues === null ? null : json_encode($despues),
            'motivo' => $motivo,
            'fecha' => $fecha,
        ]);
    }

    public function test_paso_1_abrir_el_historial_de_una_propiedad_con_cambios_muestra_fecha_tipo_usuario_y_valores(): void
    {
        $agente = $this->agente(['nombre' => 'Ana Agente']);
        $propiedad = $this->propiedad(['titulo' => 'Casa del Parque'], $agente);
        $this->cambio($propiedad, Historial::CAMBIO_ESTADO, '2026-11-10 09:30:00', $agente,
            ['estado' => 'DISPONIBLE'], ['estado' => 'RESERVADO'], 'Cliente dejó anticipo');

        $this->actingAs($agente)->get(route('propiedades.historial', $propiedad))->assertOk()
            ->assertSee('<span class="cu-etiqueta">(CU31)</span>', false)
            ->assertSee($propiedad->codigo)->assertSee('Casa del Parque')
            ->assertSee('10/11/2026 09:30')->assertSee('Cambio de estado')->assertSee('Ana Agente')
            ->assertSee('DISPONIBLE')->assertSee('RESERVADO')->assertSee('Cliente dejó anticipo')
            ->assertDontSee('{"estado"', false);
    }

    public function test_paso_2_filtrar_por_tipo_cambio_estado_muestra_solo_cambios_de_estado(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->cambio($propiedad, Historial::REGISTRO, '2026-11-01 08:00:00', $agente, null, ['titulo' => 'Titulo inicial']);
        $this->cambio($propiedad, Historial::CAMBIO_ESTADO, '2026-11-02 08:00:00', $agente, ['estado' => 'DISPONIBLE'], ['estado' => 'RESERVADO']);
        $this->cambio($propiedad, Historial::MODIFICACION, '2026-11-03 08:00:00', $agente, ['precio' => '1'], ['precio' => '2']);

        $respuesta = $this->actingAs($agente)->get(route('propiedades.historial', [$propiedad, 'tipo' => 'CAMBIO_ESTADO']))->assertOk();

        $respuesta->assertSee('Cambio de estado')->assertDontSee('Titulo inicial')->assertDontSee('<td>Modificación</td>', false)->assertDontSee('<td>Registro</td>', false);
        $this->assertSame(1, substr_count($respuesta->getContent(), 'data-bs-target="#detalle-'));
    }

    public function test_paso_3_filtrar_por_rango_de_fechas_muestra_solo_los_del_rango_incluyendo_ambos_dias(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->cambio($propiedad, Historial::MODIFICACION, '2026-10-31 23:59:00', $agente, ['titulo' => 'FueraAntes'], ['titulo' => 'x']);
        $this->cambio($propiedad, Historial::MODIFICACION, '2026-11-01 00:00:00', $agente, ['titulo' => 'BordeDesde'], ['titulo' => 'x']);
        $this->cambio($propiedad, Historial::MODIFICACION, '2026-11-05 12:00:00', $agente, ['titulo' => 'Medio'], ['titulo' => 'x']);
        $this->cambio($propiedad, Historial::MODIFICACION, '2026-11-10 23:59:59', $agente, ['titulo' => 'BordeHasta'], ['titulo' => 'x']);
        $this->cambio($propiedad, Historial::MODIFICACION, '2026-11-11 00:00:00', $agente, ['titulo' => 'FueraDespues'], ['titulo' => 'x']);

        $this->actingAs($agente)->get(route('propiedades.historial', [$propiedad, 'desde' => '2026-11-01', 'hasta' => '2026-11-10']))->assertOk()
            ->assertSee('BordeDesde')->assertSee('Medio')->assertSee('BordeHasta')
            ->assertDontSee('FueraAntes')->assertDontSee('FueraDespues');
    }

    public function test_paso_4_desde_posterior_a_hasta_muestra_rango_no_valido_y_no_consulta(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->cambio($propiedad, Historial::MODIFICACION, '2026-11-05 12:00:00', $agente, ['titulo' => 'NoDebeVerse'], ['titulo' => 'x']);

        $this->actingAs($agente)->get(route('propiedades.historial', [$propiedad, 'desde' => '2026-11-10', 'hasta' => '2026-11-01']))->assertOk()
            ->assertSee('El rango de fechas no es válido.')->assertDontSee('NoDebeVerse')->assertDontSee('Sin cambios registrados');
    }

    public function test_paso_5_propiedad_sin_cambios_muestra_sin_cambios_registrados(): void
    {
        $agente = $this->agente();

        $this->actingAs($agente)->get(route('propiedades.historial', $this->propiedad([], $agente)))->assertOk()
            ->assertSee('Sin cambios registrados');
    }

    public function test_paso_6_un_agente_que_ve_el_historial_de_una_propiedad_de_otro_agente_recibe_403(): void
    {
        $propiedad = $this->propiedad([], $this->agente());

        $this->actingAs($this->agente())->get(route('propiedades.historial', $propiedad))->assertForbidden();
    }

    public function test_el_administrador_ve_el_historial_de_una_propiedad_dada_de_baja(): void
    {
        $propiedad = $this->propiedad(['estadopropiedad' => Propiedad::BAJA], $this->agente());
        $this->cambio($propiedad, Historial::BAJA, '2026-11-12 10:00:00', null, ['estado' => 'DISPONIBLE'], ['estado' => 'BAJA'], 'Publicación duplicada');

        $this->actingAs($this->admin())->get(route('propiedades.historial', $propiedad))->assertOk()
            ->assertSee('Baja')->assertSee('Publicación duplicada');
    }

    public function test_propiedad_inexistente_da_404_y_clientes_o_asistentes_dan_403(): void
    {
        $this->actingAs($this->admin())->get('/propiedades/99999/historial')->assertNotFound();

        $propiedad = $this->propiedad();
        $this->actingAs($this->cliente())->get(route('propiedades.historial', $propiedad))->assertForbidden();
        $this->actingAs($this->asistente())->get(route('propiedades.historial', $propiedad))->assertForbidden();
    }

    public function test_parametros_basura_no_dan_error_500(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->admin());

        $this->get(route('propiedades.historial', $propiedad) . '?tipo[]=x&desde=zzz&hasta[]=1')->assertOk();
        $this->get(route('propiedades.historial', $propiedad) . '?tipo=INVENTADO&desde=2026-02-31&hasta=9999999')->assertOk();
    }

    public function test_el_orden_es_del_mas_reciente_al_mas_antiguo(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->cambio($propiedad, Historial::MODIFICACION, '2026-11-01 08:00:00', $agente, ['titulo' => 'PrimeroViejo'], ['titulo' => 'x']);
        $this->cambio($propiedad, Historial::MODIFICACION, '2026-11-09 08:00:00', $agente, ['titulo' => 'SegundoNuevo'], ['titulo' => 'x']);

        $html = $this->actingAs($agente)->get(route('propiedades.historial', $propiedad))->getContent();

        $this->assertLessThan(strpos($html, 'PrimeroViejo'), strpos($html, 'SegundoNuevo'));
    }

    public function test_la_paginacion_es_de_15_y_conserva_los_filtros(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        for ($i = 1; $i <= 16; $i++) {
            $this->cambio($propiedad, Historial::MODIFICACION, '2026-11-01 08:' . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . ':00', $agente, ['titulo' => "Cambio{$i}x"], ['titulo' => 'y']);
        }

        $respuesta = $this->actingAs($agente)->get(route('propiedades.historial', [$propiedad, 'tipo' => 'MODIFICACION']))->assertOk();

        $this->assertSame(15, substr_count($respuesta->getContent(), 'data-bs-target="#detalle-'));
        $respuesta->assertSee('tipo=MODIFICACION&amp;page=2', false);
    }

    public function test_el_inventario_muestra_el_enlace_historial_solo_en_las_propiedades_permitidas(): void
    {
        $agente = $this->agente();
        $propia = $this->propiedad(['titulo' => 'Mia'], $agente);
        $this->propiedad(['titulo' => 'Ajena'], $this->agente());

        $this->actingAs($agente)->get(route('propiedades.index'))->assertOk()
            ->assertSee(route('propiedades.historial', $propia), false);
        $this->assertSame(1, substr_count($this->get(route('propiedades.index'))->getContent(), '>Historial</a>'));
        $this->actingAs($this->admin())->get(route('propiedades.index'));
        $this->assertSame(2, substr_count($this->get(route('propiedades.index'))->getContent(), '>Historial</a>'));
    }
}