<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Historial;
use App\Models\Pago;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Rol;
use App\Models\TipoPago;
use App\Models\Usuario;
use App\Models\Visita;
use App\Services\InstantaneaDatos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/** Tablas y modelos del Ciclo 3: visita, reserva, contrato, pago, tipopago y la cuenta del cliente. */
class ModelosCiclo3Test extends SistemaTestCase
{
    private function cliente3(array $datos = []): Cliente
    {
        return Cliente::create(array_merge(['nombre' => 'Carla Rojas', 'email' => 'carla@test.com', 'telefono' => '70011122'], $datos));
    }

    private function visita(?Propiedad $propiedad = null, ?Cliente $cliente = null, array $datos = []): Visita
    {
        return Visita::create(array_merge([
            'idpropiedad' => ($propiedad ?? $this->propiedad())->idpropiedad,
            'idcliente' => ($cliente ?? $this->cliente3())->idcliente,
            'fecha' => '2026-12-10',
            'hora' => '09:00',
        ], $datos));
    }

    private function contrato(array $datos = [], ?Propiedad $propiedad = null, ?Cliente $cliente = null): Contrato
    {
        return Contrato::create(array_merge([
            'idpropiedad' => ($propiedad ?? $this->propiedad())->idpropiedad,
            'idcliente' => ($cliente ?? $this->cliente3())->idcliente,
            'tipocontrato' => 'Venta',
            'monto' => 1000,
            'fechainicio' => '2026-12-01',
        ], $datos));
    }

    private function pago(Contrato $contrato, array $datos = []): Pago
    {
        return Pago::create(array_merge([
            'idcontrato' => $contrato->idcontrato,
            'monto' => 100,
            'montousd' => 100,
            'moneda' => Pago::USD,
            'fechapago' => '2026-12-02 10:00:00',
            'estado' => Pago::PAGADO,
        ], $datos));
    }

    // ------------------------------------------------------------------ tablas

    public function test_las_tablas_nuevas_tienen_las_columnas_del_documento_y_las_extra_del_equipo(): void
    {
        $this->assertTrue(Schema::hasColumns('visita', ['idvisita', 'idpropiedad', 'idcliente', 'idhistorial', 'fecha', 'hora', 'estado', 'observacion', 'motivo']));
        $this->assertTrue(Schema::hasColumns('reserva', ['idreserva', 'idcliente', 'idpropiedad', 'fechareserva', 'fechaplazo', 'estado', 'idusuario']));
        $this->assertTrue(Schema::hasColumns('contrato', ['idcontrato', 'idpropiedad', 'idcliente', 'idusuario', 'idreserva', 'tipocontrato', 'monto', 'fechainicio', 'fechafin', 'estado']));
        $this->assertTrue(Schema::hasColumns('pago', [
            'idpago', 'idcontrato', 'monto', 'moneda', 'tipocambio', 'fechapago', 'comprobante', 'estado',
            'montousd', 'metodo', 'idusuario', 'stripe_session_id', 'stripe_payment_intent',
        ]));
        $this->assertTrue(Schema::hasColumns('tipopago', ['idpago', 'cambiodemonedalocal', 'fecha']));
        $this->assertTrue(Schema::hasColumn('cliente', 'idusuariocuenta'));
    }

    public function test_los_valores_por_defecto_de_las_tablas_nuevas(): void
    {
        $visita = $this->visita()->fresh();
        $reserva = Reserva::create([
            'idcliente' => $this->cliente3()->idcliente,
            'idpropiedad' => $this->propiedad()->idpropiedad,
            'fechaplazo' => '2026-12-31',
        ])->fresh();
        $contrato = $this->contrato()->fresh();
        $pago = Pago::create([
            'idcontrato' => $contrato->idcontrato, 'monto' => 50, 'montousd' => 50,
            'moneda' => Pago::USD, 'fechapago' => '2026-12-02 10:00:00',
        ])->fresh();

        $this->assertSame(Visita::PROGRAMADA, $visita->estado);
        $this->assertNull($visita->idhistorial);
        $this->assertNull($visita->motivo);
        $this->assertSame(Reserva::VIGENTE, $reserva->estado);
        $this->assertNotNull($reserva->fechareserva);
        $this->assertNull($reserva->idusuario);
        $this->assertSame(Contrato::VIGENTE, $contrato->estado);
        $this->assertNull($contrato->fechafin);
        $this->assertSame(Pago::PENDIENTE, $pago->estado);
        $this->assertSame(Pago::EFECTIVO, $pago->metodo);
        $this->assertNull($pago->comprobante);
        $this->assertNull($pago->tipocambio);
    }

    public function test_los_montos_se_manejan_con_dos_decimales_y_el_tipo_de_cambio_con_cuatro(): void
    {
        $contrato = $this->contrato(['monto' => 85000.5])->fresh();
        $pago = $this->pago($contrato, ['monto' => 696, 'moneda' => Pago::BOB, 'tipocambio' => 6.96, 'montousd' => 100])->fresh();

        $this->assertSame('85000.50', (string) $contrato->monto);
        $this->assertSame('696.00', (string) $pago->monto);
        $this->assertSame('6.9600', (string) $pago->tipocambio);
        $this->assertSame('100.00', (string) $pago->montousd);
    }

    // ------------------------------------------------------------------ visita

    public function test_la_visita_se_relaciona_con_propiedad_cliente_e_historial(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $historial = Historial::create(['tipo' => Historial::MODIFICACION, 'idpropiedad' => $propiedad->idpropiedad]);
        $visita = $this->visita($propiedad, null, ['idhistorial' => $historial->idhistorial])->fresh(['propiedad.agente', 'cliente', 'historial']);

        $this->assertSame($propiedad->idpropiedad, $visita->propiedad->idpropiedad);
        $this->assertSame($agente->idusuario, $visita->propiedad->agente->idusuario); // el agente responsable es el de la propiedad
        $this->assertSame('Carla Rojas', $visita->cliente->nombre);
        $this->assertSame($historial->idhistorial, $visita->historial->idhistorial);
        $this->assertCount(1, $propiedad->visitas);
        $this->assertCount(1, $visita->cliente->visitas);
    }

    public function test_solo_las_visitas_programadas_y_reagendadas_ocupan_horario(): void
    {
        $propiedad = $this->propiedad();
        $cliente = $this->cliente3();
        foreach ([Visita::PROGRAMADA, Visita::REAGENDADA, Visita::CANCELADA, Visita::REALIZADA] as $i => $estado) {
            $this->visita($propiedad, $cliente, ['estado' => $estado, 'hora' => sprintf('%02d:00', 9 + $i)]);
        }

        $this->assertEqualsCanonicalizing(
            [Visita::PROGRAMADA, Visita::REAGENDADA],
            Visita::activas()->pluck('estado')->all()
        );
        $this->assertTrue(Visita::where('estado', Visita::REAGENDADA)->first()->estaActiva());
        $this->assertFalse(Visita::where('estado', Visita::CANCELADA)->first()->estaActiva());
        $this->assertFalse(Visita::where('estado', Visita::REALIZADA)->first()->estaActiva());
    }

    public function test_la_visita_muestra_la_hora_sin_segundos_y_la_etiqueta_del_estado(): void
    {
        $visita = $this->visita(null, null, ['hora' => '14:30', 'estado' => Visita::REAGENDADA])->fresh();

        $this->assertSame('14:30', $visita->hora_corta);
        $this->assertSame('Reagendada', $visita->estado_etiqueta);
        $this->assertSame('2026-12-10', $visita->fecha->format('Y-m-d'));
    }

    // ----------------------------------------------------------------- reserva

    public function test_la_reserva_se_relaciona_con_cliente_propiedad_agente_y_contrato(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $cliente = $this->cliente3();
        $reserva = Reserva::create([
            'idcliente' => $cliente->idcliente, 'idpropiedad' => $propiedad->idpropiedad,
            'fechaplazo' => '2026-12-31', 'idusuario' => $agente->idusuario,
        ]);
        $this->assertNull($reserva->contrato);

        $contrato = $this->contrato(['idreserva' => $reserva->idreserva], $propiedad, $cliente);
        $reserva = $reserva->fresh(['cliente', 'propiedad', 'agente', 'contrato']);

        $this->assertSame($cliente->idcliente, $reserva->cliente->idcliente);
        $this->assertSame($propiedad->idpropiedad, $reserva->propiedad->idpropiedad);
        $this->assertSame($agente->idusuario, $reserva->agente->idusuario);
        $this->assertSame($contrato->idcontrato, $reserva->contrato->idcontrato);
        $this->assertSame($reserva->idreserva, $contrato->fresh('reserva')->reserva->idreserva);
        $this->assertCount(1, $propiedad->reservas);
        $this->assertCount(1, $cliente->reservas);
    }

    public function test_el_alcance_vigentes_de_reservas_excluye_las_demas(): void
    {
        $cliente = $this->cliente3();
        foreach ([Reserva::VIGENTE, Reserva::CANCELADA, Reserva::CONCRETADA, Reserva::VENCIDA] as $estado) {
            Reserva::create([
                'idcliente' => $cliente->idcliente, 'idpropiedad' => $this->propiedad()->idpropiedad,
                'fechaplazo' => '2026-12-31', 'estado' => $estado,
            ]);
        }

        $this->assertSame([Reserva::VIGENTE], Reserva::vigentes()->pluck('estado')->all());
    }

    // ---------------------------------------------------------------- contrato

    public function test_el_contrato_se_relaciona_con_propiedad_cliente_agente_y_pagos(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $cliente = $this->cliente3();
        $contrato = $this->contrato(['idusuario' => $agente->idusuario], $propiedad, $cliente);
        $pago = $this->pago($contrato);

        $contrato = $contrato->fresh(['propiedad', 'cliente', 'agente', 'pagos']);
        $this->assertSame($propiedad->idpropiedad, $contrato->propiedad->idpropiedad);
        $this->assertSame($cliente->idcliente, $contrato->cliente->idcliente);
        $this->assertSame($agente->idusuario, $contrato->agente->idusuario);
        $this->assertSame([$pago->idpago], $contrato->pagos->pluck('idpago')->all());
        $this->assertSame($contrato->idcontrato, $pago->fresh('contrato')->contrato->idcontrato);
        $this->assertCount(1, $propiedad->contratos);
        $this->assertCount(1, $cliente->contratos);
    }

    public function test_el_saldo_del_contrato_es_el_monto_menos_los_pagos_pagados_en_usd(): void
    {
        $contrato = $this->contrato(['monto' => 1000]);

        $this->assertSame(0.0, $contrato->totalPagadoUsd());
        $this->assertSame(1000.0, $contrato->saldoUsd());

        $this->pago($contrato, ['monto' => 300, 'montousd' => 300]);
        // Un pago en bolivianos descuenta su equivalente en dólares, no los bolivianos.
        $this->pago($contrato, ['monto' => 696, 'moneda' => Pago::BOB, 'tipocambio' => 6.96, 'montousd' => 100]);
        // Los pendientes y los anulados no descuentan.
        $this->pago($contrato, ['monto' => 200, 'montousd' => 200, 'estado' => Pago::PENDIENTE]);
        $this->pago($contrato, ['monto' => 150, 'montousd' => 150, 'estado' => Pago::ANULADO]);

        $this->assertSame(400.0, $contrato->totalPagadoUsd());
        $this->assertSame(600.0, $contrato->saldoUsd());
    }

    public function test_el_saldo_no_acumula_errores_de_redondeo(): void
    {
        $contrato = $this->contrato(['monto' => 0.30]);
        $this->pago($contrato, ['monto' => 0.10, 'montousd' => 0.10]);
        $this->pago($contrato, ['monto' => 0.20, 'montousd' => 0.20]);

        $this->assertSame(0.30, $contrato->totalPagadoUsd());
        $this->assertSame(0.0, $contrato->saldoUsd());
    }

    public function test_el_alcance_vigentes_de_contratos_excluye_los_demas(): void
    {
        $this->contrato();
        $this->contrato(['estado' => Contrato::FINALIZADO]);
        $this->contrato(['estado' => Contrato::RESCINDIDO]);

        $this->assertSame([Contrato::VIGENTE], Contrato::vigentes()->pluck('estado')->all());
    }

    public function test_una_propiedad_con_contratos_no_se_puede_borrar(): void
    {
        $contrato = $this->contrato();

        $this->expectException(\Illuminate\Database\QueryException::class);
        Propiedad::whereKey($contrato->idpropiedad)->delete();
    }

    // -------------------------------------------------------------------- pago

    public function test_el_numero_de_comprobante_no_se_puede_repetir_pero_varios_pagos_pueden_no_tenerlo(): void
    {
        $contrato = $this->contrato();
        $this->pago($contrato, ['comprobante' => null]);
        $this->pago($contrato, ['comprobante' => null]);
        $this->pago($contrato, ['comprobante' => 'CP-2026-000001']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->pago($contrato, ['comprobante' => 'CP-2026-000001']);
    }

    public function test_la_sesion_de_stripe_no_se_puede_repetir_en_dos_pagos(): void
    {
        $contrato = $this->contrato();
        $this->pago($contrato, ['stripe_session_id' => 'cs_test_1', 'metodo' => Pago::TARJETA]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->pago($contrato, ['stripe_session_id' => 'cs_test_1', 'metodo' => Pago::TARJETA]);
    }

    public function test_el_pago_tiene_un_solo_tipo_de_pago_con_el_tipo_de_cambio(): void
    {
        $usuario = $this->asistente();
        $pago = $this->pago($this->contrato(), ['moneda' => Pago::BOB, 'tipocambio' => 6.96, 'idusuario' => $usuario->idusuario]);
        $tipo = TipoPago::create(['idpago' => $pago->idpago, 'cambiodemonedalocal' => 6.96, 'fecha' => '2026-12-02']);

        $pago = $pago->fresh(['tipopago', 'usuario']);
        $this->assertSame('6.9600', (string) $pago->tipopago->cambiodemonedalocal);
        $this->assertSame('2026-12-02', $pago->tipopago->fecha->format('Y-m-d'));
        $this->assertSame($pago->idpago, $tipo->fresh('pago')->pago->idpago);
        $this->assertSame($usuario->idusuario, $pago->usuario->idusuario);

        $this->expectException(\Illuminate\Database\QueryException::class);
        TipoPago::create(['idpago' => $pago->idpago, 'cambiodemonedalocal' => 7, 'fecha' => '2026-12-03']);
    }

    public function test_al_borrar_un_pago_se_borra_su_tipo_de_pago_pero_el_contrato_protege_a_sus_pagos(): void
    {
        $contrato = $this->contrato();
        $pago = $this->pago($contrato);
        TipoPago::create(['idpago' => $pago->idpago, 'cambiodemonedalocal' => 6.96, 'fecha' => '2026-12-02']);

        $pago->delete();
        $this->assertSame(0, TipoPago::count());

        $this->pago($contrato);
        $this->expectException(\Illuminate\Database\QueryException::class);
        $contrato->delete();
    }

    public function test_las_constantes_de_estado_y_metodo_del_pago(): void
    {
        $this->assertSame([Pago::USD, Pago::BOB], Pago::MONEDAS);
        $this->assertSame('Tarjeta', $this->pago($this->contrato(), ['metodo' => Pago::TARJETA])->fresh()->metodo_etiqueta);
        $this->assertSame('Anulado', $this->pago($this->contrato(), ['estado' => Pago::ANULADO])->fresh()->estado_etiqueta);
    }

    // ------------------------------------------------------------------ cliente

    public function test_el_cliente_puede_tener_cuenta_de_acceso_y_empieza_sin_ella(): void
    {
        $cliente = $this->cliente3();
        $this->assertNull($cliente->fresh()->idusuariocuenta);
        $this->assertNull($cliente->cuenta);

        $cuenta = $this->cliente(['email' => 'carla@test.com']);
        $cliente->update(['idusuariocuenta' => $cuenta->idusuario]);

        $this->assertSame($cuenta->idusuario, $cliente->fresh('cuenta')->cuenta->idusuario);
        $this->assertSame($cliente->idcliente, $cuenta->clienteAsociado->idcliente);
        $this->assertTrue($cuenta->tieneRol(Rol::CLIENTE));
    }

    public function test_si_se_borra_la_cuenta_el_cliente_se_conserva(): void
    {
        $cuenta = $this->cliente();
        $cliente = $this->cliente3(['idusuariocuenta' => $cuenta->idusuario]);

        Usuario::whereKey($cuenta->idusuario)->delete();

        $this->assertNotNull(Cliente::find($cliente->idcliente));
        $this->assertNull($cliente->fresh()->idusuariocuenta);
    }

    // ------------------------------------------------------- datos compartidos

    public function test_las_tablas_nuevas_entran_en_los_datos_compartidos_en_orden_de_dependencia(): void
    {
        $tablas = (new InstantaneaDatos())->tablas();
        $pos = array_flip($tablas);

        foreach (['visita', 'reserva', 'contrato', 'pago', 'tipopago'] as $tabla) {
            $this->assertArrayHasKey($tabla, $pos, $tabla);
        }
        $this->assertGreaterThan($pos['cliente'], $pos['visita']);
        $this->assertGreaterThan($pos['historial'], $pos['visita']);
        $this->assertGreaterThan($pos['cliente'], $pos['reserva']);
        $this->assertGreaterThan($pos['reserva'], $pos['contrato']);
        $this->assertGreaterThan($pos['contrato'], $pos['pago']);
        $this->assertGreaterThan($pos['pago'], $pos['tipopago']);
    }

    public function test_exportar_y_volver_a_cargar_conserva_visitas_reservas_contratos_y_pagos(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $cuenta = $this->cliente(['email' => 'carla@test.com']);
        $cliente = $this->cliente3(['idusuariocuenta' => $cuenta->idusuario]);
        $this->visita($propiedad, $cliente, ['motivo' => 'Prueba']);
        $reserva = Reserva::create(['idcliente' => $cliente->idcliente, 'idpropiedad' => $propiedad->idpropiedad, 'fechaplazo' => '2026-12-31']);
        $contrato = $this->contrato(['idreserva' => $reserva->idreserva, 'monto' => 1234.56], $propiedad, $cliente);
        $pago = $this->pago($contrato, ['comprobante' => 'CP-2026-000001', 'moneda' => Pago::BOB, 'tipocambio' => 6.96, 'monto' => 696, 'montousd' => 100]);
        TipoPago::create(['idpago' => $pago->idpago, 'cambiodemonedalocal' => 6.96, 'fecha' => '2026-12-02']);

        $temporal = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lit_datos_' . uniqid();
        $servicio = new InstantaneaDatos($temporal . '/datos.json', $temporal . '/uploads');

        $resultado = $servicio->exportar();
        foreach (['visita', 'reserva', 'contrato', 'pago', 'tipopago'] as $tabla) {
            $this->assertSame(1, $resultado['tablas'][$tabla], $tabla);
        }

        foreach (array_reverse($servicio->tablas()) as $tabla) {
            DB::table($tabla)->delete();
        }
        $servicio->importar();

        $this->assertSame('1234.56', (string) Contrato::first()->monto);
        $this->assertSame('CP-2026-000001', Pago::first()->comprobante);
        $this->assertSame('6.9600', (string) TipoPago::first()->cambiodemonedalocal);
        $this->assertSame($cuenta->idusuario, Cliente::first()->idusuariocuenta);
        $this->assertSame('Prueba', Visita::first()->motivo);

        File::deleteDirectory($temporal);
    }

    // ---------------------------------------------------------------- migración

    public function test_las_migraciones_nuevas_se_pueden_revertir_y_volver_a_aplicar(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 6])->assertSuccessful();

        foreach (['visita', 'reserva', 'contrato', 'pago', 'tipopago'] as $tabla) {
            $this->assertFalse(Schema::hasTable($tabla), $tabla);
        }
        $this->assertFalse(Schema::hasColumn('cliente', 'idusuariocuenta'));
        $this->assertTrue(Schema::hasTable('propiedad')); // lo anterior no se toca
        $this->assertTrue(Schema::hasColumns('cliente', ['idcliente', 'nombre', 'email', 'telefono', 'estadoseguimiento', 'idusuario']));

        $this->artisan('migrate')->assertSuccessful();

        $this->assertTrue(Schema::hasTable('pago'));
        $this->assertTrue(Schema::hasColumn('cliente', 'idusuariocuenta'));
    }
}
