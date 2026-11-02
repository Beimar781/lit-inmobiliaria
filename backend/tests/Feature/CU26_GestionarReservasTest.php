<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Models\Historial;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

/**
 * CU26: Gestionar reservas de propiedad. La fecha de las pruebas se fija en el martes 15 de diciembre de 2026, hora de Bolivia.
 */
class CU26_GestionarReservasTest extends SistemaTestCase
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

    private function reservar(Usuario $actor, Propiedad $propiedad, ?Cliente $cliente = null, array $cambios = [])
    {
        return $this->actingAs($actor)->post(route('reservas.store'), array_merge([
            'idpropiedad' => $propiedad->idpropiedad,
            'idcliente' => ($cliente ?? $this->prospecto($actor))->idcliente,
            'fechaplazo' => '2026-12-30',
        ], $cambios));
    }

    private function reservaDe(Propiedad $propiedad, Cliente $cliente, string $plazo = '2026-12-30', string $estado = Reserva::VIGENTE, bool $reservar = true): Reserva
    {
        if ($reservar) {
            $propiedad->update(['estadopropiedad' => Propiedad::RESERVADO]);
        }

        return Reserva::create([
            'idcliente' => $cliente->idcliente, 'idpropiedad' => $propiedad->idpropiedad,
            'fechareserva' => '2026-12-10', 'fechaplazo' => $plazo, 'estado' => $estado,
        ]);
    }

    // ============================================ paso 1: registrar

    public function test_se_registra_una_reserva_vigente_y_la_propiedad_pasa_a_reservado(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad(['titulo' => 'Casa de Ana'], $ana);
        $cliente = $this->prospecto($ana, 'Clara Cliente');

        $this->reservar($ana, $propiedad, $cliente)
            ->assertRedirect(route('reservas.index'))
            ->assertSessionHas('status', 'Reserva registrada: «Casa de Ana» quedó reservada para Clara Cliente hasta el 30/12/2026.');

        $reserva = Reserva::firstOrFail();
        $this->assertSame(Reserva::VIGENTE, $reserva->estado);
        $this->assertSame($cliente->idcliente, $reserva->idcliente);
        $this->assertSame($propiedad->idpropiedad, $reserva->idpropiedad);
        $this->assertSame('2026-12-15', $reserva->fechareserva->format('Y-m-d'));
        $this->assertSame('2026-12-30', $reserva->fechaplazo->format('Y-m-d'));
        $this->assertSame($ana->idusuario, $reserva->idusuario);
        $this->assertSame(Propiedad::RESERVADO, $propiedad->fresh()->estadopropiedad);
    }

    public function test_el_cambio_de_estado_queda_en_el_historial_de_la_propiedad_y_en_la_bitacora(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);

        $this->reservar($ana, $propiedad, $this->prospecto($ana, 'Clara Cliente'));

        $historial = Historial::where('idpropiedad', $propiedad->idpropiedad)->firstOrFail();
        $this->assertSame(Historial::CAMBIO_ESTADO, $historial->tipo);
        $this->assertSame(['estado' => 'DISPONIBLE'], json_decode($historial->valoranterior, true));
        $this->assertSame(['estado' => 'RESERVADO'], json_decode($historial->valoractual, true));
        $this->assertStringContainsString('Reserva #' . Reserva::firstOrFail()->idreserva, $historial->motivo);
        $this->assertSame($ana->idusuario, $historial->idusuario);

        $registro = Bitacora::where('accion', 'RESERVA_REGISTRADA')->firstOrFail();
        $this->assertSame('Reservas', $registro->modulo);
        $this->assertSame($ana->idusuario, $registro->idusuario);
        $this->assertSame('2026-12-30', $registro->detalle['fechaplazo']);
        $this->assertSame(1, Bitacora::where('accion', 'PROPIEDAD_ESTADO')->count());
    }

    public function test_el_administrador_reserva_cualquier_propiedad_para_cualquier_cliente(): void
    {
        $propiedad = $this->propiedad([], $this->agente());
        $cliente = $this->prospecto($this->agente());

        $this->reservar($this->admin(), $propiedad, $cliente)->assertSessionHasNoErrors()->assertRedirect(route('reservas.index'));

        $this->assertSame(Propiedad::RESERVADO, $propiedad->fresh()->estadopropiedad);
    }

    public function test_el_formulario_lista_solo_propiedades_disponibles_y_los_prospectos_del_agente(): void
    {
        $ana = $this->agente();
        $this->propiedad(['titulo' => 'Disponible de Ana'], $ana);
        $this->propiedad(['titulo' => 'Vendida de Ana', 'estadopropiedad' => Propiedad::VENDIDO], $ana);
        $this->propiedad(['titulo' => 'Disponible de otro'], $this->agente());
        $this->prospecto($ana, 'Prospecto de Ana');
        $this->prospecto($this->agente(), 'Prospecto de otro');

        $this->actingAs($ana)->get(route('reservas.create'))
            ->assertOk()
            ->assertSee('Disponible de Ana')->assertSee('Prospecto de Ana')
            ->assertDontSee('Vendida de Ana')->assertDontSee('Disponible de otro')->assertDontSee('Prospecto de otro');

        $this->actingAs($this->admin())->get(route('reservas.create'))
            ->assertSee('Disponible de otro')->assertSee('Prospecto de otro')->assertDontSee('Vendida de Ana');
    }

    public function test_el_formulario_llega_con_la_propiedad_y_el_cliente_elegidos(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $cliente = $this->prospecto($ana);

        $html = $this->actingAs($ana)->get(route('reservas.create', ['propiedad' => $propiedad->idpropiedad, 'cliente' => $cliente->idcliente]))->getContent();

        $this->assertMatchesRegularExpression('/<option value="' . $propiedad->idpropiedad . '"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/<option value="' . $cliente->idcliente . '"\s+selected/', $html);
    }

    // ============================================ paso 2 y 6: propiedad no disponible

    public function test_no_se_puede_reservar_una_propiedad_que_no_esta_disponible(): void
    {
        $ana = $this->agente();

        foreach ([Propiedad::VENDIDO, Propiedad::ALQUILADO, Propiedad::RESERVADO] as $estado) {
            $propiedad = $this->propiedad(['estadopropiedad' => $estado], $ana);

            $this->reservar($ana, $propiedad)->assertSessionHasErrors(['idpropiedad' => 'La propiedad no está disponible (está ' . strtolower($estado) . ').']);
            $this->assertSame($estado, $propiedad->fresh()->estadopropiedad);
        }
        $this->assertSame(0, Reserva::count());
    }

    public function test_no_se_puede_reservar_una_propiedad_dada_de_baja(): void
    {
        $ana = $this->agente();

        $this->reservar($ana, $this->propiedad(['estadopropiedad' => Propiedad::BAJA], $ana))
            ->assertSessionHasErrors(['idpropiedad' => 'Esa propiedad fue dada de baja.']);
    }

    public function test_una_segunda_reserva_sobre_una_propiedad_ya_reservada_se_rechaza(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);

        $this->reservar($ana, $propiedad)->assertSessionHasNoErrors();
        $this->reservar($ana, $propiedad, $this->prospecto($ana, 'Otro cliente'))
            ->assertSessionHasErrors(['idpropiedad' => 'La propiedad ya tiene una reserva vigente.']);

        $this->assertSame(1, Reserva::count());
        $this->assertSame(1, Historial::where('idpropiedad', $propiedad->idpropiedad)->count());
    }

    public function test_una_reserva_vigente_bloquea_otra_aunque_la_propiedad_figure_disponible(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $this->reservaDe($propiedad, $this->prospecto($ana), '2026-12-30', Reserva::VIGENTE, false);

        $this->reservar($ana, $propiedad)->assertSessionHasErrors(['idpropiedad' => 'La propiedad ya tiene una reserva vigente.']);
    }

    // ============================================ paso 3: plazo

    public function test_un_plazo_pasado_o_de_hoy_se_rechaza(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);

        foreach (['2026-12-14', '2026-12-15', '2020-01-01'] as $plazo) {
            $this->reservar($ana, $propiedad, null, ['fechaplazo' => $plazo])
                ->assertSessionHasErrors(['fechaplazo' => 'El plazo de la reserva debe ser una fecha futura.']);
        }
        $this->reservar($ana, $propiedad, null, ['fechaplazo' => '30/12/2026'])->assertSessionHasErrors('fechaplazo');
        $this->assertSame(0, Reserva::count());
        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);
    }

    public function test_el_plazo_de_manana_se_acepta(): void
    {
        $ana = $this->agente();

        $this->reservar($ana, $this->propiedad([], $ana), null, ['fechaplazo' => '2026-12-16'])->assertSessionHasNoErrors();
    }

    // ============================================ paso 4: campos obligatorios

    public function test_los_campos_obligatorios_vacios_se_rechazan_y_se_indican(): void
    {
        $this->actingAs($this->agente())->post(route('reservas.store'), [])
            ->assertSessionHasErrors([
                'idpropiedad' => 'Elige la propiedad que se va a reservar.',
                'idcliente' => 'Elige el cliente que reserva.',
                'fechaplazo' => 'Indica la fecha de plazo de la reserva.',
            ]);

        $this->assertSame(0, Reserva::count());
    }

    public function test_un_cliente_que_no_existe_en_el_crm_se_rechaza(): void
    {
        $ana = $this->agente();

        $this->actingAs($ana)->post(route('reservas.store'), [
            'idpropiedad' => $this->propiedad([], $ana)->idpropiedad, 'idcliente' => 9999, 'fechaplazo' => '2026-12-30',
        ])->assertSessionHasErrors(['idcliente' => 'El cliente no existe en el CRM. Regístralo primero como prospecto.']);
    }

    // ============================================ paso 5: cancelar

    public function test_cancelar_una_reserva_vigente_devuelve_la_propiedad_a_disponible(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad(['titulo' => 'Casa de Ana'], $ana);
        $this->reservar($ana, $propiedad, $this->prospecto($ana));
        $reserva = Reserva::firstOrFail();

        $this->actingAs($ana)->patch(route('reservas.cancelar', $reserva))
            ->assertRedirect(route('reservas.index'))
            ->assertSessionHas('status', 'La reserva fue cancelada y «Casa de Ana» volvió a estar disponible.');

        $this->assertSame(Reserva::CANCELADA, $reserva->fresh()->estado);
        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);

        $historial = Historial::where('idpropiedad', $propiedad->idpropiedad)->orderByDesc('idhistorial')->first();
        $this->assertSame(['estado' => 'RESERVADO'], json_decode($historial->valoranterior, true));
        $this->assertSame(['estado' => 'DISPONIBLE'], json_decode($historial->valoractual, true));
        $this->assertSame(1, Bitacora::where('accion', 'RESERVA_CANCELADA')->count());
    }

    public function test_despues_de_cancelar_la_propiedad_se_puede_volver_a_reservar(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $this->reservar($ana, $propiedad);
        $this->actingAs($ana)->patch(route('reservas.cancelar', Reserva::firstOrFail()));

        $this->reservar($ana, $propiedad, $this->prospecto($ana, 'Otro cliente'))->assertSessionHasNoErrors();

        $this->assertSame(2, Reserva::count());
        $this->assertSame(1, Reserva::vigentes()->count());
    }

    public function test_solo_se_cancelan_las_reservas_vigentes(): void
    {
        $ana = $this->agente();
        $cliente = $this->prospecto($ana);

        foreach ([Reserva::CANCELADA, Reserva::CONCRETADA, Reserva::VENCIDA] as $estado) {
            $propiedad = $this->propiedad(['estadopropiedad' => Propiedad::VENDIDO], $ana);
            $reserva = $this->reservaDe($propiedad, $cliente, '2026-12-30', $estado, false);

            $this->actingAs($ana)->patch(route('reservas.cancelar', $reserva))->assertSessionHas('error');

            $this->assertSame($estado, $reserva->fresh()->estado);
            $this->assertSame(Propiedad::VENDIDO, $propiedad->fresh()->estadopropiedad);
        }
        $this->assertSame(0, Bitacora::where('accion', 'RESERVA_CANCELADA')->count());
    }

    public function test_el_agente_no_puede_cancelar_la_reserva_de_otro_agente_pero_el_administrador_si(): void
    {
        $beto = $this->agente();
        $propiedad = $this->propiedad([], $beto);
        $this->reservar($beto, $propiedad);
        $reserva = Reserva::firstOrFail();

        $this->actingAs($this->agente())->patch(route('reservas.cancelar', $reserva))->assertForbidden();
        $this->assertSame(Reserva::VIGENTE, $reserva->fresh()->estado);

        $this->actingAs($this->admin())->patch(route('reservas.cancelar', $reserva))->assertRedirect(route('reservas.index'));
        $this->assertSame(Reserva::CANCELADA, $reserva->fresh()->estado);
    }

    // ============================================ paso 7: listado por rol y filtro

    public function test_el_agente_ve_solo_las_reservas_de_sus_propiedades_y_el_administrador_todas(): void
    {
        $ana = $this->agente();
        $beto = $this->agente();
        $this->reservar($ana, $this->propiedad(['titulo' => 'Casa de Ana'], $ana), $this->prospecto($ana, 'Cliente de Ana'));
        $this->reservar($beto, $this->propiedad(['titulo' => 'Casa de Beto'], $beto), $this->prospecto($beto, 'Cliente de Beto'));

        $this->flushSession(); // el aviso de la última reserva no debe contarse como parte del listado
        $this->actingAs($ana)->get(route('reservas.index'))
            ->assertOk()
            ->assertSee('Casa de Ana')->assertSee('Cliente de Ana')
            ->assertDontSee('Casa de Beto')->assertDontSee('Cliente de Beto');

        $this->actingAs($this->admin())->get(route('reservas.index'))
            ->assertSee('Casa de Ana')->assertSee('Casa de Beto');
    }

    public function test_el_listado_se_filtra_por_estado(): void
    {
        $ana = $this->agente();
        $this->reservaDe($this->propiedad(['titulo' => 'Casa vigente'], $ana), $this->prospecto($ana), '2026-12-30', Reserva::VIGENTE);
        $this->reservaDe($this->propiedad(['titulo' => 'Casa cancelada'], $ana), $this->prospecto($ana), '2026-12-30', Reserva::CANCELADA, false);

        $this->actingAs($ana)->get(route('reservas.index', ['estado' => 'CANCELADA']))
            ->assertSee('Casa cancelada')->assertDontSee('Casa vigente');
        $this->actingAs($ana)->get(route('reservas.index', ['estado' => 'VIGENTE']))
            ->assertSee('Casa vigente')->assertDontSee('Casa cancelada');
        // Un filtro inventado o mal formado se ignora (no da error).
        $this->actingAs($ana)->get('/reservas?estado=INVENTADO')->assertOk()->assertSee('Casa vigente')->assertSee('Casa cancelada');
        $this->actingAs($ana)->get('/reservas?estado[]=x')->assertOk();
    }

    public function test_sin_reservas_se_muestra_un_mensaje(): void
    {
        $this->actingAs($this->agente())->get(route('reservas.index'))->assertOk()->assertSee('No hay reservas para mostrar.');
    }

    // ============================================ permisos del agente

    public function test_el_agente_no_puede_reservar_una_propiedad_que_no_registro(): void
    {
        $ana = $this->agente();
        $deBeto = $this->propiedad([], $this->agente());

        $this->reservar($ana, $deBeto)->assertForbidden();
        // aunque mande datos inválidos, recibe 403 y no un aviso de validación
        $this->actingAs($ana)->post(route('reservas.store'), ['idpropiedad' => $deBeto->idpropiedad])->assertForbidden();

        $this->assertSame(0, Reserva::count());
        $this->assertSame(Propiedad::DISPONIBLE, $deBeto->fresh()->estadopropiedad);
    }

    public function test_el_agente_solo_reserva_para_sus_prospectos(): void
    {
        $ana = $this->agente();

        $this->reservar($ana, $this->propiedad([], $ana), $this->prospecto($this->agente()))
            ->assertSessionHasErrors(['idcliente' => 'Ese cliente no está entre tus prospectos.']);

        $this->assertSame(0, Reserva::count());
    }

    public function test_el_asistente_el_cliente_y_el_invitado_no_entran(): void
    {
        $reserva = $this->reservaDe($this->propiedad(), $this->prospecto());

        $this->get(route('reservas.index'))->assertRedirect(route('login'));
        $this->post(route('reservas.store'), [])->assertRedirect(route('login'));
        $this->patch(route('reservas.cancelar', $reserva))->assertRedirect(route('login'));

        foreach ([$this->asistente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario)->get(route('reservas.index'))->assertForbidden();
            $this->actingAs($usuario)->get(route('reservas.create'))->assertForbidden();
            $this->actingAs($usuario)->post(route('reservas.store'), [])->assertForbidden();
            $this->actingAs($usuario)->patch(route('reservas.cancelar', $reserva))->assertForbidden();
        }
        $this->assertSame(Reserva::VIGENTE, $reserva->fresh()->estado);
    }

    // ============================================ vencimiento del plazo

    public function test_al_consultar_las_reservas_vencidas_se_marcan_y_la_propiedad_vuelve_a_disponible(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $reserva = $this->reservaDe($propiedad, $this->prospecto($ana), '2026-12-14');

        $this->actingAs($ana)->get(route('reservas.index'))->assertOk()->assertSee('Vencida');

        $this->assertSame(Reserva::VENCIDA, $reserva->fresh()->estado);
        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);
        $historial = Historial::where('idpropiedad', $propiedad->idpropiedad)->firstOrFail();
        $this->assertSame(['estado' => 'DISPONIBLE'], json_decode($historial->valoractual, true));
        $this->assertStringContainsString('Venció el plazo', $historial->motivo);
        $this->assertSame(1, Bitacora::where('accion', 'RESERVA_VENCIDA')->count());
    }

    public function test_una_reserva_con_plazo_de_hoy_sigue_vigente(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $reserva = $this->reservaDe($propiedad, $this->prospecto($ana), '2026-12-15');

        $this->actingAs($ana)->get(route('reservas.index'));

        $this->assertSame(Reserva::VIGENTE, $reserva->fresh()->estado);
        $this->assertSame(Propiedad::RESERVADO, $propiedad->fresh()->estadopropiedad);
    }

    public function test_al_intentar_reservar_una_propiedad_con_reserva_vencida_se_libera_y_se_acepta_la_nueva(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $vieja = $this->reservaDe($propiedad, $this->prospecto($ana), '2026-12-10');

        $this->reservar($ana, $propiedad, $this->prospecto($ana, 'Nuevo cliente'))->assertSessionHasNoErrors();

        $this->assertSame(Reserva::VENCIDA, $vieja->fresh()->estado);
        $this->assertSame(1, Reserva::vigentes()->count());
        $this->assertSame(Propiedad::RESERVADO, $propiedad->fresh()->estadopropiedad);
    }

    public function test_el_vencimiento_no_toca_una_propiedad_que_ya_no_esta_reservada(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad(['estadopropiedad' => Propiedad::VENDIDO], $ana);
        $reserva = $this->reservaDe($propiedad, $this->prospecto($ana), '2026-12-10', Reserva::VIGENTE, false);

        $this->actingAs($ana)->get(route('reservas.index'));

        $this->assertSame(Reserva::VENCIDA, $reserva->fresh()->estado);
        $this->assertSame(Propiedad::VENDIDO, $propiedad->fresh()->estadopropiedad);
        $this->assertSame(0, Historial::count());
    }

    // ============================================ errores y panel

    public function test_un_error_de_la_base_de_datos_avisa_y_no_deja_la_reserva_a_medias(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        Reserva::creating(function () {
            throw new QueryException('sqlite', 'insert into reserva', [], new \Exception('conexión perdida'));
        });

        $this->reservar($ana, $propiedad)
            ->assertSessionHas('error', 'No se pudo registrar la reserva por un problema con la base de datos. Intenta de nuevo en unos minutos.');

        $this->assertSame(0, Reserva::count());
        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);
    }

    public function test_el_panel_lista_el_cu26_para_el_agente_y_el_administrador(): void
    {
        foreach ([$this->agente(), $this->admin()] as $usuario) {
            $this->actingAs($usuario)->get(route('panel'))
                ->assertSee('Gestionar reservas de propiedad')
                ->assertSee(route('reservas.index'), false);
        }
        $this->actingAs($this->asistente())->get(route('panel'))->assertDontSee('Gestionar reservas de propiedad');
    }
}
