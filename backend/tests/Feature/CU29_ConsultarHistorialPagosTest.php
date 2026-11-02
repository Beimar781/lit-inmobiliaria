<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use App\Models\TipoPago;
use Carbon\CarbonImmutable;

/**
 * CU29: Consultar historial de pagos. La fecha de las pruebas se fija en el martes 15 de diciembre de 2026, hora de Bolivia.
 */
class CU29_ConsultarHistorialPagosTest extends SistemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-12-15 10:00:00', 'America/La_Paz'));
    }

    private function contrato(string $cliente = 'Clara Cliente', float $monto = 10000, ?Cliente $existente = null): Contrato
    {
        return Contrato::create([
            'idpropiedad' => $this->propiedad(['titulo' => 'Casa de ' . $cliente])->idpropiedad,
            'idcliente' => ($existente ?? Cliente::create(['nombre' => $cliente, 'email' => uniqid() . '@correo.com']))->idcliente,
            'tipocontrato' => 'Venta', 'monto' => $monto, 'fechainicio' => '2026-12-01', 'estado' => Contrato::VIGENTE,
        ]);
    }

    private function pago(Contrato $contrato, float $monto = 100, string $fecha = '2026-12-10', string $estado = Pago::PAGADO, ?string $comprobante = null, array $extra = []): Pago
    {
        return Pago::create(array_merge([
            'idcontrato' => $contrato->idcontrato, 'monto' => $monto, 'montousd' => $monto, 'moneda' => 'USD',
            'fechapago' => $fecha . ' 10:00:00', 'estado' => $estado, 'metodo' => Pago::EFECTIVO, 'comprobante' => $comprobante,
        ], $extra));
    }

    // ============================================ paso 1: historial de un contrato

    public function test_se_listan_los_pagos_de_un_contrato_con_sus_datos_y_el_total(): void
    {
        $contrato = $this->contrato('Clara Cliente');
        $this->pago($contrato, 300, '2026-12-05', Pago::PAGADO, 'CP-2026-000001');
        $this->pago($contrato, 200.5, '2026-12-08', Pago::PAGADO, 'CP-2026-000002');
        $this->pago($this->contrato('Otro Cliente'), 999, '2026-12-08', Pago::PAGADO, 'CP-2026-000003');

        $this->actingAs($this->asistente())->get(route('pagos.index', ['contrato' => $contrato->idcontrato]))
            ->assertOk()
            ->assertSee('Historial de pagos')
            ->assertSee('05/12/2026')->assertSee('08/12/2026')
            ->assertSee('USD 300.00')->assertSee('USD 200.50')
            ->assertSee('Efectivo')
            ->assertSee('CP-2026-000001')->assertSee('CP-2026-000002')
            ->assertSee('Pagado')
            ->assertSee('2 pagos en el resultado')
            ->assertSee('Total pagado: USD 500.50')
            ->assertDontSee('CP-2026-000003')->assertDontSee('USD 999.00');
    }

    public function test_el_administrador_tambien_consulta_el_historial(): void
    {
        $this->pago($this->contrato(), 100, '2026-12-05', Pago::PAGADO, 'CP-2026-000001');

        $this->actingAs($this->admin())->get(route('pagos.index'))->assertOk()->assertSee('CP-2026-000001');
    }

    public function test_sin_filtros_se_listan_todos_los_pagos_del_mas_reciente_al_mas_antiguo(): void
    {
        $contrato = $this->contrato();
        $this->pago($contrato, 100, '2026-12-01', Pago::PAGADO, 'CP-2026-000001');
        $this->pago($contrato, 100, '2026-12-12', Pago::PAGADO, 'CP-2026-000002');
        $this->pago($contrato, 100, '2026-12-06', Pago::PAGADO, 'CP-2026-000003');

        $html = $this->actingAs($this->asistente())->get(route('pagos.index'))->getContent();

        $this->assertTrue(strpos($html, 'CP-2026-000002') < strpos($html, 'CP-2026-000003'));
        $this->assertTrue(strpos($html, 'CP-2026-000003') < strpos($html, 'CP-2026-000001'));
    }

    // ============================================ paso 2: historial de un cliente

    public function test_el_historial_de_un_cliente_incluye_los_pagos_de_todos_sus_contratos(): void
    {
        $clara = Cliente::create(['nombre' => 'Clara Cliente', 'email' => 'clara@correo.com']);
        $primero = $this->contrato('Clara Cliente', 5000, $clara);
        $segundo = $this->contrato('Clara Cliente', 5000, $clara);
        $this->pago($primero, 100, '2026-12-05', Pago::PAGADO, 'CP-2026-000001');
        $this->pago($segundo, 250, '2026-12-06', Pago::PAGADO, 'CP-2026-000002');
        $this->pago($this->contrato('Otro Cliente'), 999, '2026-12-06', Pago::PAGADO, 'CP-2026-000003');

        $this->actingAs($this->asistente())->get(route('pagos.index', ['cliente' => $clara->idcliente]))
            ->assertOk()
            ->assertSee('CP-2026-000001')->assertSee('CP-2026-000002')->assertDontSee('CP-2026-000003')
            ->assertSee('Total pagado: USD 350.00');
    }

    // ============================================ paso 3: rango de fechas

    public function test_un_rango_de_fechas_valido_muestra_solo_los_pagos_de_ese_rango_incluyendo_los_extremos(): void
    {
        $contrato = $this->contrato();
        foreach (['2026-12-01', '2026-12-05', '2026-12-10', '2026-12-12'] as $i => $fecha) {
            $this->pago($contrato, 100, $fecha, Pago::PAGADO, 'CP-2026-00000' . ($i + 1));
        }

        $this->actingAs($this->asistente())->get(route('pagos.index', ['desde' => '2026-12-05', 'hasta' => '2026-12-10']))
            ->assertOk()
            ->assertSee('CP-2026-000002')->assertSee('CP-2026-000003')
            ->assertDontSee('CP-2026-000001')->assertDontSee('CP-2026-000004')
            ->assertSee('Total pagado: USD 200.00');
    }

    public function test_solo_desde_o_solo_hasta_tambien_filtran(): void
    {
        $contrato = $this->contrato();
        $this->pago($contrato, 100, '2026-12-01', Pago::PAGADO, 'CP-2026-000001');
        $this->pago($contrato, 100, '2026-12-12', Pago::PAGADO, 'CP-2026-000002');
        $asistente = $this->asistente();

        $this->actingAs($asistente)->get(route('pagos.index', ['desde' => '2026-12-10']))->assertSee('CP-2026-000002')->assertDontSee('CP-2026-000001');
        $this->actingAs($asistente)->get(route('pagos.index', ['hasta' => '2026-12-10']))->assertSee('CP-2026-000001')->assertDontSee('CP-2026-000002');
        $this->actingAs($asistente)->get(route('pagos.index', ['desde' => '2026-12-12', 'hasta' => '2026-12-12']))->assertSee('CP-2026-000002')->assertDontSee('CP-2026-000001');
    }

    // ============================================ paso 4: estado

    public function test_se_filtra_por_estado_del_pago(): void
    {
        $contrato = $this->contrato();
        $this->pago($contrato, 100, '2026-12-05', Pago::PAGADO, 'CP-2026-000001');
        $this->pago($contrato, 111, '2026-12-06', Pago::PENDIENTE);
        $this->pago($contrato, 122, '2026-12-07', Pago::ANULADO);
        $asistente = $this->asistente();

        $this->actingAs($asistente)->get(route('pagos.index', ['estado' => 'PENDIENTE']))
            ->assertSee('USD 111.00')->assertDontSee('USD 100.00')->assertDontSee('USD 122.00')
            ->assertSee('1 pago en el resultado')->assertSee('Total pagado: USD 0.00');
        $this->actingAs($asistente)->get(route('pagos.index', ['estado' => 'ANULADO']))
            ->assertSee('USD 122.00')->assertDontSee('USD 111.00');
        $this->actingAs($asistente)->get(route('pagos.index', ['estado' => 'PAGADO']))
            ->assertSee('USD 100.00')->assertDontSee('USD 111.00')->assertSee('Total pagado: USD 100.00');
    }

    public function test_el_total_suma_solo_los_pagados_aunque_se_vean_los_pendientes_y_anulados(): void
    {
        $contrato = $this->contrato();
        $this->pago($contrato, 100, '2026-12-05', Pago::PAGADO);
        $this->pago($contrato, 40, '2026-12-06', Pago::PENDIENTE);
        $this->pago($contrato, 9, '2026-12-07', Pago::ANULADO);

        $this->actingAs($this->asistente())->get(route('pagos.index'))
            ->assertSee('3 pagos en el resultado')->assertSee('Total pagado: USD 100.00');
    }

    public function test_el_total_usa_el_equivalente_en_dolares_de_los_pagos_en_bolivianos(): void
    {
        $contrato = $this->contrato();
        $this->pago($contrato, 696, '2026-12-05', Pago::PAGADO, 'CP-2026-000001', ['moneda' => 'BOB', 'tipocambio' => 6.96, 'montousd' => 100]);
        $this->pago($contrato, 50, '2026-12-06', Pago::PAGADO, 'CP-2026-000002');

        $this->actingAs($this->asistente())->get(route('pagos.index'))
            ->assertSee('BOB 696.00')->assertSee('USD 50.00')
            ->assertSee('Total pagado: USD 150.00');
    }

    // ============================================ paso 5: sin resultados

    public function test_un_filtro_sin_resultados_muestra_total_cero_y_el_mensaje(): void
    {
        $contrato = $this->contrato();
        $this->pago($contrato, 100, '2026-12-05', Pago::PAGADO, 'CP-2026-000001');

        $this->actingAs($this->asistente())->get(route('pagos.index', ['desde' => '2027-01-01']))
            ->assertOk()
            ->assertSee('Sin pagos registrados')
            ->assertSee('0 pagos en el resultado')
            ->assertSee('Total pagado: USD 0.00')
            ->assertDontSee('CP-2026-000001');
    }

    public function test_sin_ningun_pago_en_el_sistema_se_muestra_el_mensaje(): void
    {
        $this->actingAs($this->asistente())->get(route('pagos.index'))
            ->assertOk()->assertSee('Sin pagos registrados')->assertSee('Total pagado: USD 0.00');
    }

    // ============================================ paso 6: «desde» posterior a «hasta»

    public function test_un_desde_posterior_al_hasta_muestra_el_error_y_no_filtra_por_fechas(): void
    {
        $contrato = $this->contrato();
        $this->pago($contrato, 100, '2026-12-01', Pago::PAGADO, 'CP-2026-000001');
        $this->pago($contrato, 100, '2026-12-12', Pago::PAGADO, 'CP-2026-000002');

        $this->actingAs($this->asistente())->get(route('pagos.index', ['desde' => '2026-12-10', 'hasta' => '2026-12-05']))
            ->assertOk()
            ->assertSee('La fecha «desde» no puede ser posterior a la fecha «hasta»: no se filtró por fechas.')
            ->assertSee('CP-2026-000001')->assertSee('CP-2026-000002')
            ->assertSee('Total pagado: USD 200.00');
    }

    public function test_el_error_de_fechas_conserva_los_otros_filtros(): void
    {
        $contrato = $this->contrato();
        $this->pago($contrato, 100, '2026-12-01', Pago::PAGADO, 'CP-2026-000001');
        $this->pago($contrato, 77, '2026-12-12', Pago::PENDIENTE);

        $this->actingAs($this->asistente())->get(route('pagos.index', ['desde' => '2026-12-10', 'hasta' => '2026-12-05', 'estado' => 'PENDIENTE']))
            ->assertSee('no puede ser posterior')->assertSee('USD 77.00')->assertDontSee('CP-2026-000001');
    }

    public function test_una_fecha_con_formato_invalido_avisa_y_no_filtra(): void
    {
        $this->pago($this->contrato(), 100, '2026-12-01', Pago::PAGADO, 'CP-2026-000001');
        $asistente = $this->asistente();

        $this->actingAs($asistente)->get(route('pagos.index', ['desde' => '10/12/2026']))
            ->assertOk()->assertSee('La fecha «desde» no es válida')->assertSee('CP-2026-000001');
        $this->actingAs($asistente)->get(route('pagos.index', ['hasta' => '2026-13-45']))
            ->assertOk()->assertSee('La fecha «hasta» no es válida')->assertSee('CP-2026-000001');
    }

    // ============================================ contrato o cliente que no existe

    public function test_un_contrato_o_un_cliente_que_no_existe_avisa_y_no_muestra_pagos(): void
    {
        $this->pago($this->contrato(), 100, '2026-12-01', Pago::PAGADO, 'CP-2026-000001');
        $asistente = $this->asistente();

        $this->actingAs($asistente)->get(route('pagos.index', ['contrato' => 9999]))
            ->assertOk()->assertSee('El contrato elegido no existe.')->assertSee('Sin pagos registrados')->assertDontSee('CP-2026-000001');
        $this->actingAs($asistente)->get(route('pagos.index', ['cliente' => 9999]))
            ->assertOk()->assertSee('El cliente elegido no existe.')->assertSee('Sin pagos registrados');
        $this->actingAs($asistente)->get('/pagos?contrato=abc')
            ->assertOk()->assertSee('El contrato elegido no es válido.')->assertDontSee('CP-2026-000001');
    }

    public function test_un_estado_invalido_avisa_y_muestra_todos(): void
    {
        $this->pago($this->contrato(), 100, '2026-12-01', Pago::PAGADO, 'CP-2026-000001');

        $this->actingAs($this->asistente())->get(route('pagos.index', ['estado' => 'INVENTADO']))
            ->assertOk()->assertSee('El estado elegido no es válido')->assertSee('CP-2026-000001');
    }

    public function test_los_parametros_mal_formados_no_dan_error_500(): void
    {
        $asistente = $this->asistente();

        $this->actingAs($asistente)->get('/pagos?contrato[]=1&cliente[]=2&estado[]=x&desde[]=a&hasta[]=b')->assertOk();
    }

    // ============================================ paginación

    public function test_el_listado_se_pagina_y_el_total_es_de_todo_el_resultado(): void
    {
        $contrato = $this->contrato('Clara Cliente', 100000);
        foreach (range(1, 12) as $i) {
            $this->pago($contrato, 10, '2026-12-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), Pago::PAGADO, sprintf('CP-2026-%06d', $i));
        }
        $asistente = $this->asistente();

        $primera = $this->actingAs($asistente)->get(route('pagos.index', ['contrato' => $contrato->idcontrato]))
            ->assertSee('12 pagos en el resultado')->assertSee('Total pagado: USD 120.00')
            ->assertSee('CP-2026-000012')->assertDontSee('CP-2026-000001<');
        $this->assertSame(10, substr_count($primera->getContent(), 'class="btn btn-sm btn-outline-primary">Ver</a>'));

        $this->actingAs($asistente)->get(route('pagos.index', ['contrato' => $contrato->idcontrato, 'page' => 2]))
            ->assertSee('Total pagado: USD 120.00')
            ->assertSee('CP-2026-000001')->assertSee('CP-2026-000002')->assertDontSee('CP-2026-000012');
    }

    public function test_los_filtros_se_conservan_al_cambiar_de_pagina(): void
    {
        $contrato = $this->contrato('Clara Cliente', 100000);
        foreach (range(1, 12) as $i) {
            $this->pago($contrato, 10, '2026-12-10', Pago::PAGADO);
        }

        $this->actingAs($this->asistente())->get(route('pagos.index', ['contrato' => $contrato->idcontrato, 'estado' => 'PAGADO']))
            ->assertSee('contrato=' . $contrato->idcontrato . '&amp;estado=PAGADO&amp;page=2', false);
    }

    // ============================================ paso 7: detalle y comprobante

    public function test_el_detalle_del_pago_muestra_sus_datos_y_el_enlace_al_comprobante(): void
    {
        $contrato = $this->contrato('Clara Cliente', 1000);
        $this->actingAs($this->asistente(['nombre' => 'Sara Asistente']))->post(route('pagos.store'), [
            'idcontrato' => $contrato->idcontrato, 'monto' => '696', 'moneda' => 'BOB', 'tipocambio' => '6.96', 'fechapago' => '2026-12-15', 'metodo' => 'EFECTIVO',
        ])->assertSessionHasNoErrors();
        $pago = Pago::firstOrFail();

        $this->get(route('pagos.show', $pago))
            ->assertOk()
            ->assertSee('Pago N.º ' . $pago->idpago)
            ->assertSee('Clara Cliente')->assertSee($contrato->propiedad->codigo)
            ->assertSee('BOB 696.00')->assertSee('6.96 Bs por USD')->assertSee('USD 100.00')
            ->assertSee('Efectivo')->assertSee('Sara Asistente')->assertSee('CP-2026-000001')
            ->assertSee('Ver comprobante')
            ->assertSee(route('pagos.comprobante', $pago), false);
    }

    public function test_volver_a_emitir_el_comprobante_desde_el_detalle_conserva_el_numero(): void
    {
        $contrato = $this->contrato();
        $asistente = $this->asistente();
        $this->actingAs($asistente)->post(route('pagos.store'), ['idcontrato' => $contrato->idcontrato, 'monto' => '100', 'moneda' => 'USD', 'fechapago' => '2026-12-15', 'metodo' => 'EFECTIVO']);
        $pago = Pago::firstOrFail();
        $numero = $pago->comprobante;

        $this->get(route('pagos.show', $pago))->assertSee($numero);
        $this->get(route('pagos.comprobante', $pago))->assertOk()->assertSee($numero);

        $this->assertSame($numero, $pago->fresh()->comprobante);
        $this->assertSame(1, Pago::count());
    }

    public function test_un_pago_pendiente_o_anulado_no_ofrece_comprobante(): void
    {
        $contrato = $this->contrato();
        $asistente = $this->asistente();

        foreach ([Pago::PENDIENTE => 'pendiente', Pago::ANULADO => 'anulado'] as $estado => $texto) {
            $pago = $this->pago($contrato, 50, '2026-12-10', $estado);

            $this->actingAs($asistente)->get(route('pagos.show', $pago))
                ->assertOk()
                ->assertSee("Un pago {$texto} no tiene comprobante.")
                ->assertSee('Sin comprobante')
                ->assertDontSee('Ver comprobante');
        }
    }

    public function test_el_detalle_de_un_pago_inexistente_da_404(): void
    {
        $this->actingAs($this->asistente())->get('/pagos/9999')->assertNotFound();
    }

    public function test_el_listado_enlaza_a_cada_detalle(): void
    {
        $pago = $this->pago($this->contrato(), 100, '2026-12-10', Pago::PAGADO, 'CP-2026-000001');

        $this->actingAs($this->asistente())->get(route('pagos.index'))->assertSee(route('pagos.show', $pago), false);
    }

    // ============================================ permisos y lectura

    public function test_el_agente_recibe_acceso_denegado(): void
    {
        $pago = $this->pago($this->contrato(), 100, '2026-12-10', Pago::PAGADO, 'CP-2026-000001');
        $agente = $this->agente();

        $this->actingAs($agente)->get(route('pagos.index'))->assertForbidden();
        $this->actingAs($agente)->get(route('pagos.show', $pago))->assertForbidden();
    }

    public function test_el_cliente_y_el_invitado_no_entran(): void
    {
        $pago = $this->pago($this->contrato(), 100, '2026-12-10', Pago::PAGADO);

        $this->get(route('pagos.index'))->assertRedirect(route('login'));
        $this->get(route('pagos.show', $pago))->assertRedirect(route('login'));

        $this->actingAs($this->cliente())->get(route('pagos.index'))->assertForbidden();
        $this->actingAs($this->cliente())->get(route('pagos.show', $pago))->assertForbidden();
    }

    public function test_consultar_el_historial_no_modifica_ningun_dato(): void
    {
        $contrato = $this->contrato();
        $pagado = $this->pago($contrato, 100, '2026-12-10', Pago::PAGADO);       // sin número de comprobante todavía
        $this->pago($contrato, 50, '2026-12-11', Pago::PENDIENTE);
        $antes = Pago::orderBy('idpago')->get()->toArray();
        $asistente = $this->asistente();

        $this->actingAs($asistente)->get(route('pagos.index'));
        $this->actingAs($asistente)->get(route('pagos.index', ['estado' => 'PAGADO', 'desde' => '2026-12-01']));
        $this->actingAs($asistente)->get(route('pagos.show', $pagado));

        $this->assertSame($antes, Pago::orderBy('idpago')->get()->toArray(), 'ni siquiera el comprobante se genera al consultar');
        $this->assertSame(0, Bitacora::where('modulo', 'Pagos')->count());
    }

    // ============================================ panel y enlaces

    public function test_el_panel_ofrece_el_historial_de_pagos_al_asistente_y_al_administrador_pero_no_para_el_agente(): void
    {
        foreach ([$this->asistente(), $this->admin()] as $usuario) {
            $this->actingAs($usuario)->get(route('panel'))
                ->assertSee('Historial de pagos')
                ->assertSee(route('pagos.index'), false);
        }
        $this->actingAs($this->agente())->get(route('panel'))->assertDontSee('Historial de pagos');
    }

    public function test_el_filtro_del_formulario_ofrece_los_contratos_y_solo_los_clientes_con_contratos(): void
    {
        $contrato = $this->contrato('Clara Cliente');
        Cliente::create(['nombre' => 'Sin contrato', 'email' => 'sin@correo.com']);

        $this->actingAs($this->asistente())->get(route('pagos.index'))
            ->assertSee('N.º ' . $contrato->idcontrato . ' · Clara Cliente')
            ->assertSee('Todos los clientes')
            ->assertDontSee('Sin contrato');
    }
}
