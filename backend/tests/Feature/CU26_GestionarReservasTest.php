<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Historial;
use App\Models\MovimientoReserva;
use App\Models\Pago;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Usuario;
use App\Models\Visita;
use App\Modules\AgendaVisitasPagos\Compartido\DineroDeReservas;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

/**
 * CU26: Gestionar reservas de propiedad. La reserva se hace después de una visita realizada y se paga en efectivo;
 * si el cliente no formaliza la compra se le devuelve casi todo (se retiene el 20 %).
 * La fecha de las pruebas se fija en el martes 15 de diciembre de 2026, hora de Bolivia. Las propiedades de prueba cuestan USD 85.000.
 */
class CU26_GestionarReservasTest extends SistemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-12-15 10:00:00', 'America/La_Paz'));
    }

    private function cliente3(string $nombre = 'Clara Cliente'): Cliente
    {
        return Cliente::create(['nombre' => $nombre, 'email' => uniqid() . '@correo.com']);
    }

    private function visita(Propiedad $propiedad, ?Cliente $cliente = null, string $estado = Visita::REALIZADA): Visita
    {
        static $hora = 8;
        $hora = $hora >= 17 ? 8 : $hora + 1;

        return Visita::create([
            'idpropiedad' => $propiedad->idpropiedad,
            'idcliente' => ($cliente ?? $this->cliente3())->idcliente,
            'fecha' => '2026-12-10', 'hora' => sprintf('%02d:00:00', $hora), 'estado' => $estado,
        ]);
    }

    /** Reserva apoyada en una visita realizada: manda la visita y, de ella, la propiedad y el cliente. */
    private function reservar(Usuario $actor, Visita $visita, array $cambios = [])
    {
        return $this->actingAs($actor)->post(route('reservas.store'), array_merge([
            'idvisita' => $visita->idvisita,
            'idpropiedad' => $visita->idpropiedad,
            'idcliente' => $visita->idcliente,
            'monto' => '1000',
            'moneda' => 'USD',
            'fechaplazo' => '2026-12-30',
        ], $cambios));
    }

    /** Reserva sin visita previa: se elige directamente la propiedad y el cliente. */
    private function reservarSinVisita(Usuario $actor, Propiedad $propiedad, ?Cliente $cliente = null, array $cambios = [])
    {
        return $this->actingAs($actor)->post(route('reservas.store'), array_merge([
            'idpropiedad' => $propiedad->idpropiedad,
            'idcliente' => ($cliente ?? $this->cliente3())->idcliente,
            'monto' => '1000',
            'moneda' => 'USD',
            'fechaplazo' => '2026-12-30',
        ], $cambios));
    }

    /** Una reserva ya hecha por el sistema: propiedad con visita realizada y cobro de USD 1.000. */
    private function reservaHecha(?Usuario $agente = null, array $cambios = []): Reserva
    {
        $propiedad = $this->propiedad(['titulo' => 'Casa ' . uniqid()], $agente);
        $this->reservar($this->asistente(), $this->visita($propiedad), $cambios)->assertSessionHasNoErrors();

        return Reserva::orderByDesc('idreserva')->firstOrFail();
    }

    private function cancelar(Usuario $actor, Reserva $reserva)
    {
        return $this->actingAs($actor)->patch(route('reservas.cancelar', $reserva));
    }

    private function devolver(Usuario $actor, Reserva $reserva)
    {
        return $this->actingAs($actor)->post(route('reservas.devolver', $reserva));
    }

    // ============================================ paso 1: registrar con el cobro en efectivo

    public function test_el_asistente_registra_la_reserva_despues_de_una_visita_realizada_y_cobra_en_efectivo(): void
    {
        $ana = $this->agente();
        $asistente = $this->asistente();
        $propiedad = $this->propiedad(['titulo' => 'Casa de Ana'], $ana);
        $cliente = $this->cliente3('Clara Cliente');
        $visita = $this->visita($propiedad, $cliente);

        $respuesta = $this->reservar($asistente, $visita)
            ->assertSessionHas('status', 'Reserva registrada: «Casa de Ana» quedó reservada para Clara Cliente hasta el 30/12/2026.');

        $reserva = Reserva::firstOrFail();
        $respuesta->assertRedirect(route('reservas.recibo', $reserva->cobro));
        $this->assertSame(Reserva::VIGENTE, $reserva->estado);
        $this->assertSame($cliente->idcliente, $reserva->idcliente);
        $this->assertSame($propiedad->idpropiedad, $reserva->idpropiedad);
        $this->assertSame($visita->idvisita, $reserva->idvisita);
        $this->assertSame('2026-12-15', $reserva->fechareserva->format('Y-m-d'));
        $this->assertSame('2026-12-30', $reserva->fechaplazo->format('Y-m-d'));
        $this->assertSame('1000.00', (string) $reserva->montousd);
        $this->assertSame($asistente->idusuario, $reserva->idusuario);
        $this->assertSame(Propiedad::RESERVADO, $propiedad->fresh()->estadopropiedad);

        $cobro = $reserva->cobro;
        $this->assertSame(MovimientoReserva::COBRO, $cobro->tipo);
        $this->assertSame('1000.00', (string) $cobro->monto);
        $this->assertSame('USD', $cobro->moneda);
        $this->assertNull($cobro->tipocambio);
        $this->assertSame('1000.00', (string) $cobro->montousd);
        $this->assertSame('RV-2026-000001', $cobro->comprobante);
        $this->assertSame($asistente->idusuario, $cobro->idusuario);
    }

    public function test_el_cambio_de_estado_queda_en_el_historial_y_el_cobro_en_la_bitacora(): void
    {
        $asistente = $this->asistente();
        $propiedad = $this->propiedad([]);
        $this->reservar($asistente, $this->visita($propiedad, $this->cliente3('Clara Cliente')));

        $historial = Historial::where('idpropiedad', $propiedad->idpropiedad)->firstOrFail();
        $this->assertSame(Historial::CAMBIO_ESTADO, $historial->tipo);
        $this->assertSame(['estado' => 'DISPONIBLE'], json_decode($historial->valoranterior, true));
        $this->assertSame(['estado' => 'RESERVADO'], json_decode($historial->valoractual, true));
        $this->assertStringContainsString('Reserva #' . Reserva::firstOrFail()->idreserva, $historial->motivo);

        $registro = Bitacora::where('accion', 'RESERVA_REGISTRADA')->firstOrFail();
        $this->assertSame('Reservas', $registro->modulo);
        $this->assertSame($asistente->idusuario, $registro->idusuario);
        $this->assertSame('1000.00', $registro->detalle['monto']);
        $this->assertSame('RV-2026-000001', $registro->detalle['comprobante']);
        $this->assertSame(1, Bitacora::where('accion', 'PROPIEDAD_ESTADO')->count());
    }

    public function test_el_administrador_tambien_registra_reservas(): void
    {
        $this->reservar($this->admin(), $this->visita($this->propiedad()))->assertSessionHasNoErrors();

        $this->assertSame(1, Reserva::vigentes()->count());
    }

    public function test_los_recibos_son_correlativos_y_cobros_y_devoluciones_llevan_su_propia_serie(): void
    {
        $asistente = $this->asistente();
        $primera = $this->reservaHecha();
        $segunda = $this->reservaHecha();

        $this->assertSame('RV-2026-000001', $primera->cobro->comprobante);
        $this->assertSame('RV-2026-000002', $segunda->cobro->comprobante);

        $this->cancelar($asistente, $segunda);
        $this->devolver($asistente, $segunda);
        $this->assertSame('DV-2026-000001', $segunda->fresh()->devolucion->comprobante);
    }

    public function test_la_serie_de_recibos_empieza_de_nuevo_cada_ano(): void
    {
        $this->reservaHecha();
        $this->travelTo(CarbonImmutable::parse('2027-01-05 10:00:00', 'America/La_Paz'));
        $segunda = $this->reservaHecha(null, ['fechaplazo' => '2027-02-01']);

        $this->assertSame('RV-2027-000001', $segunda->cobro->comprobante);
    }

    // ============================================ el cobro: monedas y mínimo

    public function test_un_pago_en_bolivianos_guarda_el_tipo_de_cambio_y_su_equivalente_en_dolares(): void
    {
        $this->reservar($this->asistente(), $this->visita($this->propiedad()), ['monto' => '6960', 'moneda' => 'BOB', 'tipocambio' => '6.96'])
            ->assertSessionHasNoErrors();

        $cobro = Reserva::firstOrFail()->cobro;
        $this->assertSame('BOB', $cobro->moneda);
        $this->assertSame('6960.00', (string) $cobro->monto);
        $this->assertSame('6.9600', (string) $cobro->tipocambio);
        $this->assertSame('1000.00', (string) $cobro->montousd);
        $this->assertSame('1000.00', (string) Reserva::firstOrFail()->montousd);
    }

    public function test_el_equivalente_en_dolares_se_redondea_a_dos_decimales(): void
    {
        $this->assertSame(1000.0, DineroDeReservas::aUsd('6960', 'BOB', '6.96'));
        $this->assertSame(143.68, DineroDeReservas::aUsd('1000', 'BOB', '6.96'));
        $this->assertSame(250.5, DineroDeReservas::aUsd('250.50', 'USD', null));
        $this->assertSame(850.0, DineroDeReservas::minimoUsd('85000'));
    }

    public function test_un_pago_en_bolivianos_sin_tipo_de_cambio_se_rechaza_y_en_dolares_se_ignora(): void
    {
        $asistente = $this->asistente();
        $visita = $this->visita($this->propiedad());

        $this->reservar($asistente, $visita, ['moneda' => 'BOB', 'tipocambio' => ''])
            ->assertSessionHasErrors(['tipocambio' => 'Indica el tipo de cambio oficial del día para el pago en bolivianos.']);
        $this->reservar($asistente, $visita, ['moneda' => 'BOB', 'tipocambio' => '0'])->assertSessionHasErrors('tipocambio');
        $this->reservar($asistente, $visita, ['moneda' => 'BOB', 'tipocambio' => 'abc'])->assertSessionHasErrors('tipocambio');
        $this->assertSame(0, Reserva::count());

        $this->reservar($asistente, $visita, ['moneda' => 'USD', 'tipocambio' => '6.96'])->assertSessionHasNoErrors();
        $this->assertNull(Reserva::firstOrFail()->cobro->tipocambio);
    }

    public function test_el_monto_debe_ser_al_menos_el_uno_por_ciento_del_precio(): void
    {
        $asistente = $this->asistente();
        $propiedad = $this->propiedad();
        $visita = $this->visita($propiedad);

        $this->reservar($asistente, $visita, ['monto' => '849.99'])->assertSessionHasErrors('monto');
        $this->assertStringContainsString('al menos el 1% del precio: USD 850.00', session('errors')->first('monto'));
        // En bolivianos se mide por su equivalente en dólares: 5.000 Bs a 6,96 son USD 718,39.
        $this->reservar($asistente, $visita, ['monto' => '5000', 'moneda' => 'BOB', 'tipocambio' => '6.96'])->assertSessionHasErrors('monto');
        $this->assertSame(0, Reserva::count());
        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);

        $this->reservar($asistente, $visita, ['monto' => '850'])->assertSessionHasNoErrors();
        $this->assertSame(1, Reserva::count());
    }

    public function test_el_monto_no_puede_superar_el_precio_de_la_propiedad(): void
    {
        $this->reservar($this->asistente(), $this->visita($this->propiedad()), ['monto' => '85000.01'])
            ->assertSessionHasErrors('monto');

        $this->assertSame(0, Reserva::count());
    }

    public function test_el_monto_y_la_moneda_no_validos_se_rechazan(): void
    {
        $asistente = $this->asistente();
        $visita = $this->visita($this->propiedad());

        $this->reservar($asistente, $visita, ['monto' => '0'])->assertSessionHasErrors(['monto' => 'El monto debe ser mayor a cero.']);
        $this->reservar($asistente, $visita, ['monto' => '-5'])->assertSessionHasErrors('monto');
        $this->reservar($asistente, $visita, ['monto' => 'abc'])->assertSessionHasErrors(['monto' => 'El monto debe ser un número.']);
        $this->reservar($asistente, $visita, ['monto' => '1000.999'])->assertSessionHasErrors('monto');
        $this->reservar($asistente, $visita, ['moneda' => 'EUR'])->assertSessionHasErrors(['moneda' => 'La moneda debe ser dólares (USD) o bolivianos (BOB).']);
        $this->assertSame(0, Reserva::count());
    }

    // ============================================ la reserva exige una visita realizada

    public function test_una_visita_que_no_esta_realizada_no_sirve_de_apoyo_pero_la_visita_es_opcional(): void
    {
        $asistente = $this->asistente();
        $propiedad = $this->propiedad();

        foreach ([Visita::PROGRAMADA, Visita::REAGENDADA, Visita::CANCELADA] as $estado) {
            $this->reservar($asistente, $this->visita($propiedad, null, $estado))->assertSessionHasErrors('idvisita');
            $this->assertStringContainsString('solo se puede apoyar la reserva en una visita realizada', session('errors')->first('idvisita'));
        }
        $realizada = $this->visita($propiedad);
        $this->reservar($asistente, $realizada, ['idvisita' => 9999])->assertSessionHasErrors(['idvisita' => 'La visita elegida no existe.']);
        $this->assertSame(0, Reserva::count());
        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);
        $this->assertSame(0, MovimientoReserva::count());

        // Sin visita previa sí se puede reservar.
        $this->reservarSinVisita($asistente, $propiedad)->assertSessionHasNoErrors();
        $this->assertNull(Reserva::firstOrFail()->idvisita);
    }

    public function test_se_reserva_sin_visita_previa_eligiendo_la_propiedad_y_el_cliente(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad(['titulo' => 'Casa de Ana'], $ana);
        $cliente = $this->cliente3('Clara Cliente');

        $this->reservarSinVisita($this->asistente(), $propiedad, $cliente)
            ->assertSessionHas('status', 'Reserva registrada: «Casa de Ana» quedó reservada para Clara Cliente hasta el 30/12/2026.');

        $reserva = Reserva::firstOrFail();
        $this->assertNull($reserva->idvisita);
        $this->assertSame($cliente->idcliente, $reserva->idcliente);
        $this->assertSame(Reserva::VIGENTE, $reserva->estado);
        $this->assertSame('RV-2026-000001', $reserva->cobro->comprobante);
        $this->assertSame(Propiedad::RESERVADO, $propiedad->fresh()->estadopropiedad);
    }

    public function test_la_visita_debe_corresponder_a_la_propiedad_y_al_cliente_elegidos(): void
    {
        $asistente = $this->asistente();
        $propiedad = $this->propiedad();
        $visita = $this->visita($propiedad, $this->cliente3('Quien visitó'));

        $this->reservarSinVisita($asistente, $propiedad, $this->cliente3('Otro cliente'), ['idvisita' => $visita->idvisita])
            ->assertSessionHasErrors(['idvisita' => 'Esa visita no corresponde a la propiedad y el cliente elegidos.']);
        $this->reservarSinVisita($asistente, $this->propiedad(), $visita->cliente, ['idvisita' => $visita->idvisita])
            ->assertSessionHasErrors('idvisita');

        $this->assertSame(0, Reserva::count());
    }

    public function test_el_formulario_ofrece_propiedades_disponibles_clientes_y_visitas_realizadas(): void
    {
        $realizada = $this->visita($this->propiedad(['titulo' => 'Propiedad con visita hecha']), $this->cliente3('Cliente con visita'));
        $this->visita($this->propiedad(['titulo' => 'Propiedad con visita pendiente']), null, Visita::PROGRAMADA);
        $this->propiedad(['titulo' => 'Propiedad ya vendida', 'estadopropiedad' => Propiedad::VENDIDO]);
        $this->reservaHecha(null);

        $this->flushSession();
        $respuesta = $this->actingAs($this->asistente())->get(route('reservas.create'))
            ->assertOk()
            ->assertSee('Propiedad con visita hecha')->assertSee('Propiedad con visita pendiente')->assertSee('Cliente con visita')
            ->assertDontSee('Propiedad ya vendida')
            ->assertSee('Visita realizada')->assertSee('Sin visita previa')
            ->assertSee('value="' . $realizada->idvisita . '"', false);

        // La visita pendiente no se ofrece como apoyo (solo las realizadas).
        $this->assertSame(1, substr_count($respuesta->getContent(), 'data-propiedad='));
    }

    public function test_si_no_hay_visitas_realizadas_el_formulario_no_pide_visita(): void
    {
        $this->propiedad(['titulo' => 'Casa sin visitas']);

        $this->actingAs($this->asistente())->get(route('reservas.create'))
            ->assertOk()
            ->assertSee('Casa sin visitas')
            ->assertDontSee('Sin visita previa');
    }

    public function test_el_formulario_llega_con_la_visita_elegida_y_sugiere_el_ultimo_tipo_de_cambio(): void
    {
        $asistente = $this->asistente();
        $this->reservar($asistente, $this->visita($this->propiedad()), ['monto' => '6960', 'moneda' => 'BOB', 'tipocambio' => '6.96']);
        $visita = $this->visita($this->propiedad());

        $html = $this->actingAs($asistente)->get(route('reservas.create', ['visita' => $visita->idvisita]))->getContent();

        $this->assertMatchesRegularExpression('/<option value="' . $visita->idvisita . '"[^>]*selected/s', $html);
        $this->assertMatchesRegularExpression('/<option value="' . $visita->idpropiedad . '"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/<option value="' . $visita->idcliente . '"\s+selected/', $html);
        $this->assertStringContainsString('value="6.96"', $html);
        $this->assertStringContainsString('en efectivo', $html);
    }

    // ============================================ propiedad no disponible

    public function test_no_se_puede_reservar_una_propiedad_que_no_esta_disponible(): void
    {
        $asistente = $this->asistente();

        foreach ([Propiedad::VENDIDO, Propiedad::ALQUILADO, Propiedad::RESERVADO] as $estado) {
            $propiedad = $this->propiedad(['estadopropiedad' => $estado]);

            $this->reservar($asistente, $this->visita($propiedad))
                ->assertSessionHasErrors(['idpropiedad' => 'La propiedad no está disponible (está ' . strtolower($estado) . ').']);
            $this->assertSame($estado, $propiedad->fresh()->estadopropiedad);
        }
        $this->assertSame(0, Reserva::count());
        $this->assertSame(0, MovimientoReserva::count());
    }

    public function test_no_se_puede_reservar_una_propiedad_dada_de_baja(): void
    {
        $this->reservar($this->asistente(), $this->visita($this->propiedad(['estadopropiedad' => Propiedad::BAJA])))
            ->assertSessionHasErrors(['idpropiedad' => 'Esa propiedad fue dada de baja.']);
    }

    public function test_una_segunda_reserva_sobre_una_propiedad_ya_reservada_se_rechaza(): void
    {
        $asistente = $this->asistente();
        $propiedad = $this->propiedad();
        $this->reservar($asistente, $this->visita($propiedad))->assertSessionHasNoErrors();

        $this->reservar($asistente, $this->visita($propiedad, $this->cliente3('Otro cliente')))
            ->assertSessionHasErrors(['idpropiedad' => 'La propiedad ya tiene una reserva vigente.']);

        $this->assertSame(1, Reserva::count());
        $this->assertSame(1, MovimientoReserva::count());
        $this->assertSame(1, Historial::where('idpropiedad', $propiedad->idpropiedad)->count());
    }

    public function test_una_reserva_vigente_bloquea_otra_aunque_la_propiedad_figure_disponible(): void
    {
        $asistente = $this->asistente();
        $propiedad = $this->propiedad();
        $this->reservar($asistente, $this->visita($propiedad));
        $propiedad->update(['estadopropiedad' => Propiedad::DISPONIBLE]); // alguien la dejó disponible a mano con el CU8

        $this->reservar($asistente, $this->visita($propiedad, $this->cliente3('Otro cliente')))
            ->assertSessionHasErrors(['idpropiedad' => 'La propiedad ya tiene una reserva vigente.']);
    }

    // ============================================ plazo y campos obligatorios

    public function test_un_plazo_pasado_o_de_hoy_se_rechaza(): void
    {
        $asistente = $this->asistente();
        $visita = $this->visita($this->propiedad());

        foreach (['2026-12-14', '2026-12-15', '2020-01-01'] as $plazo) {
            $this->reservar($asistente, $visita, ['fechaplazo' => $plazo])
                ->assertSessionHasErrors(['fechaplazo' => 'El plazo de la reserva debe ser una fecha futura.']);
        }
        $this->reservar($asistente, $visita, ['fechaplazo' => '30/12/2026'])->assertSessionHasErrors('fechaplazo');
        $this->assertSame(0, Reserva::count());
        $this->assertSame(0, MovimientoReserva::count());
    }

    public function test_el_plazo_de_manana_se_acepta(): void
    {
        $this->reservar($this->asistente(), $this->visita($this->propiedad()), ['fechaplazo' => '2026-12-16'])->assertSessionHasNoErrors();
    }

    public function test_los_campos_obligatorios_vacios_se_rechazan_y_se_indican(): void
    {
        $this->actingAs($this->asistente())->post(route('reservas.store'), [])
            ->assertSessionHasErrors([
                'idpropiedad' => 'Elige la propiedad que se va a reservar.',
                'idcliente' => 'Elige el cliente que reserva.',
                'monto' => 'Indica cuánto paga el cliente para reservar.',
                'moneda' => 'Elige la moneda del pago.',
                'fechaplazo' => 'Indica la fecha de plazo de la reserva.',
            ])
            ->assertSessionDoesntHaveErrors('idvisita');

        $this->assertSame(0, Reserva::count());
    }

    public function test_un_cliente_que_no_existe_en_el_crm_se_rechaza(): void
    {
        $this->actingAs($this->asistente())->post(route('reservas.store'), [
            'idpropiedad' => $this->propiedad()->idpropiedad, 'idcliente' => 9999,
            'monto' => '1000', 'moneda' => 'USD', 'fechaplazo' => '2026-12-30',
        ])->assertSessionHasErrors(['idcliente' => 'El cliente no existe en el CRM. Regístralo primero como prospecto.']);
    }

    // ============================================ cancelar

    public function test_cancelar_una_reserva_vigente_devuelve_la_propiedad_a_disponible_y_deja_la_devolucion_pendiente(): void
    {
        $asistente = $this->asistente();
        $reserva = $this->reservaHecha();
        $propiedad = $reserva->propiedad;

        $this->cancelar($asistente, $reserva)
            ->assertRedirect(route('reservas.index'))
            ->assertSessionHas('status', "La reserva fue cancelada y «{$propiedad->titulo}» volvió a estar disponible. Falta devolver al cliente USD 800.00 en efectivo.");

        $reserva->refresh();
        $this->assertSame(Reserva::CANCELADA, $reserva->estado);
        $this->assertTrue($reserva->devolucionPendiente());
        $this->assertSame(0, MovimientoReserva::where('tipo', MovimientoReserva::DEVOLUCION)->count(), 'la devolución no es automática: la registra quien entrega el efectivo');
        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);

        $historial = Historial::where('idpropiedad', $propiedad->idpropiedad)->orderByDesc('idhistorial')->first();
        $this->assertSame(['estado' => 'RESERVADO'], json_decode($historial->valoranterior, true));
        $this->assertSame(['estado' => 'DISPONIBLE'], json_decode($historial->valoractual, true));
        $this->assertSame(1, Bitacora::where('accion', 'RESERVA_CANCELADA')->count());
    }

    public function test_despues_de_cancelar_la_propiedad_se_puede_volver_a_reservar(): void
    {
        $asistente = $this->asistente();
        $reserva = $this->reservaHecha();
        $this->cancelar($asistente, $reserva);

        $this->reservar($asistente, $this->visita($reserva->propiedad, $this->cliente3('Otro cliente')))->assertSessionHasNoErrors();

        $this->assertSame(2, Reserva::count());
        $this->assertSame(1, Reserva::vigentes()->count());
    }

    public function test_solo_se_cancelan_las_reservas_vigentes(): void
    {
        $asistente = $this->asistente();

        foreach ([Reserva::CANCELADA, Reserva::CONCRETADA, Reserva::VENCIDA] as $estado) {
            $reserva = $this->reservaHecha();
            $reserva->update(['estado' => $estado]);
            $reserva->propiedad->update(['estadopropiedad' => Propiedad::VENDIDO]);

            $this->cancelar($asistente, $reserva)->assertSessionHas('error');

            $this->assertSame($estado, $reserva->fresh()->estado);
            $this->assertSame(Propiedad::VENDIDO, $reserva->propiedad->fresh()->estadopropiedad);
        }
        $this->assertSame(0, Bitacora::where('accion', 'RESERVA_CANCELADA')->count());
    }

    // ============================================ devolución en efectivo

    public function test_se_devuelve_en_efectivo_todo_menos_el_veinte_por_ciento_con_su_recibo(): void
    {
        $asistente = $this->asistente();
        $reserva = $this->reservaHecha();
        $this->cancelar($asistente, $reserva);

        $respuesta = $this->devolver($asistente, $reserva);

        $devolucion = $reserva->fresh()->devolucion;
        $respuesta->assertRedirect(route('reservas.recibo', $devolucion))
            ->assertSessionHas('status', 'Devolución registrada: USD 800.00 en efectivo para ' . $reserva->cliente->nombre . '.');
        $this->assertSame(MovimientoReserva::DEVOLUCION, $devolucion->tipo);
        $this->assertSame('800.00', (string) $devolucion->monto);
        $this->assertSame('USD', $devolucion->moneda);
        $this->assertSame('800.00', (string) $devolucion->montousd);
        $this->assertSame('20.00', (string) $devolucion->porcentajeretencion);
        $this->assertSame('DV-2026-000001', $devolucion->comprobante);
        $this->assertSame($asistente->idusuario, $devolucion->idusuario);
        $this->assertFalse($reserva->fresh()->devolucionPendiente());
        $this->assertSame(Reserva::CANCELADA, $reserva->fresh()->estado, 'la reserva se conserva');

        $registro = Bitacora::where('accion', 'RESERVA_DEVUELTA')->firstOrFail();
        $this->assertSame('Reservas', $registro->modulo);
        $this->assertSame('800.00', $registro->detalle['monto']);
        $this->assertSame('200.00', $registro->detalle['retenido_usd']);
        $this->assertSame('DV-2026-000001', $registro->detalle['comprobante']);
    }

    public function test_la_devolucion_de_un_pago_en_bolivianos_es_en_bolivianos(): void
    {
        $asistente = $this->asistente();
        $reserva = $this->reservaHecha(null, ['monto' => '6960', 'moneda' => 'BOB', 'tipocambio' => '6.96']);
        $this->cancelar($asistente, $reserva);

        $this->devolver($asistente, $reserva)->assertSessionHasNoErrors();

        $devolucion = $reserva->fresh()->devolucion;
        $this->assertSame('BOB', $devolucion->moneda);
        $this->assertSame('5568.00', (string) $devolucion->monto);
        $this->assertSame('6.9600', (string) $devolucion->tipocambio);
        $this->assertSame('800.00', (string) $devolucion->montousd);
    }

    public function test_el_porcentaje_retenido_sale_de_la_configuracion(): void
    {
        config(['reservas.retencion' => 10]);
        $asistente = $this->asistente();
        $reserva = $this->reservaHecha();
        $this->cancelar($asistente, $reserva);

        $this->devolver($asistente, $reserva);

        $devolucion = $reserva->fresh()->devolucion;
        $this->assertSame('900.00', (string) $devolucion->monto);
        $this->assertSame('10.00', (string) $devolucion->porcentajeretencion);
    }

    public function test_los_centavos_de_la_devolucion_se_redondean_a_dos_decimales(): void
    {
        $asistente = $this->asistente();
        $reserva = $this->reservaHecha(null, ['monto' => '850.55']);
        $this->cancelar($asistente, $reserva);

        $this->devolver($asistente, $reserva);

        $this->assertSame('680.44', (string) $reserva->fresh()->devolucion->monto);
    }

    public function test_una_reserva_vencida_tambien_se_devuelve(): void
    {
        $asistente = $this->asistente();
        $reserva = $this->reservaHecha(null, ['fechaplazo' => '2026-12-16']);
        $this->travelTo(CarbonImmutable::parse('2026-12-20 09:00:00', 'America/La_Paz'));

        $this->actingAs($asistente)->get(route('reservas.index'))->assertSee('Devolución pendiente: USD 800.00');
        $this->assertSame(Reserva::VENCIDA, $reserva->fresh()->estado);

        $this->devolver($asistente, $reserva)->assertSessionHasNoErrors();

        $this->assertSame('800.00', (string) $reserva->fresh()->devolucion->monto);
    }

    public function test_no_se_devuelve_una_reserva_vigente_ni_una_concretada(): void
    {
        $asistente = $this->asistente();

        foreach ([Reserva::VIGENTE, Reserva::CONCRETADA] as $estado) {
            $reserva = $this->reservaHecha();
            $reserva->update(['estado' => $estado]);

            $this->devolver($asistente, $reserva)->assertSessionHas('error');
        }
        $this->assertSame(0, MovimientoReserva::where('tipo', MovimientoReserva::DEVOLUCION)->count());
    }

    public function test_la_devolucion_no_se_registra_dos_veces(): void
    {
        $asistente = $this->asistente();
        $reserva = $this->reservaHecha();
        $this->cancelar($asistente, $reserva);
        $this->devolver($asistente, $reserva)->assertSessionHasNoErrors();

        $this->devolver($asistente, $reserva)->assertSessionHas('error', 'La devolución de esta reserva ya se registró.');

        $this->assertSame(1, MovimientoReserva::where('tipo', MovimientoReserva::DEVOLUCION)->count());
        $this->assertSame(1, Bitacora::where('accion', 'RESERVA_DEVUELTA')->count());
    }

    public function test_una_reserva_sin_cobro_registrado_no_tiene_devolucion(): void
    {
        $reserva = Reserva::create([
            'idcliente' => $this->cliente3()->idcliente, 'idpropiedad' => $this->propiedad()->idpropiedad,
            'fechareserva' => '2026-12-01', 'fechaplazo' => '2026-12-10', 'estado' => Reserva::VENCIDA,
        ]);

        $this->assertFalse($reserva->devolucionPendiente());
        $this->devolver($this->asistente(), $reserva)->assertSessionHas('error');
        $this->assertSame(0, MovimientoReserva::count());
    }

    public function test_un_error_de_la_base_de_datos_al_devolver_no_deja_la_devolucion_a_medias(): void
    {
        $asistente = $this->asistente();
        $reserva = $this->reservaHecha();
        $this->cancelar($asistente, $reserva);
        MovimientoReserva::creating(function () {
            throw new QueryException('sqlite', 'insert into movimientoreserva', [], new \Exception('conexión perdida'));
        });

        $this->devolver($asistente, $reserva)
            ->assertSessionHas('error', 'No se pudo registrar la devolución por un problema con la base de datos. Intenta de nuevo en unos minutos.');

        $this->assertTrue($reserva->fresh()->devolucionPendiente());
    }

    // ============================================ si formaliza, no se devuelve

    public function test_si_el_cliente_formaliza_la_compra_el_dinero_de_la_reserva_es_su_pago_inicial_con_comprobante(): void
    {
        $asistente = $this->asistente();
        $ana = $this->agente();
        $reserva = $this->reservaHecha($ana);

        $this->actingAs($ana)->post(route('contratos.store'), [
            'idpropiedad' => $reserva->idpropiedad, 'idcliente' => $reserva->idcliente,
            'monto' => '85000', 'fechainicio' => '2026-12-20',
        ])->assertSessionHasNoErrors();

        $contrato = Contrato::firstOrFail();
        $this->assertSame(Reserva::CONCRETADA, $reserva->fresh()->estado);
        $this->assertFalse($reserva->fresh()->devolucionPendiente());

        // El mismo monto de la reserva queda como primer pago del contrato, en efectivo, con su comprobante de pago formal (CP).
        $pago = $contrato->pagos()->firstOrFail();
        $this->assertSame(Pago::PAGADO, $pago->estado);
        $this->assertSame(Pago::EFECTIVO, $pago->metodo);
        $this->assertSame('USD', $pago->moneda);
        $this->assertSame('1000.00', (string) $pago->monto);
        $this->assertSame('1000.00', (string) $pago->montousd);
        $this->assertSame('CP-2026-000001', $pago->comprobante);
        $this->assertSame($ana->idusuario, $pago->idusuario);
        $this->assertSame(1000.0, $contrato->totalPagadoUsd());
        $this->assertSame(84000.0, $contrato->saldoUsd());
        $this->assertSame($pago->idpago, Bitacora::where('accion', 'CONTRATO_REGISTRADO')->firstOrFail()->detalle['pago_inicial']['idpago']);

        // El recibo de la reserva (RV) y el comprobante del pago formal (CP) son documentos distintos.
        $this->assertSame('RV-2026-000001', $reserva->cobro->comprobante);

        $this->devolver($asistente, $reserva)->assertSessionHas('error');
        $this->actingAs($asistente)->get(route('reservas.index'))->assertSee('Pasó a ser el pago inicial del contrato');
    }

    public function test_el_pago_inicial_de_una_reserva_en_bolivianos_conserva_la_moneda_y_el_tipo_de_cambio(): void
    {
        $ana = $this->agente();
        $reserva = $this->reservaHecha($ana, ['monto' => '6960', 'moneda' => 'BOB', 'tipocambio' => '6.96']);

        $this->actingAs($ana)->post(route('contratos.store'), [
            'idpropiedad' => $reserva->idpropiedad, 'idcliente' => $reserva->idcliente,
            'monto' => '85000', 'fechainicio' => '2026-12-20',
        ])->assertSessionHasNoErrors();

        $pago = Contrato::firstOrFail()->pagos()->firstOrFail();
        $this->assertSame('BOB', $pago->moneda);
        $this->assertSame('6960.00', (string) $pago->monto);
        $this->assertSame('6.9600', (string) $pago->tipocambio);
        $this->assertSame('1000.00', (string) $pago->montousd);
        $this->assertSame('6.9600', (string) $pago->tipopago->cambiodemonedalocal);
        $this->assertSame('2026-12-15', $pago->tipopago->fecha->format('Y-m-d'), 'el tipo de cambio es el del día en que se entregó el efectivo de la reserva');
    }

    public function test_si_el_pago_inicial_es_igual_al_contrato_queda_pagado_por_completo(): void
    {
        $ana = $this->agente();
        $reserva = $this->reservaHecha($ana, ['monto' => '2000']);

        $this->actingAs($ana)->post(route('contratos.store'), [
            'idpropiedad' => $reserva->idpropiedad, 'idcliente' => $reserva->idcliente,
            'monto' => '2000', 'fechainicio' => '2026-12-20',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0.0, Contrato::firstOrFail()->saldoUsd());
    }

    // ============================================ el listado: activas, historial y permisos

    public function test_las_reservas_devueltas_salen_de_la_lista_activa_pero_se_conservan(): void
    {
        $asistente = $this->asistente();
        $vigente = $this->reservaHecha();
        $pendiente = $this->reservaHecha();
        $devuelta = $this->reservaHecha();
        $this->cancelar($asistente, $pendiente);
        $this->cancelar($asistente, $devuelta);
        $this->devolver($asistente, $devuelta);
        $this->flushSession();

        $this->actingAs($asistente)->get(route('reservas.index'))->assertOk()
            ->assertSee($vigente->propiedad->titulo)->assertSee($pendiente->propiedad->titulo)->assertDontSee($devuelta->propiedad->titulo);

        $this->actingAs($asistente)->get(route('reservas.index', ['estado' => 'CANCELADA']))
            ->assertSee($devuelta->propiedad->titulo)->assertSee('Devuelto: USD 800.00')
            ->assertSee($pendiente->propiedad->titulo);
        $this->assertNotNull(Reserva::find($devuelta->idreserva), 'la reserva no se borra');
        $this->assertSame(3, Reserva::count());
        $this->assertSame(3, MovimientoReserva::where('tipo', MovimientoReserva::COBRO)->count());
    }

    public function test_el_listado_se_filtra_por_estado_e_ignora_filtros_invalidos(): void
    {
        $asistente = $this->asistente();
        $vigente = $this->reservaHecha();
        $cancelada = $this->reservaHecha();
        $this->cancelar($asistente, $cancelada);
        $this->flushSession();

        $this->actingAs($asistente)->get(route('reservas.index', ['estado' => 'VIGENTE']))
            ->assertSee($vigente->propiedad->titulo)->assertDontSee($cancelada->propiedad->titulo);
        $this->actingAs($asistente)->get('/reservas?estado=INVENTADO')->assertOk()->assertSee($vigente->propiedad->titulo);
        $this->actingAs($asistente)->get('/reservas?estado[]=x')->assertOk();
    }

    public function test_sin_reservas_se_muestra_un_mensaje(): void
    {
        $this->actingAs($this->asistente())->get(route('reservas.index'))->assertOk()->assertSee('No hay reservas para mostrar.');
    }

    public function test_el_agente_ve_solo_las_reservas_de_sus_propiedades(): void
    {
        $ana = $this->agente();
        $deAna = $this->reservaHecha($ana);
        $deOtro = $this->reservaHecha($this->agente());
        $this->flushSession();

        $this->actingAs($ana)->get(route('reservas.index'))
            ->assertOk()
            ->assertSee($deAna->propiedad->titulo)->assertDontSee($deOtro->propiedad->titulo)
            ->assertSee('Nueva reserva')->assertSee('Cancelar reserva')->assertSee('Crear contrato')->assertSee('Recibo');

        $this->actingAs($this->admin())->get(route('reservas.index'))
            ->assertSee($deAna->propiedad->titulo)->assertSee($deOtro->propiedad->titulo)->assertSee('Nueva reserva');
    }

    // ============================================ el agente reserva lo suyo; el asistente cubre al agente

    public function test_el_agente_reserva_sus_propiedades_para_sus_prospectos_y_cobra_el_efectivo(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad(['titulo' => 'Casa de Ana'], $ana);
        $prospecto = Cliente::create(['nombre' => 'Prospecto de Ana', 'email' => 'p@correo.com', 'idusuario' => $ana->idusuario]);

        $this->reservarSinVisita($ana, $propiedad, $prospecto)->assertSessionHasNoErrors();

        $reserva = Reserva::firstOrFail();
        $this->assertSame($ana->idusuario, $reserva->idusuario);
        $this->assertSame($ana->idusuario, $reserva->cobro->idusuario, 'el agente que reserva es quien recibe el efectivo');
        $this->assertSame(Propiedad::RESERVADO, $propiedad->fresh()->estadopropiedad);
        $this->actingAs($ana)->get(route('reservas.recibo', $reserva->cobro))->assertOk()->assertSee('RV-2026-000001');
    }

    public function test_el_agente_tambien_puede_apoyar_la_reserva_en_una_visita_realizada(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $prospecto = Cliente::create(['nombre' => 'Prospecto de Ana', 'email' => 'p@correo.com', 'idusuario' => $ana->idusuario]);

        $this->reservar($ana, $this->visita($propiedad, $prospecto))->assertSessionHasNoErrors();

        $this->assertNotNull(Reserva::firstOrFail()->idvisita);
    }

    public function test_el_formulario_del_agente_ofrece_solo_sus_propiedades_y_sus_prospectos(): void
    {
        $ana = $this->agente();
        $this->propiedad(['titulo' => 'Disponible de Ana'], $ana);
        $this->propiedad(['titulo' => 'Disponible de otro'], $this->agente());
        Cliente::create(['nombre' => 'Prospecto de Ana', 'email' => 'a@correo.com', 'idusuario' => $ana->idusuario]);
        Cliente::create(['nombre' => 'Prospecto de otro', 'email' => 'b@correo.com', 'idusuario' => $this->agente()->idusuario]);

        $this->actingAs($ana)->get(route('reservas.create'))
            ->assertOk()
            ->assertSee('Disponible de Ana')->assertSee('Prospecto de Ana')
            ->assertDontSee('Disponible de otro')->assertDontSee('Prospecto de otro');
    }

    public function test_el_agente_no_puede_reservar_una_propiedad_que_no_registro(): void
    {
        $ana = $this->agente();
        $deOtro = $this->propiedad([], $this->agente());

        $this->reservarSinVisita($ana, $deOtro)->assertForbidden();
        // aunque mande datos inválidos, recibe 403 y no un aviso de validación
        $this->actingAs($ana)->post(route('reservas.store'), ['idpropiedad' => $deOtro->idpropiedad])->assertForbidden();

        $this->assertSame(0, Reserva::count());
        $this->assertSame(Propiedad::DISPONIBLE, $deOtro->fresh()->estadopropiedad);
    }

    public function test_el_agente_solo_reserva_para_sus_prospectos(): void
    {
        $ana = $this->agente();
        $ajeno = Cliente::create(['nombre' => 'De otro', 'email' => 'x@correo.com', 'idusuario' => $this->agente()->idusuario]);

        $this->reservarSinVisita($ana, $this->propiedad([], $ana), $ajeno)
            ->assertSessionHasErrors(['idcliente' => 'Ese cliente no está entre tus prospectos.']);

        $this->assertSame(0, Reserva::count());
    }

    public function test_el_agente_cancela_y_devuelve_las_reservas_de_sus_propiedades(): void
    {
        $ana = $this->agente();
        $reserva = $this->reservaHecha($ana);

        $this->cancelar($ana, $reserva)->assertSessionHas('status');
        $this->devolver($ana, $reserva)->assertSessionHasNoErrors();

        $this->assertSame(Reserva::CANCELADA, $reserva->fresh()->estado);
        $this->assertSame($ana->idusuario, $reserva->fresh()->devolucion->idusuario);
        $this->actingAs($ana)->get(route('reservas.recibo', $reserva->fresh()->devolucion))->assertOk()->assertSee('DV-2026-000001');
    }

    public function test_el_agente_no_toca_las_reservas_de_otro_agente(): void
    {
        $ana = $this->agente();
        $deOtro = $this->reservaHecha($this->agente());

        $this->cancelar($ana, $deOtro)->assertForbidden();
        $this->devolver($ana, $deOtro)->assertForbidden();
        $this->actingAs($ana)->get(route('reservas.recibo', $deOtro->cobro))->assertForbidden();

        $this->assertSame(Reserva::VIGENTE, $deOtro->fresh()->estado);
        $this->assertSame(Propiedad::RESERVADO, $deOtro->propiedad->fresh()->estadopropiedad);
        $this->assertSame(0, MovimientoReserva::where('tipo', MovimientoReserva::DEVOLUCION)->count());
    }

    public function test_el_asistente_reserva_cualquier_propiedad_por_el_agente_que_no_puede_hacerlo(): void
    {
        $asistente = $this->asistente();
        $agenteDeVacaciones = $this->agente();
        $propiedad = $this->propiedad([], $agenteDeVacaciones);
        $prospecto = Cliente::create(['nombre' => 'Prospecto del agente', 'email' => 'p@correo.com', 'idusuario' => $agenteDeVacaciones->idusuario]);

        $this->reservarSinVisita($asistente, $propiedad, $prospecto)->assertSessionHasNoErrors();

        $reserva = Reserva::firstOrFail();
        $this->assertSame($asistente->idusuario, $reserva->idusuario);
        $this->assertSame(Propiedad::RESERVADO, $propiedad->fresh()->estadopropiedad);
        // El agente de la propiedad la ve en su lista.
        $this->flushSession();
        $this->actingAs($agenteDeVacaciones)->get(route('reservas.index'))->assertSee($propiedad->titulo);
        // El asistente también cancela y devuelve.
        $this->cancelar($asistente, $reserva)->assertSessionHas('status');
        $this->devolver($asistente, $reserva)->assertSessionHasNoErrors();
    }

    public function test_el_cliente_y_el_invitado_no_pueden_cobrar_cancelar_ni_devolver(): void
    {
        // El invitado primero (más abajo ya hay usuarios con sesión).
        $this->get(route('reservas.index'))->assertRedirect(route('login'));
        $this->post(route('reservas.store'), [])->assertRedirect(route('login'));
        $this->patch(route('reservas.cancelar', 1))->assertRedirect(route('login'));
        $this->post(route('reservas.devolver', 1))->assertRedirect(route('login'));
        $this->get(route('reservas.recibo', 1))->assertRedirect(route('login'));

        $reserva = $this->reservaHecha();
        $cobro = $reserva->cobro;
        $cliente = $this->cliente();

        $this->actingAs($cliente)->get(route('reservas.index'))->assertForbidden();
        $this->actingAs($cliente)->get(route('reservas.create'))->assertForbidden();
        $this->reservarSinVisita($cliente, $this->propiedad())->assertForbidden();
        $this->cancelar($cliente, $reserva)->assertForbidden();
        $this->devolver($cliente, $reserva)->assertForbidden();
        $this->actingAs($cliente)->get(route('reservas.recibo', $cobro))->assertForbidden();

        $this->assertSame(Reserva::VIGENTE, $reserva->fresh()->estado);
        $this->assertSame(1, Reserva::count());
    }

    // ============================================ vencimiento del plazo

    public function test_al_consultar_las_reservas_vencidas_se_marcan_y_la_propiedad_vuelve_a_disponible(): void
    {
        $asistente = $this->asistente();
        $reserva = $this->reservaHecha(null, ['fechaplazo' => '2026-12-16']);
        $propiedad = $reserva->propiedad;
        $this->travelTo(CarbonImmutable::parse('2026-12-17 08:00:00', 'America/La_Paz'));

        $this->actingAs($asistente)->get(route('reservas.index'))->assertOk()->assertSee('Vencida');

        $this->assertSame(Reserva::VENCIDA, $reserva->fresh()->estado);
        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);
        $historial = Historial::where('idpropiedad', $propiedad->idpropiedad)->orderByDesc('idhistorial')->first();
        $this->assertSame(['estado' => 'DISPONIBLE'], json_decode($historial->valoractual, true));
        $this->assertStringContainsString('Venció el plazo', $historial->motivo);
        $this->assertSame(1, Bitacora::where('accion', 'RESERVA_VENCIDA')->count());
        $this->assertTrue($reserva->fresh()->devolucionPendiente());
    }

    public function test_una_reserva_con_plazo_de_hoy_sigue_vigente(): void
    {
        $reserva = $this->reservaHecha(null, ['fechaplazo' => '2026-12-16']);
        $this->travelTo(CarbonImmutable::parse('2026-12-16 20:00:00', 'America/La_Paz'));

        $this->actingAs($this->asistente())->get(route('reservas.index'));

        $this->assertSame(Reserva::VIGENTE, $reserva->fresh()->estado);
        $this->assertSame(Propiedad::RESERVADO, $reserva->propiedad->fresh()->estadopropiedad);
    }

    public function test_al_intentar_reservar_una_propiedad_con_reserva_vencida_se_libera_y_se_acepta_la_nueva(): void
    {
        $asistente = $this->asistente();
        $vieja = $this->reservaHecha(null, ['fechaplazo' => '2026-12-16']);
        $this->travelTo(CarbonImmutable::parse('2026-12-20 09:00:00', 'America/La_Paz'));

        $this->reservar($asistente, $this->visita($vieja->propiedad, $this->cliente3('Nuevo cliente')), ['fechaplazo' => '2027-01-10'])
            ->assertSessionHasNoErrors();

        $this->assertSame(Reserva::VENCIDA, $vieja->fresh()->estado);
        $this->assertTrue($vieja->fresh()->devolucionPendiente(), 'a la anterior todavía se le debe su devolución');
        $this->assertSame(1, Reserva::vigentes()->count());
    }

    public function test_el_vencimiento_no_toca_una_propiedad_que_ya_no_esta_reservada(): void
    {
        $reserva = $this->reservaHecha(null, ['fechaplazo' => '2026-12-16']);
        $reserva->propiedad->update(['estadopropiedad' => Propiedad::VENDIDO]);
        $historiales = Historial::count();
        $this->travelTo(CarbonImmutable::parse('2026-12-20 09:00:00', 'America/La_Paz'));

        $this->actingAs($this->asistente())->get(route('reservas.index'));

        $this->assertSame(Reserva::VENCIDA, $reserva->fresh()->estado);
        $this->assertSame(Propiedad::VENDIDO, $reserva->propiedad->fresh()->estadopropiedad);
        $this->assertSame($historiales, Historial::count());
    }

    // ============================================ recibos

    public function test_el_recibo_del_cobro_muestra_los_datos_y_es_imprimible(): void
    {
        $asistente = $this->asistente(['nombre' => 'Sara Asistente']);
        $reserva = $this->reservaHecha(null, ['monto' => '6960', 'moneda' => 'BOB', 'tipocambio' => '6.96']);
        $reserva->cobro->update(['idusuario' => $asistente->idusuario]);

        $this->actingAs($asistente)->get(route('reservas.recibo', $reserva->cobro))
            ->assertOk()
            ->assertSee('Recibo de reserva')->assertSee('RV-2026-000001')
            ->assertSee($reserva->cliente->nombre)->assertSee($reserva->propiedad->titulo)
            ->assertSee('Efectivo')->assertSee('BOB 6,960.00')->assertSee('6.96 Bs por USD')->assertSee('USD 1,000.00')
            ->assertSee('Sara Asistente')->assertSee('window.print()', false)->assertSee('Firma del cliente');
    }

    public function test_el_recibo_de_la_devolucion_muestra_lo_devuelto_y_lo_retenido(): void
    {
        $asistente = $this->asistente();
        $reserva = $this->reservaHecha();
        $this->cancelar($asistente, $reserva);
        $this->devolver($asistente, $reserva);

        $this->actingAs($asistente)->get(route('reservas.recibo', $reserva->fresh()->devolucion))
            ->assertOk()
            ->assertSee('Recibo de devolución de reserva')->assertSee('DV-2026-000001')
            ->assertSee('USD 800.00')->assertSee('Retenido por la empresa')->assertSee('USD 200.00')->assertSee('20 %')
            ->assertSee('se canceló');
    }

    public function test_un_recibo_inexistente_da_404(): void
    {
        $this->actingAs($this->asistente())->get('/reservas/recibos/9999')->assertNotFound();
    }

    // ============================================ errores y panel

    public function test_un_error_de_la_base_de_datos_al_reservar_no_deja_la_reserva_ni_el_cobro_a_medias(): void
    {
        $propiedad = $this->propiedad();
        $visita = $this->visita($propiedad);
        MovimientoReserva::creating(function () {
            throw new QueryException('sqlite', 'insert into movimientoreserva', [], new \Exception('conexión perdida'));
        });

        $this->reservar($this->asistente(), $visita)
            ->assertSessionHas('error', 'No se pudo registrar la reserva por un problema con la base de datos. Intenta de nuevo en unos minutos.');

        $this->assertSame(0, Reserva::count());
        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);
    }

    public function test_el_panel_lista_el_cu26_para_el_agente_el_administrador_y_el_asistente(): void
    {
        foreach ([$this->agente(), $this->admin(), $this->asistente()] as $usuario) {
            $this->actingAs($usuario)->get(route('panel'))
                ->assertSee('Gestionar reservas de propiedad')
                ->assertSee(route('reservas.index'), false);
        }
    }
}
