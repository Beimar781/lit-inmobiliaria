<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Propiedad;
use App\Models\Usuario;
use App\Models\Visita;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * CU20 con IA: la IA solo interpreta la frase (devuelve criterios) y redacta un resumen de cifras agregadas.
 * TODAS las pruebas usan Http::fake(): nunca hay llamadas reales ni llave real.
 */
class CU20_ReportesConIaTest extends SistemaTestCase
{
    private const SIN_RECONOCER = 'No reconocí un reporte en tu solicitud. Reformúlala o usa los filtros manuales.';
    private const SIN_SERVICIO = 'El servicio de inteligencia artificial no respondió. Continúa con los filtros manuales.';
    private const SIN_PERMISO = 'Este reporte no está permitido para tu rol.';
    private const RANGO = 'El rango de fechas no es válido.';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ia.key' => 'clave-de-prueba-falsa', 'services.ia.modelo' => 'modelo-de-prueba']);
        Http::preventStrayRequests();
    }

    /** Http::fake() acumula respuestas y gana la primera: cada vez se parte de un cliente HTTP nuevo. */
    private function reiniciarHttp(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
    }

    /** La IA falsa: responde el JSON de criterios a la interpretación y un texto al resumen. */
    private function iaFalsa(array|string|null $criterios, string $resumen = 'Hay 3 propiedades disponibles.'): void
    {
        $this->reiniciarHttp();
        Http::fake(function (Request $peticion) use ($criterios, $resumen) {
            $texto = str_contains($peticion['systemInstruction']['parts'][0]['text'], 'intérprete')
                ? (is_string($criterios) ? $criterios : json_encode($criterios))
                : $resumen;

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => $texto]]]]]]);
        });
    }

    private function interpretar(string $solicitud)
    {
        return $this->post(route('reportes.interpretar'), ['solicitud' => $solicitud]);
    }

    private function propiedadesPorCategoria(): void
    {
        $casa = $this->categoria('Casa');
        $depto = $this->categoria('Departamento');
        foreach ([[$casa, 'DISPONIBLE', 100000], [$casa, 'DISPONIBLE', 50000], [$depto, 'DISPONIBLE', 70000], [$casa, 'VENDIDO', 1]] as [$cat, $estado, $precio]) {
            $this->propiedad(['idcategoria' => $cat->idcategoria, 'estadopropiedad' => $estado, 'precio' => $precio]);
        }
    }

    public function test_paso_1_el_administrador_escribe_propiedades_disponibles_por_categoria_y_el_reporte_sale_directo_con_totales_resumen_y_criterios_usados(): void
    {
        $this->propiedadesPorCategoria();
        $this->iaFalsa(['tipo' => 'propiedades', 'periodo' => null, 'filtros' => ['estado' => 'DISPONIBLE'], 'agrupacion' => 'categoria'],
            'Hay 3 propiedades disponibles, 2 casas y 1 departamento.');
        $this->actingAs($this->admin());

        // La IA entendió y los criterios son válidos: va directo al reporte, sin pantalla de confirmación
        $destino = $this->interpretar('propiedades disponibles por categoría')->headers->get('Location');
        $this->assertStringStartsWith(route('reportes.generar'), $destino);
        foreach (['tipo=propiedades', 'estado=DISPONIBLE', 'agrupacion=categoria', 'origen=ia', 'con_resumen=1'] as $parte) {
            $this->assertStringContainsString($parte, $destino);
        }
        Http::assertSentCount(1);

        $respuesta = $this->get($destino)->assertOk();
        $respuesta->assertSee('220,000.00')->assertSee('Total de filas')
            ->assertSee('Resumen')->assertSee('redactado por IA')->assertSee('Hay 3 propiedades disponibles, 2 casas y 1 departamento.')
            ->assertSee('Exportar PDF')->assertSee('Exportar Excel')
            ->assertSee('Criterios usados: Tipo: Propiedades · Estado: Disponible · Agrupado por categoría')->assertSee('Ajustar criterios')
            ->assertDontSee('Esto fue lo que entendí')->assertDontSee('Confirmar y generar');
        $this->assertMatchesRegularExpression('/<td[^>]*>Casa<\/td>\s*<td[^>]*>2<\/td>/', $respuesta->getContent());
        Http::assertSentCount(2);

        $bitacora = Bitacora::where('accion', 'REPORTE_GENERADO')->firstOrFail();
        $this->assertSame('ia', $bitacora->detalle['origen']);
        $this->assertSame('propiedades disponibles por categoría', $bitacora->detalle['solicitud']);
    }

    public function test_paso_8_una_solicitud_que_no_corresponde_a_ningun_reporte_no_genera_nada_y_muestra_el_panel_de_lo_que_entendi(): void
    {
        $this->propiedadesPorCategoria();
        $this->iaFalsa(['tipo' => null]);
        $this->actingAs($this->admin());

        DB::enableQueryLog();
        $destino = $this->interpretar('cuál es la capital de Bolivia')->headers->get('Location');
        $consultadas = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => preg_match('/from ["`]?(propiedad|contrato|pago|visita|cliente)["`]?/i', $q));
        DB::disableQueryLog();

        $this->assertStringNotContainsString('/reportes/generar', $destino);
        $this->assertCount(0, $consultadas, 'No debe consultar los datos del reporte.');
        $this->assertSame(0, Bitacora::where('accion', 'REPORTE_GENERADO')->count());

        $this->get($destino)->assertOk()
            ->assertSee(self::SIN_RECONOCER)->assertSee('cuál es la capital de Bolivia')
            ->assertSee('Esto fue lo que entendí')->assertSee('Confirmar y generar')
            ->assertDontSee('Total de filas')->assertDontSee('Exportar PDF');
    }

    public function test_paso_9_si_el_servicio_de_ia_no_responde_se_avisa_y_se_puede_continuar_con_los_filtros_manuales(): void
    {
        $this->contratoDePrueba();
        $this->actingAs($this->admin());

        foreach ([
            fn () => throw new ConnectionException('cURL error 28: timeout'),
            fn () => Http::response(['error' => 'interno'], 500),
            fn () => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'esto no es json']]]]]]),
            fn () => Http::response(['candidates' => []]),
        ] as $falla) {
            $this->reiniciarHttp();
            Http::fake($falla);
            $this->interpretar('contratos del mes')->assertSessionHasErrors(['solicitud' => self::SIN_SERVICIO]);
        }

        // En ese mismo estado, el reporte manual sigue funcionando
        $this->get(route('reportes.generar', ['tipo' => 'contratos']))->assertOk()->assertSee('1,500.00')->assertSee('Exportar PDF');
    }

    public function test_los_criterios_invalidos_o_fuera_de_rol_no_generan_nada_y_cada_caso_da_su_mensaje(): void
    {
        $this->actingAs($this->agente());

        foreach ([
            [['tipo' => 'pagos'], self::SIN_PERMISO],                                                         // el Agente no ve pagos
            [['tipo' => 'planetas'], self::SIN_RECONOCER],                                                    // tipo inventado
            [['tipo' => 'propiedades', 'filtros' => ['estado' => 'FLOTANTE']], self::SIN_RECONOCER],         // estado inventado
            [['tipo' => 'propiedades', 'agrupacion' => 'agente'], self::SIN_PERMISO],                         // el Agente no agrupa por agente
            [['tipo' => 'visitas', 'agrupacion' => 'categoria'], self::SIN_RECONOCER],                        // categoría solo es de propiedades
            [['tipo' => 'propiedades', 'filtros' => ['desde' => '2026-12-31', 'hasta' => '2026-01-01']], self::RANGO], // rango invertido
            [['tipo' => 'propiedades', 'filtros' => ['desde' => 'ayer']], self::RANGO],                       // fecha mal formada
            ['esto no es un objeto json', self::SIN_SERVICIO],
            [['tipo' => ['propiedades']], self::SIN_RECONOCER],
        ] as [$criterios, $mensaje]) {
            $this->iaFalsa($criterios);
            $destino = $this->interpretar('lo que sea')->assertSessionHasErrors(['solicitud' => $mensaje])->headers->get('Location');
            $this->assertStringNotContainsString('/reportes/generar', $destino);
        }
        $this->assertSame(0, Bitacora::where('accion', 'REPORTE_GENERADO')->count());
    }

    public function test_un_rol_no_permitido_no_genera_y_el_panel_muestra_los_criterios_con_el_aviso_de_permiso(): void
    {
        $this->iaFalsa(['tipo' => 'pagos', 'filtros' => ['estado' => 'PENDIENTE']]);
        $this->actingAs($this->agente());

        $destino = $this->interpretar('pagos pendientes')->headers->get('Location');

        $this->get($destino)->assertOk()
            ->assertSee('Este reporte no está permitido para tu rol.')
            ->assertSee('Esto fue lo que entendí')->assertSee('Tipo: Pagos')->assertSee('Estado: Pendiente')
            ->assertSee('Confirmar y generar')->assertDontSee('Total de filas');
        $this->assertSame(0, Bitacora::where('accion', 'REPORTE_GENERADO')->count());
    }

    public function test_un_rango_de_fechas_invalido_de_la_ia_no_genera_y_el_panel_deja_corregirlo(): void
    {
        $this->contratoDePrueba();
        $this->iaFalsa(['tipo' => 'contratos', 'filtros' => ['desde' => '2026-12-31', 'hasta' => '2026-01-01']]);
        $this->actingAs($this->admin());

        $destino = $this->interpretar('contratos de fin de año')->headers->get('Location');

        $this->get($destino)->assertOk()
            ->assertSee('El rango de fechas no es válido.')
            ->assertSee('Esto fue lo que entendí')->assertSee('Tipo: Contratos')->assertSee('Fechas: 31/12/2026 al 01/01/2026')
            ->assertSee('value="2026-12-31"', false)->assertSee('value="2026-01-01"', false)  // cargados en el formulario para corregirlos
            ->assertSee('Confirmar y generar')->assertDontSee('Total de filas');
        $this->assertSame(0, Bitacora::where('accion', 'REPORTE_GENERADO')->count());
    }

    public function test_un_valor_inventado_por_la_ia_no_genera_pero_el_panel_conserva_lo_que_si_era_valido(): void
    {
        $this->iaFalsa(['tipo' => 'propiedades', 'filtros' => ['estado' => 'FLOTANTE'], 'agrupacion' => 'categoria']);
        $this->actingAs($this->admin());

        $destino = $this->interpretar('propiedades flotantes por categoría')->headers->get('Location');

        $this->assertStringContainsString('tipo=propiedades', $destino);
        $this->assertStringContainsString('agrupacion=categoria', $destino);
        $this->assertStringNotContainsString('estado=', $destino);
        $this->get($destino)->assertOk()->assertSee(self::SIN_RECONOCER)->assertSee('Tipo: Propiedades')->assertSee('Agrupado por categoría')
            ->assertDontSee('FLOTANTE')->assertDontSee('Total de filas');
    }

    public function test_el_enlace_ajustar_criterios_lleva_los_criterios_al_formulario_manual(): void
    {
        $this->propiedadesPorCategoria();
        $this->iaFalsa(['tipo' => 'propiedades', 'filtros' => ['estado' => 'DISPONIBLE'], 'agrupacion' => 'categoria']);
        $this->actingAs($this->admin());
        $destino = $this->interpretar('propiedades disponibles por categoría')->headers->get('Location');

        $ajustar = route('reportes.index', ['tipo' => 'propiedades', 'estado' => 'DISPONIBLE', 'agrupacion' => 'categoria']);
        $this->get($destino)->assertSee('Ajustar criterios')->assertSee('href="' . e($ajustar) . '"', false);

        $formulario = $this->get($ajustar)->assertOk()->assertSee('Generar reporte')
            ->assertSee('<option value="propiedades" selected>', false)->assertSee('<option value="DISPONIBLE" data-tipo="propiedades" selected>', false)
            ->assertDontSee('Esto fue lo que entendí')->assertDontSee('Confirmar y generar');
        $this->assertMatchesRegularExpression('/<option value="categoria"[^>]*selected>/', $formulario->getContent());
    }

    public function test_si_el_servicio_de_ia_no_responde_no_aparece_el_panel_de_confirmacion(): void
    {
        $this->reiniciarHttp();
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->actingAs($this->admin());

        $destino = $this->interpretar('contratos')->headers->get('Location');

        $this->assertSame(route('reportes.index'), $destino);
        $this->get($destino)->assertOk()->assertSee(self::SIN_SERVICIO)->assertDontSee('Esto fue lo que entendí')->assertDontSee('Confirmar y generar');
    }

    public function test_la_ia_recibe_solo_la_frase_y_las_listas_permitidas_y_la_llave_va_en_la_cabecera(): void
    {
        $admin = $this->admin(['nombre' => 'Zoila Secreta', 'email' => 'zoila.secreta@correo.com']);
        Cliente::create(['nombre' => 'Mario Confidencial', 'email' => 'mario.confidencial@correo.com', 'telefono' => '77777777']);
        $this->propiedad(['titulo' => 'Casa Reservadisima']);
        $this->iaFalsa(['tipo' => 'propiedades', 'filtros' => ['estado' => 'DISPONIBLE']]);
        $this->actingAs($admin);

        $this->interpretar('propiedades disponibles');

        Http::assertSent(function (Request $p) {
            $cuerpo = $p->body();
            $this->assertStringContainsString('propiedades disponibles', $cuerpo);
            $this->assertStringStartsWith('https://generativelanguage.googleapis.com/', $p->url());
            $this->assertStringContainsString('modelo-de-prueba:generateContent', $p->url());
            $this->assertStringNotContainsString('clave-de-prueba-falsa', $p->url(), 'La llave no va en la URL.');
            $this->assertSame(['clave-de-prueba-falsa'], $p->header('x-goog-api-key'));
            foreach (['Zoila', 'zoila.secreta', 'Mario', 'mario.confidencial', '77777777', 'Reservadisima'] as $privado) {
                $this->assertStringNotContainsString($privado, $cuerpo);
            }
            $this->assertSame(0, $p['generationConfig']['temperature']);
            $this->assertSame('application/json', $p['generationConfig']['responseMimeType']);

            return true;
        });
    }

    public function test_el_resumen_recibe_solo_cifras_agregadas_y_los_agentes_van_anonimos(): void
    {
        $ana = $this->agente(['nombre' => 'Ana Reservada', 'email' => 'ana.reservada@correo.com']);
        $cliente = Cliente::create(['nombre' => 'Carlos Privado', 'email' => 'carlos.privado@correo.com', 'telefono' => '78888888']);
        $propiedad = $this->propiedad(['titulo' => 'Casa Oculta'], $ana);
        Visita::create(['idpropiedad' => $propiedad->idpropiedad, 'idcliente' => $cliente->idcliente, 'fecha' => '2026-12-10', 'hora' => '09:00', 'estado' => 'CANCELADA']);
        Visita::create(['idpropiedad' => $propiedad->idpropiedad, 'idcliente' => $cliente->idcliente, 'fecha' => '2026-12-11', 'hora' => '09:00', 'estado' => 'CANCELADA']);
        $this->iaFalsa(null, 'Hubo 2 visitas canceladas.');
        $this->actingAs($this->admin());

        $this->get(route('reportes.generar', ['tipo' => 'visitas', 'estado' => 'CANCELADA', 'agrupacion' => 'agente', 'con_resumen' => '1', 'origen' => 'ia']))
            ->assertOk()->assertSee('Hubo 2 visitas canceladas.');

        Http::assertSent(function (Request $p) {
            $cuerpo = $p->body();
            $this->assertStringContainsString('Agente 1', $cuerpo);
            $datos = json_decode($p['contents'][0]['parts'][0]['text'], true);
            $this->assertSame(2, $datos['total_filas']);
            $this->assertSame([['grupo' => 'Agente 1', 'cantidad' => 2]], $datos['grupos']);
            foreach (['Ana', 'ana.reservada', 'Carlos', 'carlos.privado', '78888888', 'Casa Oculta'] as $privado) {
                $this->assertStringNotContainsString($privado, $cuerpo);
            }

            return true;
        });
    }

    public function test_si_el_resumen_falla_el_reporte_se_muestra_sin_resumen_con_el_aviso(): void
    {
        $this->contratoDePrueba();
        $this->reiniciarHttp();
        Http::fake(fn () => Http::response('error', 503));
        $this->actingAs($this->admin());

        $this->get(route('reportes.generar', ['tipo' => 'contratos', 'con_resumen' => '1', 'origen' => 'ia']))->assertOk()
            ->assertSee('No se pudo generar el resumen automático.')->assertSee('1,500.00')->assertDontSee('redactado por IA');
    }

    public function test_el_resumen_se_incluye_en_el_pdf_sin_volver_a_llamar_a_la_ia_y_el_excel_no_lo_lleva(): void
    {
        $this->contratoDePrueba();
        $this->iaFalsa(null, 'Resumen de prueba del contrato.');
        $this->actingAs($this->admin());
        $parametros = ['tipo' => 'contratos'];

        $this->get(route('reportes.generar', $parametros + ['con_resumen' => '1', 'origen' => 'ia']))->assertSee('Resumen de prueba del contrato.');
        Http::assertSentCount(1);

        $this->get(route('reportes.exportar', ['formato' => 'pdf'] + $parametros))->assertOk();
        $this->get(route('reportes.exportar', ['formato' => 'xlsx'] + $parametros))->assertOk();
        Http::assertSentCount(1);
        $this->assertSame('Resumen de prueba del contrato.', session('resumen_reporte.' . md5(json_encode($parametros))));
    }

    public function test_el_camino_manual_no_llama_a_la_ia_ni_muestra_resumen(): void
    {
        $this->contratoDePrueba();
        $this->reiniciarHttp();
        Http::fake();
        $this->actingAs($this->admin());

        $this->get(route('reportes.generar', ['tipo' => 'contratos']))->assertOk()->assertSee('1,500.00')
            ->assertDontSee('redactado por IA')->assertDontSee('Confirmar y generar');
        Http::assertNothingSent();
        $this->assertSame('manual', Bitacora::where('accion', 'REPORTE_GENERADO')->first()->detalle['origen']);
    }

    public function test_solicitud_vacia_y_de_301_caracteres_son_rechazadas_y_la_de_300_pasa(): void
    {
        $this->iaFalsa(['tipo' => 'propiedades']);
        $this->actingAs($this->admin());

        $this->interpretar('')->assertSessionHasErrors(['solicitud' => 'Escribe lo que necesitas del reporte.']);
        $this->interpretar('    ')->assertSessionHasErrors(['solicitud' => 'Escribe lo que necesitas del reporte.']);
        $this->post(route('reportes.interpretar'), [])->assertSessionHasErrors(['solicitud' => 'Escribe lo que necesitas del reporte.']);
        $this->post(route('reportes.interpretar'), ['solicitud' => ['x']])->assertSessionHasErrors('solicitud');
        $this->interpretar(str_repeat('a', 301))->assertSessionHasErrors(['solicitud' => 'La solicitud es demasiado larga (máximo 300 caracteres).']);
        Http::assertNothingSent();

        $this->interpretar(str_repeat('a', 300))->assertSessionDoesntHaveErrors();
        Http::assertSentCount(1);
    }

    public function test_sin_llave_configurada_cae_a_los_filtros_manuales(): void
    {
        config(['services.ia.key' => null]);
        $this->contratoDePrueba();
        $this->actingAs($this->admin());

        $this->get(route('reportes.index'))->assertOk()->assertDontSee('Interpretar con IA')->assertSee('Generar reporte');
        $this->interpretar('contratos')->assertSessionHasErrors(['solicitud' => self::SIN_SERVICIO]);
        Http::assertNothingSent();
        $this->get(route('reportes.generar', ['tipo' => 'contratos']))->assertOk()->assertSee('1,500.00');
    }

    public function test_con_llave_la_pantalla_ofrece_describir_el_reporte(): void
    {
        $this->actingAs($this->admin())->get(route('reportes.index'))->assertOk()
            ->assertSee('Describe el reporte que necesitas')->assertSee('Interpretar con IA');
    }

    public function test_el_limite_de_10_solicitudes_por_minuto_funciona(): void
    {
        $this->iaFalsa(['tipo' => null]);
        $this->actingAs($this->admin());

        for ($i = 0; $i < 10; $i++) {
            $this->interpretar('algo')->assertRedirectContains(route('reportes.index'));
        }
        $this->interpretar('algo')->assertStatus(429);
        Http::assertSentCount(10);
    }

    public function test_las_fechas_relativas_las_resuelve_el_sistema_con_la_fecha_de_hoy_en_bolivia(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-12-15 10:00:00', 'America/La_Paz'));
        $this->actingAs($this->admin());
        $esperado = [
            'ultimo_mes' => ['2026-11-15', '2026-12-15'], 'este_mes' => ['2026-12-01', '2026-12-15'],
            'ultimos_7_dias' => ['2026-12-08', '2026-12-15'], 'este_anio' => ['2026-01-01', '2026-12-15'],
        ];

        foreach ($esperado as $periodo => [$desde, $hasta]) {
            // La IA intenta colar fechas propias: el período lo calcula el sistema
            $this->iaFalsa(['tipo' => 'visitas', 'periodo' => $periodo, 'filtros' => ['desde' => '1999-01-01'], 'agrupacion' => 'estado']);
            $destino = $this->interpretar('visitas')->headers->get('Location');
            $this->assertStringContainsString("desde={$desde}", $destino, $periodo);
            $this->assertStringContainsString("hasta={$hasta}", $destino, $periodo);
        }

        $this->iaFalsa(['tipo' => 'visitas', 'filtros' => ['desde' => '2026-10-01', 'hasta' => '2026-10-31']]);
        $destino = $this->interpretar('visitas de octubre')->headers->get('Location');
        $this->assertStringContainsString('desde=2026-10-01', $destino);
        $this->assertStringContainsString('hasta=2026-10-31', $destino);
    }

    public function test_la_zona_y_el_agente_se_validan_contra_la_base_y_si_no_existen_se_descartan(): void
    {
        $ana = $this->agente(['nombre' => 'Ana Agente']);
        $this->agente(['nombre' => 'Beto Agente']);
        $this->agente(['nombre' => 'Beto Segundo']);
        $propiedad = $this->propiedad([], $ana);
        $propiedad->ubicacion->update(['zona' => 'Zona Norte']);
        $this->actingAs($this->admin());

        $this->iaFalsa(['tipo' => 'propiedades', 'filtros' => ['zona' => 'zona norte', 'agente' => 'Ana']]);
        $destino = urldecode($this->interpretar('propiedades de Ana en zona norte')->headers->get('Location'));
        $this->assertStringContainsString('zona=Zona Norte', $destino);
        $this->assertStringContainsString('idagente=' . $ana->idusuario, $destino);

        $this->iaFalsa(['tipo' => 'propiedades', 'filtros' => ['zona' => 'Narnia', 'agente' => 'Beto']]);
        $destino = urldecode($this->interpretar('propiedades')->headers->get('Location'));
        $this->assertStringNotContainsString('zona=', $destino);
        $this->assertStringNotContainsString('idagente=', $destino, 'Dos agentes con ese nombre: no se elige ninguno.');
    }

    public function test_un_agente_confirma_criterios_propios_y_un_cliente_no_puede_interpretar(): void
    {
        $agente = $this->agente();
        $this->iaFalsa(['tipo' => 'clientes', 'agrupacion' => 'estado']);

        $this->actingAs($agente);
        $this->assertStringContainsString('tipo=clientes', $this->interpretar('mis clientes por estado')->headers->get('Location'));

        $this->actingAs($this->cliente())->post(route('reportes.interpretar'), ['solicitud' => 'x'])->assertForbidden();
        auth()->logout();
        $this->post(route('reportes.interpretar'), ['solicitud' => 'x'])->assertRedirect(route('login'));
    }

    // ----------------------------------------------------------------- voz (entrada y salida en el navegador)

    public function test_con_voz_la_frase_se_interpreta_igual_y_genera_directo_sin_boton_de_confirmar_por_voz(): void
    {
        $this->propiedadesPorCategoria();
        $this->iaFalsa(['tipo' => 'propiedades', 'filtros' => ['estado' => 'DISPONIBLE'], 'agrupacion' => 'categoria'], 'Hay 3 propiedades disponibles.');
        $this->actingAs($this->admin());

        $destino = $this->post(route('reportes.interpretar'), ['solicitud' => 'propiedades disponibles por categoría', 'voz' => '1'])->headers->get('Location');
        $this->assertStringStartsWith(route('reportes.generar'), $destino);
        $this->assertStringContainsString('voz=1', $destino);

        $this->get($destino)->assertOk()
            ->assertSee('Leer resultado')->assertSee('data-voz="1"', false)->assertSee('Criterios usados')
            ->assertDontSee('Escuchar y confirmar por voz')->assertDontSee('Confirmar y generar');

        // Sin voz no se marca el modo voz
        $this->assertStringNotContainsString('voz=1', $this->interpretar('propiedades disponibles por categoría')->headers->get('Location'));
    }

    public function test_el_boton_escuchar_y_confirmar_por_voz_solo_esta_en_el_panel_de_cuando_la_ia_no_entendio(): void
    {
        $this->iaFalsa(['tipo' => 'propiedades', 'filtros' => ['estado' => 'FLOTANTE']]);
        $this->actingAs($this->admin());

        $destino = $this->post(route('reportes.interpretar'), ['solicitud' => 'algo raro', 'voz' => '1'])->headers->get('Location');
        $this->assertStringContainsString('voz=1', $destino);

        $this->get($destino)->assertOk()
            ->assertSee('Escuchar y confirmar por voz')->assertSee('data-voz="1"', false)->assertSee('data-confirmable="1"', false)
            ->assertSee('<input type="hidden" name="voz" value="1">', false)->assertSee('Confirmar y generar');

        // Sin ningún criterio aprovechable el panel solo lee el aviso (no hay nada que confirmar por voz)
        $this->iaFalsa(['tipo' => null]);
        $this->get($this->post(route('reportes.interpretar'), ['solicitud' => 'capital', 'voz' => '1'])->headers->get('Location'))
            ->assertSee('data-confirmable="0"', false);
    }

    public function test_el_resultado_trae_el_texto_para_leerlo_en_voz_alta_y_los_botones_de_voz(): void
    {
        $this->propiedadesPorCategoria();
        $this->iaFalsa(null, 'Hay 3 propiedades disponibles, 2 casas y 1 departamento.');
        $this->actingAs($this->admin());

        $respuesta = $this->get(route('reportes.generar', ['tipo' => 'propiedades', 'estado' => 'DISPONIBLE', 'agrupacion' => 'categoria',
            'origen' => 'ia', 'con_resumen' => '1', 'voz' => '1']))->assertOk();

        $respuesta->assertSee('Leer resultado')->assertSee('Comando de voz')->assertSee('id="enlace-pdf"', false)->assertSee('id="enlace-xlsx"', false)
            ->assertSee('data-voz="1"', false)
            ->assertSee('Reporte de propiedades por categoría. Estado: Disponible. Agrupado por categoría. Hay 3 filas. Suma de precios (USD): 220000. Hay 3 propiedades disponibles, 2 casas y 1 departamento.', false);
    }

    public function test_sin_resumen_de_ia_la_voz_lee_los_grupos_y_un_reporte_vacio_dice_que_no_hay_resultados(): void
    {
        $this->propiedadesPorCategoria();
        $this->actingAs($this->admin());

        $this->get(route('reportes.generar', ['tipo' => 'propiedades', 'estado' => 'DISPONIBLE', 'agrupacion' => 'categoria']))->assertOk()
            ->assertSee('Casa: 2, por 150000 dólares. Departamento: 1, por 70000 dólares.', false);
        $this->get(route('reportes.generar', ['tipo' => 'pagos']))->assertOk()
            ->assertSee('El reporte no tiene resultados.')->assertDontSee('Leer resultado');
    }

    public function test_el_modo_voz_manual_tambien_esta_disponible_para_el_asistente_y_el_agente_sin_ia(): void
    {
        config(['services.ia.key' => null]);
        $this->contratoDePrueba();

        $this->actingAs($this->asistente())->get(route('reportes.generar', ['tipo' => 'contratos']))->assertOk()
            ->assertSee('Leer resultado')->assertSee('Comando de voz')
            ->assertSee('Hay 1 fila. Suma de montos (USD): 1500.', false)
            ->assertDontSee('id="btn-microfono"', false); // el dictado a la IA solo aparece si la IA está configurada
    }

    public function test_la_pantalla_de_reportes_trae_el_boton_para_detener_la_voz_en_cualquier_rol_y_con_o_sin_ia(): void
    {
        $this->contratoDePrueba();

        // Con IA (Administrador): formulario y resultado
        $this->actingAs($this->admin());
        $this->get(route('reportes.index'))->assertOk()->assertSee('id="btn-detener-voz"', false)->assertSee('Detener voz');
        $this->get(route('reportes.generar', ['tipo' => 'contratos']))->assertOk()->assertSee('id="btn-detener-voz"', false);

        // Sin IA (Asistente): la lectura del resultado y los comandos también se pueden detener
        config(['services.ia.key' => null]);
        $this->actingAs($this->asistente())->get(route('reportes.generar', ['tipo' => 'contratos']))->assertOk()
            ->assertSee('Leer resultado')->assertSee('id="btn-detener-voz"', false);

        // El botón empieza oculto y solo se muestra mientras el sistema habla o escucha
        $this->get(route('reportes.index'))->assertSee('id="btn-detener-voz"', false)->assertSee('position-fixed d-none', false);
    }

    // ------------------------------------------------- F-005: límites de uso y reintento

    public function test_el_resumen_de_ia_tiene_limite_por_usuario_aunque_se_repita_el_enlace_a_mano(): void
    {
        $this->contratoDePrueba();
        $this->iaFalsa(null, 'Resumen de prueba.');
        $this->actingAs($this->admin());
        $parametros = ['tipo' => 'contratos', 'con_resumen' => '1', 'origen' => 'ia'];

        for ($i = 1; $i <= 10; $i++) {
            $this->get(route('reportes.generar', $parametros))->assertOk()->assertSee('Resumen de prueba.');
        }
        Http::assertSentCount(10);

        // El 11.º en el mismo minuto no llama a la IA: el reporte sale igual, con aviso
        $this->get(route('reportes.generar', $parametros))->assertOk()
            ->assertSee('Pediste muchos resúmenes seguidos')->assertSee('1,500.00')->assertDontSee('Resumen de prueba.');
        Http::assertSentCount(10);
    }

    public function test_generar_y_exportar_tienen_limite_por_minuto_y_pagos_reservas_y_contratos_tambien(): void
    {
        $this->contratoDePrueba();
        $this->actingAs($this->admin());

        for ($i = 0; $i < 30; $i++) {
            $this->get(route('reportes.generar', ['tipo' => 'contratos']))->assertOk();
        }
        $this->get(route('reportes.generar', ['tipo' => 'contratos']))->assertStatus(429);

        for ($i = 0; $i < 15; $i++) {
            $this->get(route('reportes.exportar', ['formato' => 'xlsx', 'tipo' => 'contratos']))->assertOk();
        }
        $this->get(route('reportes.exportar', ['formato' => 'xlsx', 'tipo' => 'contratos']))->assertStatus(429);

        foreach (['pagos.store', 'reservas.store', 'contratos.store'] as $ruta) {
            for ($i = 0; $i < 30; $i++) {
                $this->assertNotSame(429, $this->post(route($ruta), [])->getStatusCode(), "{$ruta} no debe limitar antes del intento 31");
            }
            $this->post(route($ruta), [])->assertStatus(429);
        }
    }

    public function test_ante_un_429_o_503_de_la_ia_se_reintenta_una_vez_y_con_otros_errores_no(): void
    {
        $this->actingAs($this->admin());
        $bueno = ['candidates' => [['content' => ['parts' => [['text' => json_encode(['tipo' => 'propiedades'])]]]]]];

        foreach ([429, 503] as $codigo) {
            $this->reiniciarHttp();
            Http::fake([
                'generativelanguage.googleapis.com/*' => Http::sequence()->push(['error' => 'cuota'], $codigo)->push($bueno, 200),
            ]);
            $this->assertStringStartsWith(route('reportes.generar'), $this->interpretar('propiedades')->headers->get('Location'), "tras un {$codigo} reintenta y funciona");
            Http::assertSentCount(2);
        }

        // Dos fallos seguidos: se rinde (mensaje de servicio caído) sin quedarse reintentando
        $this->reiniciarHttp();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'cuota'], 429)]);
        $this->interpretar('propiedades')->assertSessionHasErrors(['solicitud' => self::SIN_SERVICIO]);
        Http::assertSentCount(2);

        // Un 500 no se reintenta
        $this->reiniciarHttp();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('error', 500)]);
        $this->interpretar('propiedades');
        Http::assertSentCount(1);
    }
    // ------------------------------------------------- F-002: fechas relativas e interpretación del año

    public function test_la_ia_recibe_la_fecha_de_hoy_en_bolivia_y_la_lista_de_periodos_pero_ningun_dato(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-21 23:30:00', 'America/La_Paz'));
        $this->iaFalsa(['tipo' => 'pagos']);
        $this->actingAs($this->admin());

        $this->interpretar('pagos de septiembre');

        Http::assertSent(function (Request $p) {
            $instruccion = $p['systemInstruction']['parts'][0]['text'];
            $this->assertStringContainsString('Hoy es 2026-11-21 (sábado)', $instruccion);
            $this->assertStringContainsString('America/La_Paz', $instruccion);
            $this->assertStringContainsString('el año actual es 2026', $instruccion);
            foreach (['hoy', 'ayer', 'esta_semana', 'semana_pasada', 'mes_pasado', 'ultimos_3_meses', 'ultimos_6_meses', 'este_anio', 'anio_pasado'] as $periodo) {
                $this->assertStringContainsString($periodo, $instruccion);
            }

            return true;
        });
    }

    public function test_una_fecha_con_el_anio_equivocado_se_corrige_al_anio_actual(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-21 12:00:00', 'America/La_Paz'));
        $this->actingAs($this->admin());

        foreach ([
            // [solicitud, tipo, lo que devuelve la IA, lo esperado]
            ['pagos de septiembre', 'pagos', ['2023-09-01', '2023-09-30'], ['2026-09-01', '2026-09-30']],
            ['visitas de ayer', 'visitas', ['2023-10-24', '2023-10-24'], ['2026-10-24', '2026-10-24']],
            ['contratos de diciembre', 'contratos', ['2024-12-01', '2024-12-31'], ['2025-12-01', '2025-12-31']],     // diciembre de este año todavía no llega
            ['visitas de diciembre', 'visitas', ['2024-12-01', '2024-12-31'], ['2026-12-01', '2026-12-31']],         // las visitas sí pueden ser futuras
            ['pagos de septiembre de 2024', 'pagos', ['2024-09-01', '2024-09-30'], ['2024-09-01', '2024-09-30']],   // el usuario dijo el año: se respeta
            ['pagos del 5 al 15 de octubre', 'pagos', ['2026-10-05', '2026-10-15'], ['2026-10-05', '2026-10-15']],   // ya estaba bien
        ] as [$solicitud, $tipo, [$desde, $hasta], [$esperadoDesde, $esperadoHasta]]) {
            $this->iaFalsa(['tipo' => $tipo, 'filtros' => ['desde' => $desde, 'hasta' => $hasta]]);
            $destino = $this->interpretar($solicitud)->headers->get('Location');

            $this->assertStringStartsWith(route('reportes.generar'), $destino, $solicitud);
            $this->assertStringContainsString("desde={$esperadoDesde}", $destino, $solicitud);
            $this->assertStringContainsString("hasta={$esperadoHasta}", $destino, $solicitud);
        }
    }

    public function test_los_periodos_nuevos_los_calcula_el_sistema_con_la_fecha_de_hoy(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-12-15 10:00:00', 'America/La_Paz')); // martes
        $this->actingAs($this->admin());
        $esperado = [
            'hoy' => ['2026-12-15', '2026-12-15'], 'ayer' => ['2026-12-14', '2026-12-14'],
            'esta_semana' => ['2026-12-14', '2026-12-15'], 'semana_pasada' => ['2026-12-07', '2026-12-13'],
            'mes_pasado' => ['2026-11-01', '2026-11-30'], 'ultimos_3_meses' => ['2026-09-15', '2026-12-15'],
            'ultimos_6_meses' => ['2026-06-15', '2026-12-15'], 'anio_pasado' => ['2025-01-01', '2025-12-31'],
        ];

        foreach ($esperado as $periodo => [$desde, $hasta]) {
            $this->iaFalsa(['tipo' => 'pagos', 'periodo' => $periodo]);
            $destino = $this->interpretar('pagos')->headers->get('Location');

            $this->assertStringContainsString("desde={$desde}", $destino, $periodo);
            $this->assertStringContainsString("hasta={$hasta}", $destino, $periodo);
        }
    }

    public function test_si_la_frase_habla_de_un_periodo_y_la_ia_no_devuelve_fechas_no_se_genera_sin_filtro(): void
    {
        $this->actingAs($this->admin());

        foreach (['pagos de los últimos 3 meses', 'ventas del año pasado', 'visitas de esta semana', 'contratos de septiembre', 'pagos de ayer'] as $solicitud) {
            $this->iaFalsa(['tipo' => 'pagos']); // la IA entendió el tipo pero se olvidó del período
            $destino = $this->interpretar($solicitud)->headers->get('Location');

            $this->assertStringNotContainsString('/reportes/generar', $destino, $solicitud);
            $this->get($destino)->assertOk()->assertSee('No pude entender el período de tiempo')->assertSee('Tipo: Pagos')->assertSee('Confirmar y generar');
        }
        $this->assertSame(0, Bitacora::where('accion', 'REPORTE_GENERADO')->count());

        // Sin una expresión de tiempo (o solo "por mes" como agrupación) el reporte sale directo, como antes
        foreach (['pagos', 'pagos agrupados por mes', 'reporte de pagos por estado'] as $solicitud) {
            $this->iaFalsa(['tipo' => 'pagos']);
            $this->assertStringStartsWith(route('reportes.generar'), $this->interpretar($solicitud)->headers->get('Location'), $solicitud);
        }
    }

    private function contratoDePrueba(): Contrato
    {
        return Contrato::create([
            'idpropiedad' => $this->propiedad()->idpropiedad,
            'idcliente' => Cliente::create(['nombre' => 'Cliente Contrato', 'email' => 'cc@test.com'])->idcliente,
            'tipocontrato' => 'Venta', 'monto' => 1500, 'fechainicio' => '2026-12-01',
        ]);
    }
}