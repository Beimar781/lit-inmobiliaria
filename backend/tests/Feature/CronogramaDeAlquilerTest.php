<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use App\Modules\AgendaVisitasPagos\Compartido\CronogramaDeAlquiler as Cronograma;
use Carbon\CarbonImmutable;

/**
 * Cronograma calculado de pagos mensuales de los contratos de alquiler (sin tablas nuevas).
 * La fecha de las pruebas se fija en el 20 de octubre de 2026, hora de Bolivia.
 */
class CronogramaDeAlquilerTest extends SistemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-20 10:00:00', 'America/La_Paz'));
    }

    private function contrato(array $cambios = []): Contrato
    {
        $cliente = Cliente::create(['nombre' => 'Clara Cliente', 'email' => uniqid() . '@correo.com']);

        return Contrato::create(array_merge([
            'idpropiedad' => $this->propiedad(['titulo' => 'Casa ' . uniqid()])->idpropiedad,
            'idcliente' => $cliente->idcliente,
            'tipocontrato' => 'Alquiler', 'monto' => 6600,
            'fechainicio' => '2026-11-05', 'fechafin' => '2027-11-05', 'estado' => Contrato::VIGENTE,
        ], $cambios));
    }

    private function pago(Contrato $contrato, float $monto, string $estado = Pago::PAGADO): Pago
    {
        return Pago::create([
            'idcontrato' => $contrato->idcontrato, 'monto' => $monto, 'montousd' => $monto, 'moneda' => 'USD',
            'fechapago' => '2026-11-05 10:00:00', 'estado' => $estado, 'metodo' => Pago::EFECTIVO,
        ]);
    }

    private function estados(array $cuotas): array
    {
        return array_count_values(array_column($cuotas, 'estado'));
    }

    // ------------------------------------------------------------------ unidad

    public function test_doce_cuotas_de_550_con_vencimiento_el_mismo_dia_de_cada_mes(): void
    {
        $cuotas = Cronograma::cuotas($this->contrato());

        $this->assertCount(12, $cuotas);
        $this->assertSame(550.0, $cuotas[0]['monto']);
        $this->assertSame('2026-11-05', $cuotas[0]['vencimiento']->format('Y-m-d'));
        $this->assertSame('2027-10-05', $cuotas[11]['vencimiento']->format('Y-m-d'));
        $this->assertSame(6600.0, round(array_sum(array_column($cuotas, 'monto')), 2));
        $this->assertSame(['PENDIENTE' => 12], $this->estados($cuotas));
    }

    public function test_un_pago_de_una_cuota_deja_la_primera_pagada_y_once_pendientes(): void
    {
        $contrato = $this->contrato();
        $this->pago($contrato, 550);

        $cuotas = Cronograma::cuotas($contrato->fresh());

        $this->assertSame(['PAGADA' => 1, 'PENDIENTE' => 11], $this->estados($cuotas));
        $this->assertSame(2, Cronograma::proxima($cuotas)['numero']);
    }

    public function test_un_pago_parcial_deja_la_cuota_parcial_y_dice_cuanto_falta(): void
    {
        $contrato = $this->contrato();
        $this->pago($contrato, 200);

        $cuotas = Cronograma::cuotas($contrato->fresh());

        $this->assertSame('PARCIAL', $cuotas[0]['estado']);
        $this->assertSame(350.0, $cuotas[0]['falta']);
        $this->assertSame(1, Cronograma::proxima($cuotas)['numero']);
    }

    public function test_un_pago_mayor_a_una_cuota_cubre_la_primera_y_parte_de_la_segunda(): void
    {
        $contrato = $this->contrato();
        $this->pago($contrato, 800);

        $cuotas = Cronograma::cuotas($contrato->fresh());

        $this->assertSame('PAGADA', $cuotas[0]['estado']);
        $this->assertSame('PARCIAL', $cuotas[1]['estado']);
        $this->assertSame(250.0, $cuotas[1]['pagado']);
        $this->assertSame(300.0, $cuotas[1]['falta']);
        $this->assertSame('PENDIENTE', $cuotas[2]['estado']);
    }

    public function test_el_redondeo_suma_exactamente_el_monto_del_contrato(): void
    {
        $contrato = $this->contrato(['monto' => 1000, 'fechainicio' => '2026-11-05', 'fechafin' => '2027-02-05']);

        $cuotas = Cronograma::cuotas($contrato);

        $this->assertCount(3, $cuotas);
        $this->assertSame([333.33, 333.33, 333.34], array_column($cuotas, 'monto'));
        $this->assertSame(1000.0, round(array_sum(array_column($cuotas, 'monto')), 2));
    }

    public function test_los_pagos_pendientes_y_anulados_se_ignoran(): void
    {
        $contrato = $this->contrato();
        $this->pago($contrato, 550);
        $this->pago($contrato, 550, Pago::PENDIENTE);
        $this->pago($contrato, 550, Pago::ANULADO);

        $cuotas = Cronograma::cuotas($contrato->fresh());

        $this->assertSame(['PAGADA' => 1, 'PENDIENTE' => 11], $this->estados($cuotas));
    }

    public function test_lo_pagado_del_cronograma_coincide_con_monto_menos_saldo(): void
    {
        $contrato = $this->contrato();
        $this->pago($contrato, 550);
        $this->pago($contrato, 123.45);

        $contrato = $contrato->fresh();
        $pagadoEnCuotas = round(array_sum(array_column(Cronograma::cuotas($contrato), 'pagado')), 2);

        $this->assertSame(5926.55, round($contrato->saldoUsd(), 2));
        $this->assertSame(round((float) $contrato->monto - $contrato->saldoUsd(), 2), $pagadoEnCuotas);
    }

    public function test_venta_y_anticretico_no_tienen_cronograma(): void
    {
        foreach (['Venta', 'Anticrético'] as $tipo) {
            $contrato = $this->contrato(['tipocontrato' => $tipo]);

            $this->assertSame([], Cronograma::cuotas($contrato), $tipo);
        }
    }

    public function test_un_alquiler_sin_fecha_de_fin_no_tiene_cronograma(): void
    {
        $this->assertSame([], Cronograma::cuotas($this->contrato(['fechafin' => null])));
    }

    public function test_una_cuota_sin_pagar_con_fecha_pasada_esta_vencida(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-01-20 10:00:00', 'America/La_Paz'));
        $contrato = $this->contrato();
        $this->pago($contrato, 550);

        $cuotas = Cronograma::cuotas($contrato->fresh());

        // Vencieron el 05/12 y el 05/01; la cuota 1 está pagada y la del 05/02 todavía no vence.
        $this->assertSame(['PAGADA' => 1, 'VENCIDA' => 2, 'PENDIENTE' => 9], $this->estados($cuotas));
        $this->assertSame(2, Cronograma::proxima($cuotas)['numero']);
    }

    public function test_un_contrato_finalizado_o_rescindido_no_marca_cuotas_vencidas(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-01-20 10:00:00', 'America/La_Paz'));

        foreach ([Contrato::FINALIZADO, Contrato::RESCINDIDO] as $estado) {
            $cuotas = Cronograma::cuotas($this->contrato(['estado' => $estado]));

            $this->assertSame(['PENDIENTE' => 12], $this->estados($cuotas), $estado);
        }
    }

    public function test_los_meses_cortos_vencen_el_ultimo_dia_del_mes(): void
    {
        $contrato = $this->contrato(['monto' => 400, 'fechainicio' => '2026-12-31', 'fechafin' => '2027-04-30']);

        $cuotas = Cronograma::cuotas($contrato);

        $this->assertSame(
            ['2026-12-31', '2027-01-31', '2027-02-28', '2027-03-31'],
            array_map(fn ($c) => $c['vencimiento']->format('Y-m-d'), $cuotas)
        );
    }

    // ------------------------------------------------------------------ vistas

    public function test_el_portal_y_el_detalle_del_alquiler_muestran_las_12_cuotas(): void
    {
        $cuenta = $this->cliente(['email' => 'clara@correo.com']);
        $cliente = Cliente::create(['nombre' => 'Clara', 'email' => 'clara@correo.com', 'idusuariocuenta' => $cuenta->idusuario]);
        $contrato = $this->contrato(['idcliente' => $cliente->idcliente]);
        $this->pago($contrato, 550);
        $this->pago($contrato, 550, Pago::PENDIENTE);

        $this->actingAs($cuenta)->get(route('portal.contrato', $contrato))
            ->assertOk()
            ->assertSee('Pagos mensuales')
            ->assertSee('05/11/2026')->assertSee('05/10/2027')
            ->assertSee('Los pagos se aplican en orden, de la primera cuota a la última.')
            ->assertSee('Próxima')
            ->assertSee('USD 6,050.00');

        $this->actingAs($this->admin())->get(route('contratos.show', $contrato))
            ->assertOk()
            ->assertSee('Pagos mensuales')
            ->assertSee('05/10/2027');

        $this->assertSame(6050.0, $contrato->fresh()->saldoUsd());
    }

    public function test_un_contrato_de_venta_no_muestra_la_tabla_de_pagos_mensuales(): void
    {
        $contrato = $this->contrato(['tipocontrato' => 'Venta', 'fechafin' => null]);

        $this->actingAs($this->admin())->get(route('contratos.show', $contrato))
            ->assertOk()->assertDontSee('Pagos mensuales');
    }

    public function test_el_formulario_de_pago_del_alquiler_muestra_la_proxima_cuota_solo_como_pista(): void
    {
        $contrato = $this->contrato();
        $this->pago($contrato, 550);

        $this->actingAs($this->admin())->get(route('pagos.create', ['contrato' => $contrato->idcontrato]))
            ->assertOk()
            ->assertSee('Próxima cuota: N.º 2, USD 550.00, vence el 05/12/2026');

        $venta = $this->contrato(['tipocontrato' => 'Venta']);
        $this->actingAs($this->admin())->get(route('pagos.create', ['contrato' => $venta->idcontrato]))
            ->assertOk()->assertDontSee('Próxima cuota');
    }

    public function test_el_aviso_de_pagado_por_completo_solo_sale_con_saldo_cero(): void
    {
        $cuenta = $this->cliente(['email' => 'clara2@correo.com']);
        $cliente = Cliente::create(['nombre' => 'Clara', 'email' => 'clara2@correo.com', 'idusuariocuenta' => $cuenta->idusuario]);
        $contrato = $this->contrato(['idcliente' => $cliente->idcliente]);
        $this->pago($contrato, 550);

        $this->actingAs($cuenta)->get(route('portal.contrato', $contrato))
            ->assertDontSee('pagado por completo')->assertSee('Pagar con tarjeta');

        $this->pago($contrato, 6050);

        $this->actingAs($cuenta)->get(route('portal.contrato', $contrato))
            ->assertSee('pagado por completo');
    }
}
