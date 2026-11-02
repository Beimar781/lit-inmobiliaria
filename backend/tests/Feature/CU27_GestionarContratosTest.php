<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Historial;
use App\Models\Pago;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

/**
 * CU27: Gestionar contratos. La fecha de las pruebas se fija en el martes 15 de diciembre de 2026, hora de Bolivia.
 */
class CU27_GestionarContratosTest extends SistemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-12-15 10:00:00', 'America/La_Paz'));
    }

    private function prospecto(?Usuario $agente = null, string $nombre = 'Clara Cliente'): Cliente
    {
        return Cliente::create(['nombre' => $nombre, 'email' => uniqid() . '@correo.com', 'idusuario' => $agente?->idusuario]);
    }

    private function contratar(Usuario $actor, Propiedad $propiedad, ?Cliente $cliente = null, array $cambios = [])
    {
        return $this->actingAs($actor)->post(route('contratos.store'), array_merge([
            'idpropiedad' => $propiedad->idpropiedad,
            'idcliente' => ($cliente ?? $this->prospecto($actor))->idcliente,
            'monto' => '85000.50',
            'fechainicio' => '2026-12-20',
            'fechafin' => '',
        ], $cambios));
    }

    private function contratoDe(Propiedad $propiedad, ?Cliente $cliente = null, string $estado = Contrato::VIGENTE, array $extra = []): Contrato
    {
        return Contrato::create(array_merge([
            'idpropiedad' => $propiedad->idpropiedad,
            'idcliente' => ($cliente ?? $this->prospecto())->idcliente,
            'tipocontrato' => $propiedad->tipopropiedad ?? 'Venta',
            'monto' => 1000, 'fechainicio' => '2026-12-01', 'estado' => $estado,
        ], $extra));
    }

    private function reservar(Propiedad $propiedad, Cliente $cliente, string $plazo = '2026-12-30', string $estado = Reserva::VIGENTE): Reserva
    {
        $propiedad->update(['estadopropiedad' => Propiedad::RESERVADO]);

        return Reserva::create([
            'idcliente' => $cliente->idcliente, 'idpropiedad' => $propiedad->idpropiedad,
            'fechareserva' => '2026-12-10', 'fechaplazo' => $plazo, 'estado' => $estado,
        ]);
    }

    // ============================================ paso 1: contrato de venta

    public function test_un_contrato_de_venta_queda_vigente_y_la_propiedad_pasa_a_vendido(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad(['titulo' => 'Casa de Ana', 'tipopropiedad' => 'Venta'], $ana);
        $cliente = $this->prospecto($ana);

        $this->contratar($ana, $propiedad, $cliente)
            ->assertRedirect(route('contratos.show', 1))
            ->assertSessionHas('status', 'Contrato registrado: «Casa de Ana» ahora está vendido.');

        $contrato = Contrato::firstOrFail();
        $this->assertSame(Contrato::VIGENTE, $contrato->estado);
        $this->assertSame('Venta', $contrato->tipocontrato);
        $this->assertSame('85000.50', (string) $contrato->monto);
        $this->assertSame($cliente->idcliente, $contrato->idcliente);
        $this->assertSame($ana->idusuario, $contrato->idusuario);
        $this->assertNull($contrato->idreserva);
        $this->assertSame('2026-12-20', $contrato->fechainicio->format('Y-m-d'));
        $this->assertNull($contrato->fechafin);
        $this->assertSame(Propiedad::VENDIDO, $propiedad->fresh()->estadopropiedad);
    }

    public function test_el_cambio_queda_en_el_historial_de_la_propiedad_y_en_la_bitacora(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);

        $this->contratar($ana, $propiedad);

        $historial = Historial::where('idpropiedad', $propiedad->idpropiedad)->firstOrFail();
        $this->assertSame(Historial::CAMBIO_ESTADO, $historial->tipo);
        $this->assertSame(['estado' => 'DISPONIBLE'], json_decode($historial->valoranterior, true));
        $this->assertSame(['estado' => 'VENDIDO'], json_decode($historial->valoractual, true));
        $this->assertStringContainsString('Contrato #' . Contrato::firstOrFail()->idcontrato, $historial->motivo);

        $registro = Bitacora::where('accion', 'CONTRATO_REGISTRADO')->firstOrFail();
        $this->assertSame('Contratos', $registro->modulo);
        $this->assertSame($ana->idusuario, $registro->idusuario);
        $this->assertSame('85000.50', $registro->detalle['monto']);
        $this->assertSame(1, Bitacora::where('accion', 'PROPIEDAD_ESTADO')->count());
    }

    // ============================================ paso 2: alquiler y anticrético

    public function test_un_contrato_de_alquiler_con_fecha_de_fin_posterior_pasa_la_propiedad_a_alquilado(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad(['tipopropiedad' => 'Alquiler'], $ana);

        $this->contratar($ana, $propiedad, null, ['monto' => '350', 'fechainicio' => '2026-12-20', 'fechafin' => '2027-12-20'])->assertSessionHasNoErrors();

        $contrato = Contrato::firstOrFail();
        $this->assertSame('Alquiler', $contrato->tipocontrato);
        $this->assertSame(Contrato::VIGENTE, $contrato->estado);
        $this->assertSame('2027-12-20', $contrato->fechafin->format('Y-m-d'));
        $this->assertSame(Propiedad::ALQUILADO, $propiedad->fresh()->estadopropiedad);
    }

    public function test_un_contrato_de_anticretico_pasa_la_propiedad_a_alquilado(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad(['tipopropiedad' => 'Anticrético'], $ana);

        $this->contratar($ana, $propiedad)->assertSessionHasNoErrors();

        $this->assertSame('Anticrético', Contrato::firstOrFail()->tipocontrato);
        $this->assertSame(Propiedad::ALQUILADO, $propiedad->fresh()->estadopropiedad);
    }

    public function test_el_tipo_del_contrato_sale_de_la_propiedad_aunque_se_mande_otro(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad(['tipopropiedad' => 'Venta'], $ana);

        $this->contratar($ana, $propiedad, null, ['tipocontrato' => 'Alquiler']);

        $this->assertSame('Venta', Contrato::firstOrFail()->tipocontrato);
        $this->assertSame(Propiedad::VENDIDO, $propiedad->fresh()->estadopropiedad);
    }

    public function test_una_propiedad_sin_tipo_de_operacion_no_se_puede_formalizar(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad(['tipopropiedad' => null], $ana);

        $this->contratar($ana, $propiedad)->assertSessionHasErrors('idpropiedad');

        $this->assertSame(0, Contrato::count());
        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);
    }

    // ============================================ paso 3 y 4: fechas y monto

    public function test_una_fecha_de_fin_anterior_o_igual_a_la_de_inicio_se_rechaza(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad(['tipopropiedad' => 'Alquiler'], $ana);

        foreach (['2026-12-19', '2026-12-20'] as $fin) {
            $this->contratar($ana, $propiedad, null, ['fechainicio' => '2026-12-20', 'fechafin' => $fin])
                ->assertSessionHasErrors(['fechafin' => 'La fecha de fin debe ser posterior a la de inicio.']);
        }
        $this->assertSame(0, Contrato::count());
        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);
    }

    public function test_las_fechas_deben_tener_formato_valido_y_el_inicio_es_obligatorio(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);

        $this->contratar($ana, $propiedad, null, ['fechainicio' => ''])->assertSessionHasErrors(['fechainicio' => 'Indica la fecha de inicio del contrato.']);
        $this->contratar($ana, $propiedad, null, ['fechainicio' => '20/12/2026'])->assertSessionHasErrors('fechainicio');
        $this->contratar($ana, $propiedad, null, ['fechafin' => 'mañana'])->assertSessionHasErrors('fechafin');
    }

    public function test_un_monto_igual_a_cero_o_no_valido_se_rechaza(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);

        $this->contratar($ana, $propiedad, null, ['monto' => '0'])->assertSessionHasErrors(['monto' => 'El monto debe ser mayor a cero.']);
        $this->contratar($ana, $propiedad, null, ['monto' => '-5'])->assertSessionHasErrors('monto');
        $this->contratar($ana, $propiedad, null, ['monto' => 'abc'])->assertSessionHasErrors(['monto' => 'El monto debe ser un número.']);
        $this->contratar($ana, $propiedad, null, ['monto' => '100.999'])->assertSessionHasErrors('monto');
        $this->contratar($ana, $propiedad, null, ['monto' => '99999999999'])->assertSessionHasErrors('monto');
        $this->contratar($ana, $propiedad, null, ['monto' => ''])->assertSessionHasErrors(['monto' => 'Indica el monto del contrato en dólares.']);

        $this->assertSame(0, Contrato::count());
        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);
    }

    public function test_un_monto_pequeno_valido_se_acepta(): void
    {
        $ana = $this->agente();

        $this->contratar($ana, $this->propiedad([], $ana), null, ['monto' => '0.01'])->assertSessionHasNoErrors();

        $this->assertSame('0.01', (string) Contrato::firstOrFail()->monto);
    }

    public function test_los_campos_vacios_se_indican(): void
    {
        $this->actingAs($this->agente())->post(route('contratos.store'), [])
            ->assertSessionHasErrors([
                'idpropiedad' => 'Elige la propiedad del contrato.',
                'idcliente' => 'Elige el cliente que firma el contrato.',
                'monto' => 'Indica el monto del contrato en dólares.',
                'fechainicio' => 'Indica la fecha de inicio del contrato.',
            ]);
    }

    public function test_un_cliente_que_no_existe_en_el_crm_se_rechaza(): void
    {
        $ana = $this->agente();

        $this->actingAs($ana)->post(route('contratos.store'), [
            'idpropiedad' => $this->propiedad([], $ana)->idpropiedad, 'idcliente' => 9999,
            'monto' => 1000, 'fechainicio' => '2026-12-20',
        ])->assertSessionHasErrors(['idcliente' => 'El cliente no existe en el CRM. Regístralo primero como prospecto.']);
    }

    // ============================================ paso 5: desde una reserva

    public function test_un_contrato_desde_una_reserva_vigente_la_concreta_y_cambia_la_propiedad(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $cliente = $this->prospecto($ana);
        $reserva = $this->reservar($propiedad, $cliente);

        $this->contratar($ana, $propiedad, $cliente)->assertSessionHasNoErrors();

        $contrato = Contrato::firstOrFail();
        $this->assertSame($reserva->idreserva, $contrato->idreserva);
        $this->assertSame(Reserva::CONCRETADA, $reserva->fresh()->estado);
        $this->assertSame(Propiedad::VENDIDO, $propiedad->fresh()->estadopropiedad);

        $historial = Historial::where('idpropiedad', $propiedad->idpropiedad)->firstOrFail();
        $this->assertSame(['estado' => 'RESERVADO'], json_decode($historial->valoranterior, true));
        $this->assertSame(['estado' => 'VENDIDO'], json_decode($historial->valoractual, true));
        $this->assertSame(1, Bitacora::where('accion', 'RESERVA_CONCRETADA')->count());
        $this->assertSame(0, Reserva::vigentes()->count());
    }

    public function test_una_propiedad_reservada_por_otro_cliente_no_se_puede_formalizar(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $reserva = $this->reservar($propiedad, $this->prospecto($ana, 'Quien reservó'));

        $this->contratar($ana, $propiedad, $this->prospecto($ana, 'Otro cliente'))
            ->assertSessionHasErrors(['idpropiedad' => 'La propiedad está reservada por otro cliente.']);

        $this->assertSame(0, Contrato::count());
        $this->assertSame(Reserva::VIGENTE, $reserva->fresh()->estado);
        $this->assertSame(Propiedad::RESERVADO, $propiedad->fresh()->estadopropiedad);
    }

    public function test_una_propiedad_que_figura_reservada_sin_reserva_vigente_no_se_formaliza(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad(['estadopropiedad' => Propiedad::RESERVADO], $ana);

        $this->contratar($ana, $propiedad)->assertSessionHasErrors('idpropiedad');

        $this->assertSame(0, Contrato::count());
    }

    public function test_el_agente_formaliza_la_reserva_de_un_cliente_aunque_no_sea_su_prospecto(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $cliente = $this->prospecto($this->agente()); // lo reservó el administrador para un cliente de otro agente
        $this->reservar($propiedad, $cliente);

        $this->contratar($ana, $propiedad, $cliente)->assertSessionHasNoErrors();

        $this->assertSame(1, Contrato::count());
    }

    public function test_si_la_reserva_ya_vencio_la_propiedad_queda_libre_y_se_puede_formalizar_con_otro_cliente(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $vieja = $this->reservar($propiedad, $this->prospecto($ana), '2026-12-10');

        $this->contratar($ana, $propiedad, $this->prospecto($ana, 'Otro cliente'))->assertSessionHasNoErrors();

        $this->assertSame(Reserva::VENCIDA, $vieja->fresh()->estado);
        $this->assertNull(Contrato::firstOrFail()->idreserva);
        $this->assertSame(Propiedad::VENDIDO, $propiedad->fresh()->estadopropiedad);
    }

    public function test_el_formulario_llega_con_la_propiedad_y_el_cliente_de_la_reserva(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad(['titulo' => 'Casa reservada'], $ana);
        $cliente = $this->prospecto($ana);
        $reserva = $this->reservar($propiedad, $cliente);

        $html = $this->actingAs($ana)->get(route('contratos.create', ['reserva' => $reserva->idreserva]))->assertOk()->assertSee('Casa reservada')->getContent();

        $this->assertMatchesRegularExpression('/<option value="' . $propiedad->idpropiedad . '"[^>]*selected/s', $html);
        $this->assertMatchesRegularExpression('/<option value="' . $cliente->idcliente . '"\s+selected/', $html);
    }

    public function test_el_formulario_lista_solo_propiedades_que_se_pueden_formalizar(): void
    {
        $ana = $this->agente();
        $this->propiedad(['titulo' => 'Disponible de Ana'], $ana);
        $this->propiedad(['titulo' => 'Vendida de Ana', 'estadopropiedad' => Propiedad::VENDIDO], $ana);
        $this->propiedad(['titulo' => 'Reservada sin reserva', 'estadopropiedad' => Propiedad::RESERVADO], $ana);
        $this->propiedad(['titulo' => 'Disponible de otro'], $this->agente());

        $this->actingAs($ana)->get(route('contratos.create'))
            ->assertOk()
            ->assertSee('Disponible de Ana')
            ->assertDontSee('Vendida de Ana')->assertDontSee('Reservada sin reserva')->assertDontSee('Disponible de otro');

        $this->actingAs($this->admin())->get(route('contratos.create'))->assertSee('Disponible de otro');
    }

    // ============================================ paso 6 y 7: cambiar el estado

    public function test_rescindir_un_contrato_vigente_devuelve_la_propiedad_a_disponible(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $this->contratar($ana, $propiedad);
        $contrato = Contrato::firstOrFail();

        $this->actingAs($ana)->patch(route('contratos.estado', $contrato), ['estado' => 'RESCINDIDO', 'motivo' => 'El comprador no consiguió el crédito'])
            ->assertRedirect(route('contratos.show', $contrato))
            ->assertSessionHas('status', 'El contrato fue rescindido y la propiedad volvió a estar disponible.');

        $this->assertSame(Contrato::RESCINDIDO, $contrato->fresh()->estado);
        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);

        $historial = Historial::where('idpropiedad', $propiedad->idpropiedad)->orderByDesc('idhistorial')->first();
        $this->assertSame(['estado' => 'VENDIDO'], json_decode($historial->valoranterior, true));
        $this->assertSame(['estado' => 'DISPONIBLE'], json_decode($historial->valoractual, true));
        $this->assertStringContainsString('El comprador no consiguió el crédito', $historial->motivo);
        $this->assertSame(1, Bitacora::where('accion', 'CONTRATO_RESCINDIDO')->count());
    }

    public function test_rescindir_exige_el_motivo(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $contrato = $this->contratoDe($propiedad);
        $propiedad->update(['estadopropiedad' => Propiedad::VENDIDO]);

        $this->actingAs($ana)->patch(route('contratos.estado', $contrato), ['estado' => 'RESCINDIDO', 'motivo' => ''])
            ->assertSessionHasErrors(['motivo' => 'Indica el motivo para rescindir el contrato.']);

        $this->assertSame(Contrato::VIGENTE, $contrato->fresh()->estado);
        $this->assertSame(Propiedad::VENDIDO, $propiedad->fresh()->estadopropiedad);
    }

    public function test_finalizar_un_contrato_vigente_lo_deja_finalizado_y_la_propiedad_no_cambia(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad(['tipopropiedad' => 'Alquiler'], $ana);
        $this->contratar($ana, $propiedad, null, ['fechafin' => '2027-12-20']);
        $contrato = Contrato::firstOrFail();

        $this->actingAs($ana)->patch(route('contratos.estado', $contrato), ['estado' => 'FINALIZADO'])
            ->assertSessionHas('status', 'El contrato fue finalizado.');

        $this->assertSame(Contrato::FINALIZADO, $contrato->fresh()->estado);
        $this->assertSame(Propiedad::ALQUILADO, $propiedad->fresh()->estadopropiedad);
        $this->assertSame(1, Bitacora::where('accion', 'CONTRATO_FINALIZADO')->count());
    }

    public function test_solo_se_puede_cambiar_el_estado_de_un_contrato_vigente_y_a_finalizado_o_rescindido(): void
    {
        $ana = $this->agente();

        foreach ([Contrato::FINALIZADO, Contrato::RESCINDIDO] as $estado) {
            $contrato = $this->contratoDe($this->propiedad([], $ana), null, $estado);

            $this->actingAs($ana)->patch(route('contratos.estado', $contrato), ['estado' => 'FINALIZADO'])->assertSessionHas('error');
            $this->assertSame($estado, $contrato->fresh()->estado);
        }

        $vigente = $this->contratoDe($this->propiedad([], $ana));
        $this->actingAs($ana)->patch(route('contratos.estado', $vigente), ['estado' => 'VIGENTE'])->assertSessionHasErrors('estado');
        $this->actingAs($ana)->patch(route('contratos.estado', $vigente), ['estado' => 'INVENTADO'])->assertSessionHasErrors('estado');
        $this->actingAs($ana)->patch(route('contratos.estado', $vigente), [])->assertSessionHasErrors('estado');
        $this->assertSame(Contrato::VIGENTE, $vigente->fresh()->estado);
    }

    public function test_al_rescindir_no_se_toca_una_propiedad_que_ya_cambio_de_estado(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad(['estadopropiedad' => Propiedad::RESERVADO], $ana);
        $contrato = $this->contratoDe($propiedad);

        $this->actingAs($ana)->patch(route('contratos.estado', $contrato), ['estado' => 'RESCINDIDO', 'motivo' => 'Prueba']);

        $this->assertSame(Contrato::RESCINDIDO, $contrato->fresh()->estado);
        $this->assertSame(Propiedad::RESERVADO, $propiedad->fresh()->estadopropiedad);
        $this->assertSame(0, Historial::count());
    }

    // ============================================ paso 8 y otras propiedades no formalizables

    public function test_no_se_puede_contratar_una_propiedad_dada_de_baja(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad(['estadopropiedad' => Propiedad::BAJA], $ana);

        $this->contratar($ana, $propiedad)->assertSessionHasErrors(['idpropiedad' => 'Esa propiedad fue dada de baja: no se puede formalizar.']);

        $this->assertSame(0, Contrato::count());
        $this->assertSame(Propiedad::BAJA, $propiedad->fresh()->estadopropiedad);
        $this->assertSame(0, Historial::count());
    }

    public function test_no_se_puede_contratar_una_propiedad_ya_vendida_o_alquilada_ni_con_contrato_vigente(): void
    {
        $ana = $this->agente();

        foreach ([Propiedad::VENDIDO, Propiedad::ALQUILADO] as $estado) {
            $propiedad = $this->propiedad(['estadopropiedad' => $estado], $ana);
            $this->contratar($ana, $propiedad)->assertSessionHasErrors(['idpropiedad' => 'La propiedad no está disponible (está ' . strtolower($estado) . ').']);
        }

        $conContrato = $this->propiedad([], $ana); // figura disponible pero tiene un contrato vigente
        $this->contratoDe($conContrato);
        $this->contratar($ana, $conContrato)->assertSessionHasErrors(['idpropiedad' => 'La propiedad ya tiene un contrato vigente.']);

        $this->assertSame(1, Contrato::count());
    }

    public function test_despues_de_rescindir_la_propiedad_se_puede_formalizar_otra_vez(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $this->contratar($ana, $propiedad);
        $this->actingAs($ana)->patch(route('contratos.estado', Contrato::firstOrFail()), ['estado' => 'RESCINDIDO', 'motivo' => 'Se cayó la venta']);

        $this->contratar($ana, $propiedad, $this->prospecto($ana, 'Otro cliente'))->assertSessionHasNoErrors();

        $this->assertSame(2, Contrato::count());
        $this->assertSame(1, Contrato::vigentes()->count());
    }

    // ============================================ permisos

    public function test_el_agente_no_puede_formalizar_una_propiedad_que_no_registro(): void
    {
        $ana = $this->agente();
        $deBeto = $this->propiedad([], $this->agente());

        $this->contratar($ana, $deBeto)->assertForbidden();
        $this->actingAs($ana)->post(route('contratos.store'), ['idpropiedad' => $deBeto->idpropiedad])->assertForbidden();

        $this->assertSame(0, Contrato::count());
        $this->assertSame(Propiedad::DISPONIBLE, $deBeto->fresh()->estadopropiedad);
    }

    public function test_el_agente_solo_formaliza_con_sus_prospectos(): void
    {
        $ana = $this->agente();

        $this->contratar($ana, $this->propiedad([], $ana), $this->prospecto($this->agente()))
            ->assertSessionHasErrors(['idcliente' => 'Ese cliente no está entre tus prospectos.']);

        $this->assertSame(0, Contrato::count());
    }

    public function test_el_administrador_formaliza_cualquier_propiedad(): void
    {
        $propiedad = $this->propiedad([], $this->agente());

        $this->contratar($this->admin(), $propiedad, $this->prospecto($this->agente()))->assertSessionHasNoErrors();

        $this->assertSame(Propiedad::VENDIDO, $propiedad->fresh()->estadopropiedad);
    }

    public function test_el_agente_no_gestiona_el_contrato_de_otro_agente_pero_el_administrador_si(): void
    {
        $ana = $this->agente();
        $deBeto = $this->contratoDe($this->propiedad([], $this->agente()));

        $this->actingAs($ana)->get(route('contratos.show', $deBeto))->assertForbidden();
        $this->actingAs($ana)->patch(route('contratos.estado', $deBeto), ['estado' => 'FINALIZADO'])->assertForbidden();
        $this->actingAs($ana)->patch(route('contratos.estado', $deBeto), [])->assertForbidden();
        $this->assertSame(Contrato::VIGENTE, $deBeto->fresh()->estado);

        $this->actingAs($this->admin())->get(route('contratos.show', $deBeto))->assertOk();
        $this->actingAs($this->admin())->patch(route('contratos.estado', $deBeto), ['estado' => 'FINALIZADO'])->assertRedirect();
        $this->assertSame(Contrato::FINALIZADO, $deBeto->fresh()->estado);
    }

    public function test_el_asistente_el_cliente_y_el_invitado_no_entran(): void
    {
        $contrato = $this->contratoDe($this->propiedad());

        $this->get(route('contratos.index'))->assertRedirect(route('login'));
        $this->post(route('contratos.store'), [])->assertRedirect(route('login'));

        foreach ([$this->asistente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario)->get(route('contratos.index'))->assertForbidden();
            $this->actingAs($usuario)->get(route('contratos.create'))->assertForbidden();
            $this->actingAs($usuario)->post(route('contratos.store'), [])->assertForbidden();
            $this->actingAs($usuario)->get(route('contratos.show', $contrato))->assertForbidden();
            $this->actingAs($usuario)->patch(route('contratos.estado', $contrato), ['estado' => 'FINALIZADO'])->assertForbidden();
        }
        $this->assertSame(Contrato::VIGENTE, $contrato->fresh()->estado);
    }

    public function test_un_contrato_inexistente_da_404(): void
    {
        $this->actingAs($this->admin())->get('/contratos/9999')->assertNotFound();
    }

    // ============================================ listado y detalle

    public function test_el_agente_ve_solo_los_contratos_de_sus_propiedades_y_el_administrador_todos(): void
    {
        $ana = $this->agente();
        $this->contratoDe($this->propiedad(['titulo' => 'Casa de Ana'], $ana), $this->prospecto($ana, 'Cliente de Ana'));
        $this->contratoDe($this->propiedad(['titulo' => 'Casa de Beto'], $this->agente()), $this->prospecto(null, 'Cliente de Beto'));

        $this->actingAs($ana)->get(route('contratos.index'))
            ->assertOk()->assertSee('Casa de Ana')->assertDontSee('Casa de Beto');
        $this->actingAs($this->admin())->get(route('contratos.index'))
            ->assertSee('Casa de Ana')->assertSee('Casa de Beto');
    }

    public function test_el_listado_se_filtra_por_estado_y_por_tipo(): void
    {
        $ana = $this->agente();
        $this->contratoDe($this->propiedad(['titulo' => 'Venta vigente', 'tipopropiedad' => 'Venta'], $ana));
        $this->contratoDe($this->propiedad(['titulo' => 'Alquiler vigente', 'tipopropiedad' => 'Alquiler'], $ana));
        $this->contratoDe($this->propiedad(['titulo' => 'Venta rescindida', 'tipopropiedad' => 'Venta'], $ana), null, Contrato::RESCINDIDO);

        $this->actingAs($ana)->get(route('contratos.index', ['estado' => 'RESCINDIDO']))
            ->assertSee('Venta rescindida')->assertDontSee('Venta vigente')->assertDontSee('Alquiler vigente');
        $this->actingAs($ana)->get(route('contratos.index', ['tipo' => 'Alquiler']))
            ->assertSee('Alquiler vigente')->assertDontSee('Venta vigente')->assertDontSee('Venta rescindida');
        $this->actingAs($ana)->get(route('contratos.index', ['estado' => 'VIGENTE', 'tipo' => 'Venta']))
            ->assertSee('Venta vigente')->assertDontSee('Alquiler vigente')->assertDontSee('Venta rescindida');
        $this->actingAs($ana)->get('/contratos?estado=INVENTADO&tipo[]=x')->assertOk()->assertSee('Venta vigente');
    }

    public function test_sin_contratos_se_muestra_un_mensaje(): void
    {
        $this->actingAs($this->agente())->get(route('contratos.index'))->assertOk()->assertSee('No hay contratos para mostrar.');
    }

    public function test_el_detalle_muestra_monto_pagado_y_saldo(): void
    {
        $ana = $this->agente();
        $contrato = $this->contratoDe($this->propiedad(['titulo' => 'Casa de Ana'], $ana), $this->prospecto($ana, 'Clara Cliente'), Contrato::VIGENTE, ['monto' => 1000]);
        Pago::create(['idcontrato' => $contrato->idcontrato, 'monto' => 300, 'montousd' => 300, 'moneda' => 'USD', 'fechapago' => '2026-12-10 10:00:00', 'estado' => Pago::PAGADO]);
        Pago::create(['idcontrato' => $contrato->idcontrato, 'monto' => 200, 'montousd' => 200, 'moneda' => 'USD', 'fechapago' => '2026-12-11 10:00:00', 'estado' => Pago::PENDIENTE]);

        $this->actingAs($ana)->get(route('contratos.show', $contrato))
            ->assertOk()
            ->assertSee('Casa de Ana')->assertSee('Clara Cliente')
            ->assertSee('USD 1,000.00')->assertSee('USD 300.00')->assertSee('USD 700.00')
            ->assertSee('Vigente');
    }

    public function test_el_detalle_de_un_contrato_cerrado_ya_no_ofrece_cambiar_el_estado(): void
    {
        $ana = $this->agente();
        $contrato = $this->contratoDe($this->propiedad([], $ana), null, Contrato::FINALIZADO);

        $this->actingAs($ana)->get(route('contratos.show', $contrato))
            ->assertSee('ya no se puede cambiar su estado')
            ->assertDontSee(route('contratos.estado', $contrato), false);
    }

    // ============================================ errores y panel

    public function test_un_error_de_la_base_de_datos_avisa_y_no_deja_el_contrato_a_medias(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $cliente = $this->prospecto($ana);
        $reserva = $this->reservar($propiedad, $cliente);
        Contrato::creating(function () {
            throw new QueryException('sqlite', 'insert into contrato', [], new \Exception('conexión perdida'));
        });

        $this->contratar($ana, $propiedad, $cliente)
            ->assertSessionHas('error', 'No se pudo registrar el contrato por un problema con la base de datos. Intenta de nuevo en unos minutos.');

        $this->assertSame(0, Contrato::count());
        $this->assertSame(Reserva::VIGENTE, $reserva->fresh()->estado);
        $this->assertSame(Propiedad::RESERVADO, $propiedad->fresh()->estadopropiedad);
    }

    public function test_el_panel_lista_el_cu27_para_el_agente_y_el_administrador(): void
    {
        foreach ([$this->agente(), $this->admin()] as $usuario) {
            $this->actingAs($usuario)->get(route('panel'))
                ->assertSee('Gestionar contratos')
                ->assertSee(route('contratos.index'), false);
        }
        $this->actingAs($this->asistente())->get(route('panel'))->assertDontSee('Gestionar contratos');
    }

    public function test_desde_el_listado_de_reservas_se_ofrece_crear_el_contrato(): void
    {
        $ana = $this->agente();
        $reserva = $this->reservar($this->propiedad([], $ana), $this->prospecto($ana));

        $this->actingAs($ana)->get(route('reservas.index'))
            ->assertSee('Crear contrato')
            ->assertSee(e(route('contratos.create', ['reserva' => $reserva->idreserva])), false);
    }
}
