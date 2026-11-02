<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use App\Models\TipoPago;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

/**
 * CU28: Registrar pago. Los contratos están en dólares; el pago puede ser en dólares o en bolivianos con el tipo de cambio del día.
 * La fecha de las pruebas se fija en el martes 15 de diciembre de 2026, hora de Bolivia.
 */
class CU28_RegistrarPagoTest extends SistemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-12-15 10:00:00', 'America/La_Paz'));
    }

    private function contrato(float $monto = 1000, string $estado = Contrato::VIGENTE, string $cliente = 'Clara Cliente'): Contrato
    {
        return Contrato::create([
            'idpropiedad' => $this->propiedad(['titulo' => 'Casa ' . uniqid()])->idpropiedad,
            'idcliente' => Cliente::create(['nombre' => $cliente, 'email' => uniqid() . '@correo.com'])->idcliente,
            'tipocontrato' => 'Venta', 'monto' => $monto, 'fechainicio' => '2026-12-01', 'estado' => $estado,
        ]);
    }

    private function pagar(Usuario $actor, Contrato $contrato, array $cambios = [])
    {
        return $this->actingAs($actor)->post(route('pagos.store'), array_merge([
            'idcontrato' => $contrato->idcontrato,
            'monto' => '300',
            'moneda' => 'USD',
            'fechapago' => '2026-12-15',
            'metodo' => 'EFECTIVO',
        ], $cambios));
    }

    // ============================================ paso 1: pago en dólares

    public function test_se_registra_un_pago_en_dolares_sin_tipo_de_cambio_y_se_genera_su_comprobante(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato(1000);

        $respuesta = $this->pagar($asistente, $contrato);

        $pago = Pago::firstOrFail();
        $respuesta->assertRedirect(route('pagos.comprobante', $pago))
            ->assertSessionHas('status', 'Pago registrado. Saldo restante del contrato: USD 700.00.');
        $this->assertSame($contrato->idcontrato, $pago->idcontrato);
        $this->assertSame(Pago::PAGADO, $pago->estado);
        $this->assertSame(Pago::EFECTIVO, $pago->metodo);
        $this->assertSame('USD', $pago->moneda);
        $this->assertSame('300.00', (string) $pago->monto);
        $this->assertSame('300.00', (string) $pago->montousd);
        $this->assertNull($pago->tipocambio);
        $this->assertSame('CP-2026-000001', $pago->comprobante);
        $this->assertSame('2026-12-15 10:00:00', $pago->fechapago->format('Y-m-d H:i:s'));
        $this->assertSame($asistente->idusuario, $pago->idusuario);
        $this->assertNull($pago->tipopago, 'en dólares no se guarda tipo de cambio');
        $this->assertSame(300.0, $contrato->totalPagadoUsd());
        $this->assertSame(700.0, $contrato->saldoUsd());
    }

    public function test_el_administrador_tambien_registra_pagos(): void
    {
        $this->pagar($this->admin(), $this->contrato())->assertSessionHasNoErrors();

        $this->assertSame(1, Pago::count());
    }

    public function test_el_pago_queda_en_la_bitacora(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato();
        $this->pagar($asistente, $contrato);

        $registro = Bitacora::where('accion', 'PAGO_REGISTRADO')->firstOrFail();
        $this->assertSame('Pagos', $registro->modulo);
        $this->assertSame($asistente->idusuario, $registro->idusuario);
        $this->assertSame('CP-2026-000001', $registro->detalle['comprobante']);
        $this->assertSame('300.00', $registro->detalle['montousd']);
        $this->assertSame($contrato->idcontrato, $registro->detalle['idcontrato']);
        $this->assertSame(1, Bitacora::where('accion', 'COMPROBANTE_GENERADO')->count());
    }

    // ============================================ paso 2: pago en bolivianos con tipo de cambio

    public function test_un_pago_en_bolivianos_guarda_el_tipo_de_cambio_y_su_equivalente_en_dolares(): void
    {
        $contrato = $this->contrato(1000);

        $this->pagar($this->asistente(), $contrato, ['monto' => '696', 'moneda' => 'BOB', 'tipocambio' => '6.96'])->assertSessionHasNoErrors();

        $pago = Pago::firstOrFail();
        $this->assertSame('BOB', $pago->moneda);
        $this->assertSame('696.00', (string) $pago->monto);
        $this->assertSame('6.9600', (string) $pago->tipocambio);
        $this->assertSame('100.00', (string) $pago->montousd);
        $this->assertSame('CP-2026-000001', $pago->comprobante);
        $this->assertSame('6.9600', (string) $pago->tipopago->cambiodemonedalocal);
        $this->assertSame('2026-12-15', $pago->tipopago->fecha->format('Y-m-d'));
        $this->assertSame(100.0, $contrato->totalPagadoUsd(), 'el saldo se descuenta en dólares, no en bolivianos');
        $this->assertSame(900.0, $contrato->saldoUsd());
    }

    public function test_el_tipo_de_cambio_se_guarda_con_la_fecha_del_pago_aunque_se_registre_otro_dia(): void
    {
        $contrato = $this->contrato(1000);

        // Hoy es 15/12 pero el pago se hizo el 10/12: el tipo de cambio escrito es el del 10/12.
        $this->pagar($this->asistente(), $contrato, ['monto' => '696', 'moneda' => 'BOB', 'tipocambio' => '6.96', 'fechapago' => '2026-12-10'])
            ->assertSessionHasNoErrors();

        $pago = Pago::firstOrFail();
        $this->assertSame('2026-12-10', $pago->fechapago->format('Y-m-d'));
        $this->assertSame('2026-12-10', $pago->tipopago->fecha->format('Y-m-d'));
    }

    public function test_cada_pago_en_bolivianos_conserva_su_propio_tipo_de_cambio_aunque_el_dolar_cambie(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato(1000);

        $this->pagar($asistente, $contrato, ['monto' => '696', 'moneda' => 'BOB', 'tipocambio' => '6.96', 'fechapago' => '2026-12-10']);
        $this->pagar($asistente, $contrato, ['monto' => '700', 'moneda' => 'BOB', 'tipocambio' => '7.00', 'fechapago' => '2026-12-15']);

        [$primero, $segundo] = Pago::orderBy('idpago')->get()->all();
        $this->assertSame('100.00', (string) $primero->montousd);
        $this->assertSame('100.00', (string) $segundo->montousd);
        $this->assertSame('6.9600', (string) $primero->tipopago->cambiodemonedalocal);
        $this->assertSame('7.0000', (string) $segundo->tipopago->cambiodemonedalocal);
        $this->assertSame('2026-12-10', $primero->tipopago->fecha->format('Y-m-d'));
        $this->assertSame('2026-12-15', $segundo->tipopago->fecha->format('Y-m-d'));
        $this->assertSame(800.0, $contrato->saldoUsd(), 'el contrato sigue en dólares: no se recalcula con el dólar de hoy');
    }

    public function test_el_equivalente_en_dolares_se_redondea_a_dos_decimales(): void
    {
        $contrato = $this->contrato(1000);

        $this->pagar($this->asistente(), $contrato, ['monto' => '1000', 'moneda' => 'BOB', 'tipocambio' => '6.96']);

        $this->assertSame('143.68', (string) Pago::firstOrFail()->montousd);
    }

    public function test_el_tipo_de_cambio_se_ignora_si_el_pago_es_en_dolares(): void
    {
        $this->pagar($this->asistente(), $this->contrato(), ['moneda' => 'USD', 'tipocambio' => '6.96'])->assertSessionHasNoErrors();

        $this->assertNull(Pago::firstOrFail()->tipocambio);
        $this->assertSame(0, TipoPago::count());
    }

    // ============================================ paso 3: monto

    public function test_un_monto_igual_a_cero_o_no_valido_se_rechaza(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato();

        $this->pagar($asistente, $contrato, ['monto' => '0'])->assertSessionHasErrors(['monto' => 'El monto debe ser mayor a cero.']);
        $this->pagar($asistente, $contrato, ['monto' => '-10'])->assertSessionHasErrors('monto');
        $this->pagar($asistente, $contrato, ['monto' => 'abc'])->assertSessionHasErrors(['monto' => 'El monto debe ser un número.']);
        $this->pagar($asistente, $contrato, ['monto' => '10.999'])->assertSessionHasErrors('monto');
        $this->pagar($asistente, $contrato, ['monto' => ''])->assertSessionHasErrors(['monto' => 'Indica el monto del pago.']);

        $this->assertSame(0, Pago::count());
    }

    public function test_un_monto_en_bolivianos_cuyo_equivalente_es_cero_se_rechaza(): void
    {
        $this->pagar($this->asistente(), $this->contrato(), ['monto' => '0.01', 'moneda' => 'BOB', 'tipocambio' => '6.96'])
            ->assertSessionHasErrors('monto');

        $this->assertSame(0, Pago::count());
    }

    // ============================================ paso 4: bolivianos sin tipo de cambio

    public function test_un_pago_en_bolivianos_sin_tipo_de_cambio_se_rechaza(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato();

        $this->pagar($asistente, $contrato, ['moneda' => 'BOB', 'tipocambio' => ''])
            ->assertSessionHasErrors(['tipocambio' => 'Indica el tipo de cambio oficial del día para el pago en bolivianos.']);
        $this->pagar($asistente, $contrato, ['moneda' => 'BOB'])->assertSessionHasErrors('tipocambio');
        $this->pagar($asistente, $contrato, ['moneda' => 'BOB', 'tipocambio' => '0'])->assertSessionHasErrors('tipocambio');
        $this->pagar($asistente, $contrato, ['moneda' => 'BOB', 'tipocambio' => 'abc'])->assertSessionHasErrors('tipocambio');
        $this->pagar($asistente, $contrato, ['moneda' => 'BOB', 'tipocambio' => '6.123456'])->assertSessionHasErrors('tipocambio');

        $this->assertSame(0, Pago::count());
    }

    // ============================================ paso 5: contrato no vigente

    public function test_no_se_puede_pagar_un_contrato_que_no_esta_vigente(): void
    {
        $asistente = $this->asistente();

        foreach ([Contrato::FINALIZADO => 'finalizado', Contrato::RESCINDIDO => 'rescindido'] as $estado => $texto) {
            $contrato = $this->contrato(1000, $estado);

            $this->pagar($asistente, $contrato)
                ->assertSessionHasErrors(['idcontrato' => "Solo se pueden registrar pagos en un contrato vigente: este contrato está {$texto}."]);
        }
        $this->assertSame(0, Pago::count());
    }

    public function test_un_contrato_inexistente_se_rechaza(): void
    {
        $this->actingAs($this->asistente())->post(route('pagos.store'), [
            'idcontrato' => 9999, 'monto' => '100', 'moneda' => 'USD', 'fechapago' => '2026-12-15', 'metodo' => 'EFECTIVO',
        ])->assertSessionHasErrors(['idcontrato' => 'El contrato elegido no existe.']);
    }

    // ============================================ paso 6: campos obligatorios

    public function test_los_campos_obligatorios_vacios_se_rechazan_y_se_indican(): void
    {
        $this->actingAs($this->asistente())->post(route('pagos.store'), [])
            ->assertSessionHasErrors([
                'idcontrato' => 'Elige el contrato al que corresponde el pago.',
                'monto' => 'Indica el monto del pago.',
                'moneda' => 'Elige la moneda del pago.',
                'fechapago' => 'Indica la fecha del pago.',
                'metodo' => 'Elige el método de pago.',
            ]);

        $this->assertSame(0, Pago::count());
    }

    // ============================================ paso 7: el agente no entra

    public function test_el_agente_recibe_acceso_denegado(): void
    {
        $agente = $this->agente();
        $contrato = $this->contrato();

        $this->actingAs($agente)->get(route('pagos.create'))->assertForbidden();
        $this->pagar($agente, $contrato)->assertForbidden();

        $this->assertSame(0, Pago::count());
    }

    public function test_el_cliente_y_el_invitado_no_entran(): void
    {
        $this->get(route('pagos.create'))->assertRedirect(route('login'));
        $this->post(route('pagos.store'), [])->assertRedirect(route('login'));

        $cliente = $this->cliente();
        $this->actingAs($cliente)->get(route('pagos.create'))->assertForbidden();
        $this->pagar($cliente, $this->contrato())->assertForbidden();
        $this->assertSame(0, Pago::count());
    }

    // ============================================ saldo y pagos sucesivos

    public function test_el_contrato_admite_varios_pagos_y_cada_uno_tiene_su_comprobante(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato(1000);

        $this->pagar($asistente, $contrato, ['monto' => '300']);
        $this->pagar($asistente, $contrato, ['monto' => '696', 'moneda' => 'BOB', 'tipocambio' => '6.96']);
        $this->pagar($asistente, $contrato, ['monto' => '200']);

        $this->assertSame(['CP-2026-000001', 'CP-2026-000002', 'CP-2026-000003'], Pago::orderBy('idpago')->pluck('comprobante')->all());
        $this->assertSame(600.0, $contrato->totalPagadoUsd());
        $this->assertSame(400.0, $contrato->saldoUsd());
    }

    public function test_se_rechaza_un_pago_que_supera_el_saldo_y_se_acepta_el_saldo_exacto(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato(1000);
        $this->pagar($asistente, $contrato, ['monto' => '800']);

        $this->pagar($asistente, $contrato, ['monto' => '200.01'])
            ->assertSessionHasErrors(['monto' => 'El pago equivale a USD 200.01 y supera el saldo del contrato (USD 200.00).']);
        // En bolivianos se mide por su equivalente: 1.392 Bs a 6,96 son USD 200,00 y 1.400 Bs son USD 201,15.
        $this->pagar($asistente, $contrato, ['monto' => '1400', 'moneda' => 'BOB', 'tipocambio' => '6.96'])->assertSessionHasErrors('monto');
        $this->assertSame(1, Pago::count());

        $this->pagar($asistente, $contrato, ['monto' => '200'])->assertSessionHasNoErrors();
        $this->assertSame(0.0, $contrato->saldoUsd());
        $this->pagar($asistente, $contrato, ['monto' => '1'])->assertSessionHasErrors('monto');
        $this->assertSame(2, Pago::count());
    }

    public function test_los_pagos_pendientes_y_anulados_no_descuentan_el_saldo(): void
    {
        $contrato = $this->contrato(1000);
        Pago::create(['idcontrato' => $contrato->idcontrato, 'monto' => 900, 'montousd' => 900, 'moneda' => 'USD', 'fechapago' => '2026-12-10 10:00:00', 'estado' => Pago::PENDIENTE]);
        Pago::create(['idcontrato' => $contrato->idcontrato, 'monto' => 90, 'montousd' => 90, 'moneda' => 'USD', 'fechapago' => '2026-12-10 10:00:00', 'estado' => Pago::ANULADO]);

        $this->pagar($this->asistente(), $contrato, ['monto' => '1000'])->assertSessionHasNoErrors();

        $this->assertSame(0.0, $contrato->saldoUsd());
    }

    // ============================================ fecha y método

    public function test_una_fecha_de_pago_futura_se_rechaza_y_una_pasada_o_la_de_hoy_se_aceptan(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato();

        $this->pagar($asistente, $contrato, ['fechapago' => '2026-12-16'])
            ->assertSessionHasErrors(['fechapago' => 'La fecha de pago no puede ser futura.']);
        $this->pagar($asistente, $contrato, ['fechapago' => '15/12/2026'])->assertSessionHasErrors('fechapago');
        $this->assertSame(0, Pago::count());

        $this->pagar($asistente, $contrato, ['fechapago' => '2026-12-10', 'monto' => '100'])->assertSessionHasNoErrors();
        $this->pagar($asistente, $contrato, ['fechapago' => '2026-12-15', 'monto' => '100'])->assertSessionHasNoErrors();
        $this->assertSame(2, Pago::count());
        $this->assertSame('2026-12-10', Pago::orderBy('idpago')->firstOrFail()->fechapago->format('Y-m-d'));
    }

    public function test_el_pago_con_tarjeta_todavia_no_esta_disponible(): void
    {
        $this->pagar($this->asistente(), $this->contrato(), ['metodo' => 'TARJETA'])
            ->assertSessionHasErrors(['metodo' => 'El pago con tarjeta todavía no está disponible: registra el pago en efectivo.']);
        $this->pagar($this->asistente(), $this->contrato(), ['metodo' => 'CHEQUE'])->assertSessionHasErrors('metodo');

        $this->assertSame(0, Pago::count());
    }

    // ============================================ la pantalla

    public function test_el_formulario_pide_elegir_el_contrato_y_lista_solo_los_vigentes_con_saldo(): void
    {
        $this->contrato(1000, Contrato::VIGENTE, 'Cliente con saldo');
        $this->contrato(1000, Contrato::FINALIZADO, 'Cliente finalizado');
        $pagado = $this->contrato(500, Contrato::VIGENTE, 'Cliente pagado');
        Pago::create(['idcontrato' => $pagado->idcontrato, 'monto' => 500, 'montousd' => 500, 'moneda' => 'USD', 'fechapago' => '2026-12-10 10:00:00', 'estado' => Pago::PAGADO]);

        $this->actingAs($this->asistente())->get(route('pagos.create'))
            ->assertOk()
            ->assertSee('Registrar pago')->assertSee('Contrato vigente')
            ->assertSee('Cliente con saldo')->assertSee('saldo USD 1,000.00')
            ->assertDontSee('Cliente finalizado')->assertDontSee('Cliente pagado')
            ->assertDontSee('name="monto"', false);
    }

    public function test_al_elegir_el_contrato_se_muestran_sus_datos_el_saldo_y_los_pagos_ya_registrados(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato(1000, Contrato::VIGENTE, 'Clara Cliente');
        $this->pagar($asistente, $contrato, ['monto' => '300']);
        $this->flushSession();

        $this->actingAs($asistente)->get(route('pagos.create', ['contrato' => $contrato->idcontrato]))
            ->assertOk()
            ->assertSee('Clara Cliente')->assertSee('Venta')
            ->assertSee('USD 1,000.00')->assertSee('USD 300.00')->assertSee('USD 700.00')
            ->assertSee('CP-2026-000001')->assertSee('Pagado')
            ->assertSee('name="monto"', false)->assertSee('name="tipocambio"', false)
            ->assertSee('Efectivo')
            ->assertSee('<option value="TARJETA" disabled>', false);
    }

    public function test_el_formulario_sugiere_el_ultimo_tipo_de_cambio_usado(): void
    {
        $asistente = $this->asistente();
        $this->pagar($asistente, $this->contrato(), ['monto' => '696', 'moneda' => 'BOB', 'tipocambio' => '6.96']);
        $otro = $this->contrato();

        $this->actingAs($asistente)->get(route('pagos.create', ['contrato' => $otro->idcontrato]))
            ->assertSee('value="6.96"', false);
    }

    public function test_un_contrato_no_vigente_avisa_y_no_ofrece_el_formulario(): void
    {
        $contrato = $this->contrato(1000, Contrato::RESCINDIDO);

        $this->actingAs($this->asistente())->get(route('pagos.create', ['contrato' => $contrato->idcontrato]))
            ->assertOk()
            ->assertSee('solo se pueden registrar pagos en un contrato vigente')
            ->assertDontSee('name="monto"', false);
    }

    public function test_un_contrato_pagado_por_completo_avisa(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato(500);
        $this->pagar($asistente, $contrato, ['monto' => '500']);
        $this->flushSession();

        $this->actingAs($asistente)->get(route('pagos.create', ['contrato' => $contrato->idcontrato]))
            ->assertSee('ya está pagado por completo')
            ->assertDontSee('name="monto"', false);
    }

    public function test_un_contrato_inexistente_o_mal_formado_en_la_url_no_da_error(): void
    {
        $asistente = $this->asistente();

        $this->actingAs($asistente)->get(route('pagos.create', ['contrato' => 9999]))->assertOk()->assertSee('El contrato elegido no existe.');
        $this->actingAs($asistente)->get('/pagos/crear?contrato[]=x')->assertOk();
        $this->actingAs($asistente)->get('/pagos/crear?contrato=abc')->assertOk();
    }

    // ============================================ errores y accesos

    public function test_un_error_de_la_base_de_datos_avisa_y_no_deja_el_pago_a_medias(): void
    {
        $contrato = $this->contrato();
        TipoPago::creating(function () {
            throw new QueryException('sqlite', 'insert into tipopago', [], new \Exception('conexión perdida'));
        });

        $this->pagar($this->asistente(), $contrato, ['monto' => '696', 'moneda' => 'BOB', 'tipocambio' => '6.96'])
            ->assertSessionHas('error', 'No se pudo registrar el pago por un problema con la base de datos. Intenta de nuevo en unos minutos.');

        $this->assertSame(0, Pago::count());
        $this->assertSame(1000.0, $contrato->saldoUsd());
    }

    public function test_el_panel_lista_el_cu28_y_el_cu30_para_el_asistente_y_el_administrador_pero_no_para_el_agente(): void
    {
        foreach ([$this->asistente(), $this->admin()] as $usuario) {
            $this->actingAs($usuario)->get(route('panel'))
                ->assertSee('Registrar pago')->assertSee('Generar comprobante de pago')
                ->assertSee(route('pagos.create'), false);
        }
        $this->actingAs($this->agente())->get(route('panel'))->assertDontSee('Registrar pago')->assertDontSee('Generar comprobante de pago');
    }

    public function test_el_detalle_del_contrato_ofrece_registrar_pago_al_administrador_y_no_al_agente(): void
    {
        $agente = $this->agente();
        $contrato = $this->contrato();
        $contrato->propiedad->update(['idusuario' => $agente->idusuario]);
        $this->pagar($this->admin(), $contrato);
        $this->flushSession();

        $this->actingAs($this->admin())->get(route('contratos.show', $contrato))
            ->assertSee(route('pagos.create', ['contrato' => $contrato->idcontrato]), false)
            ->assertSee(route('pagos.comprobante', Pago::firstOrFail()), false);
        $this->actingAs($agente)->get(route('contratos.show', $contrato))
            ->assertSee('CP-2026-000001')
            ->assertDontSee(route('pagos.create', ['contrato' => $contrato->idcontrato]), false)
            ->assertDontSee(route('pagos.comprobante', Pago::firstOrFail()), false);
    }
}
