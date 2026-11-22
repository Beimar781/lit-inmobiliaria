<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use Carbon\CarbonImmutable;

/** F-007: un mismo formulario no se procesa dos veces (doble clic, F5 o "atrás"). */
class EnvioUnicoTest extends SistemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-12-15 10:00:00', 'America/La_Paz'));
    }

    private function contrato(): Contrato
    {
        return Contrato::create([
            'idpropiedad' => $this->propiedad(['titulo' => 'Casa ' . uniqid()])->idpropiedad,
            'idcliente' => Cliente::create(['nombre' => 'Clara Cliente', 'email' => uniqid() . '@correo.com'])->idcliente,
            'tipocontrato' => 'Venta', 'monto' => 1000, 'fechainicio' => '2026-12-01', 'estado' => Contrato::VIGENTE,
        ]);
    }

    private function datos(Contrato $contrato, array $extra = []): array
    {
        return array_merge([
            'idcontrato' => $contrato->idcontrato, 'monto' => '100', 'moneda' => 'USD',
            'fechapago' => '2026-12-15', 'metodo' => 'EFECTIVO',
        ], $extra);
    }

    public function test_el_formulario_de_pago_incluye_un_codigo_de_envio_unico(): void
    {
        $contrato = $this->contrato();

        $html = $this->actingAs($this->asistente())->get(route('pagos.create', ['contrato' => $contrato->idcontrato]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/name="_envio" value="[A-Za-z0-9]{32}"/', $html);
    }

    public function test_el_mismo_envio_repetido_registra_un_solo_pago(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato();
        $datos = $this->datos($contrato, ['_envio' => 'codigo-de-prueba-1']);

        $this->actingAs($asistente)->post(route('pagos.store'), $datos)->assertRedirect();
        $repetido = $this->actingAs($asistente)->post(route('pagos.store'), $datos);

        $repetido->assertRedirect()->assertSessionHas('aviso');
        $this->assertSame(1, Pago::count());
    }

    public function test_dos_envios_con_codigos_distintos_registran_dos_pagos(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato();

        $this->actingAs($asistente)->post(route('pagos.store'), $this->datos($contrato, ['_envio' => 'a']));
        $this->actingAs($asistente)->post(route('pagos.store'), $this->datos($contrato, ['_envio' => 'b']));

        $this->assertSame(2, Pago::count());
    }

    public function test_sin_codigo_el_envio_sigue_funcionando(): void
    {
        $this->actingAs($this->asistente())->post(route('pagos.store'), $this->datos($this->contrato()))->assertRedirect();

        $this->assertSame(1, Pago::count());
    }

    public function test_el_layout_desactiva_los_botones_al_enviar(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString("addEventListener('submit'", $html);
        $this->assertStringContainsString('b.disabled = true', $html);
    }
}
