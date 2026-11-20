<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Reserva;
use Carbon\CarbonImmutable;

/** Atributo "detalles" de la reserva: texto opcional de hasta 1000 caracteres. */
class DetallesReservaTest extends SistemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-12-15 10:00:00', 'America/La_Paz'));
    }

    private function reservar(array $cambios = [])
    {
        $cliente = Cliente::create(['nombre' => 'Clara Cliente', 'email' => uniqid() . '@correo.com']);

        return $this->actingAs($this->asistente())->post(route('reservas.store'), array_merge([
            'idpropiedad' => $this->propiedad()->idpropiedad,
            'idcliente' => $cliente->idcliente,
            'monto' => '1000',
            'moneda' => 'USD',
            'fechaplazo' => '2026-12-30',
        ], $cambios));
    }

    public function test_los_detalles_se_guardan_y_se_ven_en_el_listado(): void
    {
        $this->reservar(['detalles' => 'Paga el saldo con crédito bancario'])->assertSessionHasNoErrors();

        $this->assertSame('Paga el saldo con crédito bancario', Reserva::firstOrFail()->detalles);
        $this->get(route('reservas.index'))->assertOk()->assertSee('Paga el saldo con crédito bancario');
    }

    public function test_los_detalles_son_opcionales(): void
    {
        $this->reservar()->assertSessionHasNoErrors();

        $this->assertNull(Reserva::firstOrFail()->detalles);
    }

    public function test_los_detalles_no_pueden_superar_1000_caracteres(): void
    {
        $this->reservar(['detalles' => str_repeat('a', 1001)])->assertSessionHasErrors('detalles');
        $this->assertSame(0, Reserva::count());

        $this->reservar(['detalles' => str_repeat('a', 1000)])->assertSessionHasNoErrors();
        $this->assertSame(1, Reserva::count());
    }
}
