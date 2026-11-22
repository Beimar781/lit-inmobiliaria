<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use Carbon\CarbonImmutable;

/** F-008 y F-009: fechas coherentes al registrar pagos y contratos. */
class FechasDePagosYContratosTest extends SistemaTestCase
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

    private function pagarEl(Contrato $contrato, string $fecha)
    {
        return $this->actingAs($this->asistente())->post(route('pagos.store'), [
            'idcontrato' => $contrato->idcontrato, 'monto' => '100', 'moneda' => 'USD', 'fechapago' => $fecha, 'metodo' => 'EFECTIVO',
        ]);
    }

    private function contratarCon(array $fechas)
    {
        $agente = $this->agente();

        return $this->actingAs($agente)->post(route('contratos.store'), array_merge([
            'idpropiedad' => $this->propiedad([], $agente)->idpropiedad,
            'idcliente' => Cliente::create(['nombre' => 'Pedro', 'email' => uniqid() . '@correo.com', 'idusuario' => $agente->idusuario])->idcliente,
            'monto' => '5000',
        ], $fechas));
    }

    public function test_un_pago_anterior_al_inicio_del_contrato_se_rechaza(): void
    {
        $this->pagarEl($this->contrato(), '2026-11-30')
            ->assertSessionHasErrors(['fechapago' => 'La fecha del pago no puede ser anterior al inicio del contrato.']);

        $this->assertSame(0, Pago::count());
    }

    public function test_un_pago_el_mismo_dia_del_inicio_del_contrato_se_acepta(): void
    {
        $this->pagarEl($this->contrato(), '2026-12-01')->assertSessionDoesntHaveErrors();

        $this->assertSame(1, Pago::count());
    }

    public function test_un_contrato_con_inicio_absurdo_se_rechaza(): void
    {
        $this->contratarCon(['fechainicio' => '1926-01-01'])
            ->assertSessionHasErrors(['fechainicio' => 'La fecha de inicio no puede ser de hace más de 10 años.']);
        $this->contratarCon(['fechainicio' => '2206-01-01'])
            ->assertSessionHasErrors(['fechainicio' => 'La fecha de inicio no puede ser de más de un año en el futuro.']);

        $this->assertSame(0, Contrato::count());
    }

    public function test_un_contrato_con_fin_demasiado_lejano_se_rechaza(): void
    {
        $this->contratarCon(['fechainicio' => '2026-12-15', 'fechafin' => '2999-01-01'])
            ->assertSessionHasErrors(['fechafin' => 'La fecha de fin es demasiado lejana.']);
    }

    public function test_un_contrato_con_fechas_normales_se_acepta(): void
    {
        $this->contratarCon(['fechainicio' => '2026-12-15', 'fechafin' => '2027-12-15'])->assertSessionDoesntHaveErrors();

        $this->assertSame(1, Contrato::count());
    }
}
