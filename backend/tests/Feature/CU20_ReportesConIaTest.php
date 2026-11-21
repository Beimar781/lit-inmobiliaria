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

    public function test_paso_1_el_administrador_escribe_propiedades_disponibles_por_categoria_ve_los_criterios_y_al_confirmar_el_reporte_con_resumen(): void
    {
        $this->propiedadesPorCategoria();
        $this->iaFalsa(['tipo' => 'propiedades', 'periodo' => null, 'filtros' => ['estado' => 'DISPONIBLE'], 'agrupacion' => 'categoria'],
            'Hay 3 propiedades disponibles, 2 casas y 1 departamento.');
        $this->actingAs($this->admin());

        $respuesta = $this->interpretar('propiedades disponibles por categoría');
        $destino = $respuesta->headers->get('Location');
        $this->assertStringContainsString('tipo=propiedades', $destino);
        $this->assertStringContainsString('estado=DISPONIBLE', $destino);
        $this->assertStringContainsString('agrupacion=categoria', $destino);
        $this->assertStringContainsString('origen=ia', $destino);
        Http::assertSentCount(1);

        // Criterios interpretados, como el formulario manual ya lleno, para confirmar
        $this->get($destino)->assertOk()
            ->assertSee('Esto fue lo que entendí')->assertSee('Confirmar y generar')
            ->assertSee('<option value="propiedades" selected>', false)
            ->assertSee('<option value="DISPONIBLE" data-tipo="propiedades" selected>', false)
            ->assertSeeHtml('value="categoria"')
            ->assertDontSee('220,000.00'); // todavía no se consultó nada
        $this->assertMatchesRegularExpression('/<option value="categoria"[^>]*selected>/', $this->get($destino)->getContent());

        $confirmar = $this->get(route('reportes.generar', ['tipo' => 'propiedades', 'estado' => 'DISPONIBLE', 'agrupacion' => 'categoria',
            'origen' => 'ia', 'con_resumen' => '1', 'solicitud' => 'propiedades disponibles por categoría']))->assertOk();
        $confirmar->assertSee('220,000.00')->assertSee('Resumen')->assertSee('redactado por IA')
            ->assertSee('Hay 3 propiedades disponibles, 2 casas y 1 departamento.')->assertSee('Exportar PDF');
        $this->assertMatchesRegularExpression('/<td[^>]*>Casa<\/td>\s*<td[^>]*>2<\/td>/', $confirmar->getContent());
        Http::assertSentCount(2);

        $bitacora = Bitacora::where('accion', 'REPORTE_GENERADO')->firstOrFail();
        $this->assertSame('ia', $bitacora->detalle['origen']);
        $this->assertSame('propiedades disponibles por categoría', $bitacora->detalle['solicitud']);
    }

    public function test_paso_8_una_solicitud_que_no_corresponde_a_ningun_reporte_pide_reformular_y_no_consulta_la_base(): void
    {
        $this->propiedadesPorCategoria();
        $this->iaFalsa(['tipo' => null]);
        $this->actingAs($this->admin());

        DB::enableQueryLog();
        $this->interpretar('cuál es la capital de Bolivia')
            ->assertRedirect(route('reportes.index'))->assertSessionHasErrors(['solicitud' => self::SIN_RECONOCER]);
        $consultadas = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => preg_match('/from ["`]?(propiedad|contrato|pago|visita|cliente)["`]?/i', $q));
        DB::disableQueryLog();

        $this->assertCount(0, $consultadas, 'No debe consultar los datos del reporte.');
        $this->assertSame(0, Bitacora::where('accion', 'REPORTE_GENERADO')->count());
        $this->followingRedirects()->post(route('reportes.interpretar'), ['solicitud' => 'cuál es la capital de Bolivia'])
            ->assertSee(self::SIN_RECONOCER)->assertSee('cuál es la capital de Bolivia');
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

    public function test_los_criterios_para_un_rol_no_permitido_o_inventados_se_descartan(): void
    {
        $this->actingAs($this->agente());

        foreach ([
            ['tipo' => 'pagos'],                                                    // el Agente no ve pagos
            ['tipo' => 'planetas'],                                                 // tipo inventado
            ['tipo' => 'propiedades', 'filtros' => ['estado' => 'FLOTANTE']],       // estado inventado
            ['tipo' => 'propiedades', 'agrupacion' => 'agente'],                    // el Agente no agrupa por agente
            ['tipo' => 'visitas', 'agrupacion' => 'categoria'],                     // categoría solo es de propiedades
            ['tipo' => 'propiedades', 'filtros' => ['desde' => '2026-12-31', 'hasta' => '2026-01-01']], // rango invertido
            ['tipo' => 'propiedades', 'filtros' => ['desde' => 'ayer']],            // fecha mal formada
            'esto no es un objeto json',
            ['tipo' => ['propiedades']],
        ] as $criterios) {
            $this->iaFalsa($criterios);
            $this->interpretar('lo que sea')->assertSessionHasErrors(['solicitud' => is_string($criterios) ? self::SIN_SERVICIO : self::SIN_RECONOCER]);
        }
        $this->assertSame(0, Bitacora::where('accion', 'REPORTE_GENERADO')->count());
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
            $this->interpretar('algo')->assertRedirect(route('reportes.index'));
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

    private function contratoDePrueba(): Contrato
    {
        return Contrato::create([
            'idpropiedad' => $this->propiedad()->idpropiedad,
            'idcliente' => Cliente::create(['nombre' => 'Cliente Contrato', 'email' => 'cc@test.com'])->idcliente,
            'tipocontrato' => 'Venta', 'monto' => 1500, 'fechainicio' => '2026-12-01',
        ]);
    }
}