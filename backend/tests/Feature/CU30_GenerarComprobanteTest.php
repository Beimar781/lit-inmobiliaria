<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\Usuario;
use Carbon\CarbonImmutable;

/**
 * CU30: Generar comprobante de pago (el del pago formal del contrato: CP-AAAA-000001).
 * La fecha de las pruebas se fija en el martes 15 de diciembre de 2026, hora de Bolivia.
 */
class CU30_GenerarComprobanteTest extends SistemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-12-15 10:00:00', 'America/La_Paz'));
    }

    private function contrato(float $monto = 1000): Contrato
    {
        return Contrato::create([
            'idpropiedad' => $this->propiedad(['titulo' => 'Casa de prueba'])->idpropiedad,
            'idcliente' => Cliente::create(['nombre' => 'Clara Cliente', 'email' => uniqid() . '@correo.com'])->idcliente,
            'tipocontrato' => 'Venta', 'monto' => $monto, 'fechainicio' => '2026-12-01', 'estado' => Contrato::VIGENTE,
        ]);
    }

    private function pagar(Usuario $actor, Contrato $contrato, array $cambios = [])
    {
        return $this->actingAs($actor)->post(route('pagos.store'), array_merge([
            'idcontrato' => $contrato->idcontrato, 'monto' => '300', 'moneda' => 'USD', 'fechapago' => '2026-12-15', 'metodo' => 'EFECTIVO',
        ], $cambios));
    }

    // ============================================ paso 1: al registrar un pago se ve el comprobante

    public function test_al_registrar_un_pago_se_muestra_el_comprobante_con_sus_datos(): void
    {
        $asistente = $this->asistente(['nombre' => 'Sara Asistente']);
        $contrato = $this->contrato(1000);

        $respuesta = $this->pagar($asistente, $contrato);
        $pago = Pago::firstOrFail();

        $this->get($respuesta->headers->get('Location'))
            ->assertOk()
            ->assertSee('Comprobante de pago')
            ->assertSee('CP-2026-000001')
            ->assertSee('15/12/2026 10:00')
            ->assertSee('Clara Cliente')
            ->assertSee('Casa de prueba')->assertSee($contrato->propiedad->codigo)
            ->assertSee('N.º ' . $contrato->idcontrato)->assertSee('Venta')->assertSee('USD 1,000.00')
            ->assertSee('Efectivo')
            ->assertSee('USD 300.00')
            ->assertSee('Saldo restante')->assertSee('USD 700.00')
            ->assertSee('Sara Asistente');
        $this->assertSame('CP-2026-000001', $pago->comprobante);
    }

    public function test_el_comprobante_de_un_pago_en_bolivianos_muestra_el_tipo_de_cambio_y_el_equivalente(): void
    {
        $this->pagar($this->asistente(), $this->contrato(1000), ['monto' => '696', 'moneda' => 'BOB', 'tipocambio' => '6.96']);

        $this->get(route('pagos.comprobante', Pago::firstOrFail()))
            ->assertOk()
            ->assertSee('BOB 696.00')
            ->assertSee('6.96 Bs por USD')
            ->assertSee('Equivalente')->assertSee('USD 100.00')
            ->assertSee('USD 900.00');
    }

    public function test_el_comprobante_de_un_pago_en_dolares_no_muestra_tipo_de_cambio(): void
    {
        $this->pagar($this->asistente(), $this->contrato());

        $this->get(route('pagos.comprobante', Pago::firstOrFail()))
            ->assertOk()->assertDontSee('Tipo de cambio')->assertDontSee('Equivalente');
    }

    // ============================================ paso 2: reimprimir conserva el número

    public function test_volver_a_emitir_el_comprobante_conserva_el_mismo_numero(): void
    {
        $asistente = $this->asistente();
        $this->pagar($asistente, $this->contrato());
        $pago = Pago::firstOrFail();
        $this->pagar($asistente, $this->contrato());   // otro pago, para comprobar que no se mueven los números

        foreach ([1, 2, 3] as $vez) {
            $this->actingAs($asistente)->get(route('pagos.comprobante', $pago))->assertOk()->assertSee('CP-2026-000001');
        }

        $this->assertSame('CP-2026-000001', $pago->fresh()->comprobante);
        $this->assertSame(1, Bitacora::where('accion', 'COMPROBANTE_GENERADO')->where('descripcion', 'like', '%CP-2026-000001%')->count(), 'el número se genera una sola vez');
    }

    public function test_el_saldo_restante_es_el_que_quedo_despues_de_ese_pago(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato(1000);
        $this->pagar($asistente, $contrato, ['monto' => '300']);
        $this->pagar($asistente, $contrato, ['monto' => '200']);
        [$primero, $segundo] = Pago::orderBy('idpago')->get()->all();

        $this->actingAs($asistente)->get(route('pagos.comprobante', $primero))->assertSee('USD 700.00');
        $this->actingAs($asistente)->get(route('pagos.comprobante', $segundo))->assertSee('USD 500.00');
    }

    // ============================================ paso 3: inexistente o anulado

    public function test_el_comprobante_de_un_pago_inexistente_dice_que_no_esta_disponible(): void
    {
        $asistente = $this->asistente();

        $this->actingAs($asistente)->get(route('pagos.comprobante', 9999))
            ->assertNotFound()
            ->assertSee('Comprobante no disponible')->assertSee('El pago no existe.');
        $this->actingAs($asistente)->get('/pagos/abc/comprobante')->assertNotFound()->assertSee('Comprobante no disponible');
    }

    public function test_el_comprobante_de_un_pago_anulado_o_pendiente_no_esta_disponible(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato();

        foreach ([Pago::ANULADO => 'anulado', Pago::PENDIENTE => 'pendiente'] as $estado => $texto) {
            $pago = Pago::create(['idcontrato' => $contrato->idcontrato, 'monto' => 100, 'montousd' => 100, 'moneda' => 'USD', 'fechapago' => '2026-12-10 10:00:00', 'estado' => $estado]);

            $this->actingAs($asistente)->get(route('pagos.comprobante', $pago))
                ->assertNotFound()
                ->assertSee('Comprobante no disponible')->assertSee("El pago está {$texto}.");

            $this->assertNull($pago->fresh()->comprobante, 'un pago anulado o pendiente no recibe número');
        }
    }

    public function test_un_pago_anulado_con_comprobante_ya_emitido_deja_de_estar_disponible(): void
    {
        $asistente = $this->asistente();
        $this->pagar($asistente, $this->contrato());
        $pago = Pago::firstOrFail();
        $pago->update(['estado' => Pago::ANULADO]);

        $this->actingAs($asistente)->get(route('pagos.comprobante', $pago))->assertNotFound()->assertSee('Comprobante no disponible');
    }

    // ============================================ si el pago todavía no tiene número, se genera

    public function test_un_pago_pagado_sin_numero_recibe_el_siguiente_al_pedir_su_comprobante(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato();
        $this->pagar($asistente, $contrato);
        $sinNumero = Pago::create(['idcontrato' => $contrato->idcontrato, 'monto' => 100, 'montousd' => 100, 'moneda' => 'USD', 'fechapago' => '2026-12-15 11:00:00', 'estado' => Pago::PAGADO]);

        $this->actingAs($asistente)->get(route('pagos.comprobante', $sinNumero))->assertOk()->assertSee('CP-2026-000002');

        $this->assertSame('CP-2026-000002', $sinNumero->fresh()->comprobante);
        $this->assertSame(1, Bitacora::where('accion', 'COMPROBANTE_GENERADO')->where('descripcion', 'like', '%CP-2026-000002%')->count());
    }

    // ============================================ paso 4: imprimir

    public function test_el_comprobante_se_puede_imprimir_o_guardar_como_pdf(): void
    {
        $this->pagar($this->asistente(), $this->contrato());

        $this->get(route('pagos.comprobante', Pago::firstOrFail()))
            ->assertSee('Imprimir o guardar como PDF')
            ->assertSee('window.print()', false)
            ->assertSee('@media print', false)
            ->assertSee('Firma del cliente');
    }

    // ============================================ paso 5: números distintos y correlativos

    public function test_dos_pagos_consecutivos_reciben_numeros_distintos_y_correlativos(): void
    {
        $asistente = $this->asistente();
        $this->pagar($asistente, $this->contrato());
        $this->pagar($asistente, $this->contrato());

        [$a, $b] = Pago::orderBy('idpago')->pluck('comprobante')->all();
        $this->assertSame('CP-2026-000001', $a);
        $this->assertSame('CP-2026-000002', $b);
        $this->assertNotSame($a, $b);
    }

    public function test_la_numeracion_empieza_de_nuevo_cada_ano(): void
    {
        $asistente = $this->asistente();
        $this->pagar($asistente, $this->contrato());

        $this->travelTo(CarbonImmutable::parse('2027-01-05 10:00:00', 'America/La_Paz'));
        $this->pagar($asistente, $this->contrato(), ['fechapago' => '2027-01-05']);

        $this->assertSame(['CP-2026-000001', 'CP-2027-000001'], Pago::orderBy('idpago')->pluck('comprobante')->all());
    }

    public function test_el_numero_del_comprobante_no_se_puede_repetir_en_la_base_de_datos(): void
    {
        $this->pagar($this->asistente(), $this->contrato());
        $contrato = $this->contrato();

        $this->expectException(\Illuminate\Database\QueryException::class);
        Pago::create(['idcontrato' => $contrato->idcontrato, 'monto' => 1, 'montousd' => 1, 'moneda' => 'USD', 'fechapago' => '2026-12-15 10:00:00', 'estado' => Pago::PAGADO, 'comprobante' => 'CP-2026-000001']);
    }

    public function test_la_numeracion_continua_despues_del_pago_inicial_que_viene_de_una_reserva(): void
    {
        $asistente = $this->asistente();
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $cliente = Cliente::create(['nombre' => 'Clara Cliente', 'email' => 'c@correo.com', 'idusuario' => $ana->idusuario]);
        $this->actingAs($asistente)->post(route('reservas.store'), [
            'idpropiedad' => $propiedad->idpropiedad, 'idcliente' => $cliente->idcliente,
            'monto' => '1000', 'moneda' => 'USD', 'fechaplazo' => '2026-12-30',
        ])->assertSessionHasNoErrors();
        $this->actingAs($ana)->post(route('contratos.store'), [
            'idpropiedad' => $propiedad->idpropiedad, 'idcliente' => $cliente->idcliente, 'monto' => '85000', 'fechainicio' => '2026-12-20',
        ])->assertSessionHasNoErrors();
        $contrato = Contrato::firstOrFail();
        $this->assertSame('CP-2026-000001', $contrato->pagos()->firstOrFail()->comprobante);

        $this->pagar($asistente, $contrato, ['monto' => '5000'])->assertSessionHasNoErrors();

        $this->assertSame(['CP-2026-000001', 'CP-2026-000002'], $contrato->pagos()->orderBy('idpago')->pluck('comprobante')->all());
        // El recibo de la reserva (RV) y el comprobante (CP) son documentos distintos y no comparten numeración.
        $this->assertSame('RV-2026-000001', Reserva::firstOrFail()->cobro->comprobante);
        // El pago inicial también tiene su comprobante.
        $this->actingAs($asistente)->get(route('pagos.comprobante', $contrato->pagos()->orderBy('idpago')->firstOrFail()))
            ->assertOk()->assertSee('CP-2026-000001')->assertSee('USD 1,000.00')->assertSee('USD 84,000.00');
    }

    // ============================================ paso 6: permisos

    public function test_el_agente_recibe_acceso_denegado(): void
    {
        $this->pagar($this->asistente(), $this->contrato());
        $pago = Pago::firstOrFail();

        $this->actingAs($this->agente())->get(route('pagos.comprobante', $pago))->assertForbidden();
        $this->actingAs($this->agente())->get(route('pagos.comprobante', 9999))->assertForbidden();
    }

    public function test_el_cliente_y_el_invitado_no_ven_comprobantes(): void
    {
        $this->pagar($this->asistente(), $this->contrato());
        $pago = Pago::firstOrFail();

        $this->actingAs($this->cliente())->get(route('pagos.comprobante', $pago))->assertForbidden();

        auth()->logout();
        $this->flushSession();
        $this->get(route('pagos.comprobante', $pago))->assertRedirect(route('login'));
    }

    public function test_el_administrador_tambien_ve_comprobantes(): void
    {
        $this->pagar($this->asistente(), $this->contrato());

        $this->actingAs($this->admin())->get(route('pagos.comprobante', Pago::firstOrFail()))->assertOk()->assertSee('CP-2026-000001');
    }
}
