<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use App\Models\Usuario;
use App\Models\Visita;
use App\Modules\GestionPropiedades\Compartido\LimiteCuartoAnillo;
use App\Modules\ReportesAdministracion\CU20_GenerarReportes\ConstructorDeReportes;
use App\Modules\ReportesAdministracion\CU20_GenerarReportes\CriteriosDeReporte;
use App\Support\DatosDemo\MarcasDemo;
use App\Support\DatosDemo\PlanDeDatosDemo;
use App\Support\DatosDemo\Ref;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Datos ficticios para probar los reportes: php artisan demo:poblar-reportes (con --fresh, --quitar y --sql).
 * La fecha de las pruebas se fija en el 21/11/2026 (hora de Bolivia), igual que la referencia de los datos.
 */
class DatosDemoReportesTest extends SistemaTestCase
{
    private const TABLAS = ['usuario', 'rol', 'propietario', 'categoria', 'ubicacion', 'propiedad', 'historial', 'cliente', 'interes', 'visita',
        'reserva', 'movimientoreserva', 'contrato', 'pago', 'tipopago', 'seguimiento', 'inquilino', 'resena', 'imagen', 'bitacora'];

    private const CLAVES = ['propietario' => 'idpropietario', 'ubicacion' => 'idubicacion', 'propiedad' => 'idpropiedad', 'cliente' => 'idcliente',
        'reserva' => 'idreserva', 'contrato' => 'idcontrato', 'pago' => 'idpago', 'historial' => 'idhistorial', 'visita' => 'idvisita',
        'interes' => 'idinteres', 'movimientoreserva' => 'idmovimiento', 'seguimiento' => 'idseguimiento', 'inquilino' => 'idinquilino',
        'resena' => 'idresena', 'usuario' => 'idusuario', 'rol' => 'idrol', 'categoria' => 'idcategoria', 'tipopago' => 'idpago', 'imagen' => 'idimagen', 'bitacora' => 'idbitacora'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-21 12:00:00', 'America/La_Paz'));

        foreach (['Departamento', 'Casa', 'Oficina', 'Terreno', 'Local Comercial'] as $categoria) {
            $this->categoria($categoria);
        }
        $this->admin();
        $this->asistente();
        // Cinco agentes, como en la base real: cada posición del plan cae en un agente distinto.
        foreach (['Uno', 'Dos', 'Tres', 'Cuatro', 'Cinco'] as $numero) {
            $this->agente(['nombre' => "Agente {$numero}"]);
        }
    }

    /** Algunos datos "reales" que el comando no debe tocar. */
    private function datosReales(): void
    {
        $agente = Usuario::where('nombre', 'Agente Uno')->firstOrFail();
        $propiedad = $this->propiedad(['titulo' => 'Casa real de la inmobiliaria'], $agente);
        $cliente = Cliente::create(['nombre' => 'Cliente Real', 'email' => 'real@correo.com', 'telefono' => '70000001', 'idusuario' => $agente->idusuario]);
        $contrato = Contrato::create(['idpropiedad' => $propiedad->idpropiedad, 'idcliente' => $cliente->idcliente, 'tipocontrato' => 'Venta', 'monto' => 5000, 'fechainicio' => '2026-11-01']);
        Pago::create(['idcontrato' => $contrato->idcontrato, 'monto' => 100, 'montousd' => 100, 'moneda' => 'USD', 'fechapago' => '2026-11-02 10:00:00',
            'estado' => Pago::PAGADO, 'comprobante' => 'CP-2026-000007', 'metodo' => Pago::EFECTIVO]);
    }

    private function conteos(): array
    {
        return collect(self::TABLAS)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();
    }

    /** Un resumen exacto del contenido de las tablas (para comprobar que no se modificó ninguna fila). */
    private function huellas(): array
    {
        return collect(self::TABLAS)->mapWithKeys(fn ($t) => [$t => md5(DB::table($t)->orderBy(self::CLAVES[$t])->get()->toJson())])->all();
    }

    private function poblar(array $opciones = []): void
    {
        $this->artisan('demo:poblar-reportes', ['--hoy' => '2026-11-21'] + $opciones)->assertSuccessful();
    }

    private function demoProps(): \Illuminate\Support\Collection
    {
        return DB::table('propiedad')->join('propietario', 'propietario.idpropietario', '=', 'propiedad.idpropietario')
            ->where('propietario.nombre', 'like', MarcasDemo::PREFIJO_PROPIETARIO . '%')->select('propiedad.*')->get();
    }

    private function demoClientes(): \Illuminate\Support\Collection
    {
        return DB::table('cliente')->where('email', 'like', '%' . MarcasDemo::DOMINIO_CLIENTE)->get();
    }

    // ---------------------------------------------------------------------------------------------- creación

    public function test_el_comando_crea_los_datos_demo_con_las_cantidades_esperadas(): void
    {
        $this->datosReales();
        $antes = $this->conteos();

        $this->poblar();

        $nuevos = fn (string $t) => DB::table($t)->count() - $antes[$t];
        $this->assertSame(3, DB::table('propietario')->where('nombre', 'like', 'DEMO - %')->count());
        $this->assertBetween(12, 15, $this->demoProps()->count());
        $this->assertBetween(30, 40, $this->demoClientes()->count());
        $this->assertBetween(60, 90, $nuevos('seguimiento'));
        $this->assertBetween(6, 8, $nuevos('inquilino'));
        $this->assertBetween(40, 60, $nuevos('visita'));
        $this->assertBetween(8, 12, $nuevos('reserva'));
        $this->assertBetween(10, 14, $nuevos('contrato'));
        $this->assertBetween(5, 8, $nuevos('resena'));
        $this->assertGreaterThan(20, $nuevos('pago'));
        $this->assertGreaterThan(0, $nuevos('interes'));

        // Marcados como demo, sin cuenta de acceso, con títulos creíbles y sin tocar los usuarios.
        $this->assertSame(0, $this->demoClientes()->whereNotNull('idusuariocuenta')->count());
        $this->assertSame($antes['usuario'], DB::table('usuario')->count());
        $this->assertSame($antes['bitacora'], DB::table('bitacora')->count());
        foreach ($this->demoProps() as $p) {
            $this->assertStringNotContainsStringIgnoringCase('demo', $p->titulo);
            $this->assertTrue(LimiteCuartoAnillo::contiene((float) DB::table('ubicacion')->where('idubicacion', $p->idubicacion)->value('latitud'),
                (float) DB::table('ubicacion')->where('idubicacion', $p->idubicacion)->value('longitud')), 'Fuera del 4.º anillo: ' . $p->titulo);
        }
        // Repartidas entre varios agentes, categorías y estados
        $this->assertGreaterThanOrEqual(3, $this->demoProps()->pluck('idusuario')->unique()->count());
        $this->assertGreaterThanOrEqual(4, $this->demoProps()->pluck('idcategoria')->unique()->count());
        $this->assertEqualsCanonicalizing(['DISPONIBLE', 'RESERVADO', 'VENDIDO', 'ALQUILADO'], $this->demoProps()->pluck('estadopropiedad')->unique()->values()->all());
        $this->assertGreaterThanOrEqual(3, $this->demoClientes()->pluck('idusuario')->unique()->count());
    }

    public function test_el_plan_es_reproducible_con_semilla_fija(): void
    {
        $uno = (new PlanDeDatosDemo(CarbonImmutable::parse('2026-11-21')))->construir();
        $dos = (new PlanDeDatosDemo(CarbonImmutable::parse('2026-11-21')))->construir();

        $this->assertSame(serialize($uno), serialize($dos));
        $this->assertNotSame(serialize($uno), serialize((new PlanDeDatosDemo(CarbonImmutable::parse('2026-11-21'), 7))->construir()));
    }

    public function test_es_idempotente_y_fresh_recrea_los_datos_sin_duplicar(): void
    {
        $this->poblar();
        $despues = $this->conteos();

        $this->artisan('demo:poblar-reportes', ['--hoy' => '2026-11-21'])
            ->expectsOutputToContain('Ya existen datos demo')->assertSuccessful();
        $this->assertSame($despues, $this->conteos(), 'Sin --fresh no debe duplicar nada.');

        $this->poblar(['--fresh' => true]);
        $this->assertSame($despues, $this->conteos(), 'Con --fresh queda lo mismo que antes, sin duplicados.');
        $this->assertSame(14, $this->demoProps()->count());
    }

    // ---------------------------------------------------------------------------------------------- quitar

    public function test_quitar_deja_la_base_exactamente_como_estaba_y_no_toca_lo_real(): void
    {
        $this->datosReales();
        $conteosAntes = $this->conteos();
        $huellasAntes = $this->huellas();

        $this->poblar();
        $this->assertNotSame($conteosAntes, $this->conteos());

        // Mientras existen, lo real no cambió (solo se agregó)
        foreach (['usuario', 'rol', 'categoria', 'bitacora'] as $tabla) {
            $this->assertSame($huellasAntes[$tabla], $this->huellas()[$tabla], "Se modificó {$tabla}.");
        }

        $this->artisan('demo:poblar-reportes', ['--quitar' => true])->assertSuccessful();

        $this->assertSame($conteosAntes, $this->conteos(), 'Las tablas deben quedar con las mismas filas que antes.');
        $this->assertSame($huellasAntes, $this->huellas(), 'Ninguna fila real debe haber cambiado.');
        $this->assertFalse(MarcasDemo::existen());

        // Sin datos demo, quitar avisa y no hace nada
        $this->artisan('demo:poblar-reportes', ['--quitar' => true])->expectsOutputToContain('No hay datos demo')->assertSuccessful();
    }

    // ---------------------------------------------------------------------------------------------- coherencia

    public function test_los_pagos_y_comprobantes_son_coherentes_con_los_contratos(): void
    {
        $this->datosReales(); // ya existe el comprobante CP-2026-000007
        $this->poblar();

        $contratos = DB::table('contrato')->join('cliente', 'cliente.idcliente', '=', 'contrato.idcliente')
            ->where('cliente.email', 'like', '%' . MarcasDemo::DOMINIO_CLIENTE)->select('contrato.*')->get();
        $this->assertGreaterThanOrEqual(10, $contratos->count());

        foreach ($contratos as $c) {
            $pagado = round((float) DB::table('pago')->where('idcontrato', $c->idcontrato)->where('estado', 'PAGADO')->sum('montousd'), 2);
            $this->assertLessThanOrEqual((float) $c->monto, $pagado, "Contrato {$c->idcontrato}: lo pagado supera el monto.");
            if ($c->estado === 'FINALIZADO') {
                $this->assertEqualsWithDelta((float) $c->monto, $pagado, 0.001, "Contrato finalizado {$c->idcontrato}: debe estar pagado por completo.");
            }
            $this->assertGreaterThanOrEqual(1, DB::table('pago')->where('idcontrato', $c->idcontrato)->count());
        }

        $demoIds = $contratos->pluck('idcontrato');
        $pagos = DB::table('pago')->whereIn('idcontrato', $demoIds)->orderBy('idpago')->get();
        $comprobantes = $pagos->where('estado', 'PAGADO')->pluck('comprobante');

        // Numeración real: continúa después de CP-2026-000007, sin huecos ni repetidos, y crece con la fecha de pago
        $this->assertCount($comprobantes->count(), $comprobantes->unique());
        foreach ($comprobantes as $comprobante) {
            $this->assertMatchesRegularExpression('/^CP-2026-\d{6}$/', $comprobante);
        }
        $numeros = $comprobantes->map(fn ($c) => (int) substr($c, 8))->sort()->values();
        $this->assertSame(8, $numeros->first());
        $this->assertSame(range(8, 7 + $numeros->count()), $numeros->all());
        $ordenadoPorFecha = $pagos->where('estado', 'PAGADO')->sortBy('fechapago')->pluck('comprobante')->values()->all();
        $this->assertSame($comprobantes->sort()->values()->all(), $ordenadoPorFecha, 'Los comprobantes deben crecer con la fecha del pago.');
        $this->assertSame(0, $pagos->where('estado', '!=', 'PAGADO')->whereNotNull('comprobante')->count());
        $this->assertEqualsCanonicalizing(['PAGADO', 'PENDIENTE', 'ANULADO'], $pagos->pluck('estado')->unique()->values()->all());
        $this->assertSame(['EFECTIVO'], $pagos->pluck('metodo')->unique()->values()->all());

        // Pagos en bolivianos: tipo de cambio 6.96, su fila tipopago y el equivalente en dólares
        $bob = $pagos->where('moneda', 'BOB');
        $this->assertGreaterThanOrEqual(3, $bob->count());
        foreach ($bob as $p) {
            $this->assertEqualsWithDelta(6.96, (float) $p->tipocambio, 0.00001);
            $this->assertEqualsWithDelta(round((float) $p->monto / 6.96, 2), (float) $p->montousd, 0.001);
            $this->assertSame(1, DB::table('tipopago')->where('idpago', $p->idpago)->count(), 'Falta la fila tipopago.');
        }

        // Los alquileres tienen cuotas mensuales por mes transcurrido (CronogramaDeAlquiler) y lo pagado suma monto - saldo
        foreach (Contrato::whereIn('idcontrato', $demoIds)->where('tipocontrato', 'Alquiler')->get() as $alquiler) {
            $cuotas = \App\Modules\AgendaVisitasPagos\Compartido\CronogramaDeAlquiler::cuotas($alquiler);
            $this->assertNotEmpty($cuotas);
            $this->assertEqualsWithDelta($alquiler->totalPagadoUsd(), array_sum(array_column($cuotas, 'pagado')), 0.01);
        }

        // Las fechas de pago están en el pasado y dentro de los 4 meses
        $this->assertSame(0, $pagos->where('fechapago', '>', '2026-11-21 23:59:59')->count());
        $this->assertSame(0, $pagos->where('fechapago', '<', '2026-07-20')->count());
    }

    public function test_las_reservas_y_los_estados_de_las_propiedades_son_coherentes(): void
    {
        $this->poblar();
        $props = $this->demoProps()->keyBy('idpropiedad');

        $reservas = DB::table('reserva')->whereIn('idpropiedad', $props->keys())->get();
        $this->assertBetween(8, 12, $reservas->count());

        foreach ($reservas as $r) {
            $cobro = DB::table('movimientoreserva')->where('idreserva', $r->idreserva)->where('tipo', 'COBRO')->first();
            $devolucion = DB::table('movimientoreserva')->where('idreserva', $r->idreserva)->where('tipo', 'DEVOLUCION')->first();
            $this->assertNotNull($cobro, 'Toda reserva tiene su cobro.');
            $this->assertEqualsWithDelta((float) $r->montousd, (float) $cobro->montousd, 0.001);
            $this->assertGreaterThanOrEqual(round((float) $props[$r->idpropiedad]->precio * 0.01, 2), (float) $cobro->montousd, 'Debe cubrir el mínimo de la reserva (1 %).');
            $this->assertMatchesRegularExpression('/^RV-2026-\d{6}$/', $cobro->comprobante);

            if ($devolucion) {
                // Solo las vencidas o canceladas se devuelven, con el 20 % retenido
                $this->assertContains($r->estado, ['VENCIDA', 'CANCELADA']);
                $this->assertEqualsWithDelta(round((float) $cobro->montousd * 0.8, 2), (float) $devolucion->montousd, 0.011);
                $this->assertEqualsWithDelta(20.0, (float) $devolucion->porcentajeretencion, 0.001);
                $this->assertMatchesRegularExpression('/^DV-2026-\d{6}$/', $devolucion->comprobante);
            }

            $contrato = DB::table('contrato')->where('idreserva', $r->idreserva)->first();
            $this->assertSame($r->estado === 'CONCRETADA', $contrato !== null, 'Solo las reservas concretadas tienen contrato.');
            $this->assertSame($r->estado === 'VIGENTE', $props[$r->idpropiedad]->estadopropiedad === 'RESERVADO' && $r->fechaplazo >= '2026-11-21');
        }
        $this->assertSame(0, DB::table('movimientoreserva')->whereNotIn('idreserva', DB::table('reserva')->select('idreserva'))->count());

        // El estado de cada propiedad es el de su último cambio, y toda propiedad tiene su registro en el historial
        foreach ($props as $p) {
            $historial = DB::table('historial')->where('idpropiedad', $p->idpropiedad)->orderBy('fecha')->orderBy('idhistorial')->get();
            $this->assertSame('REGISTRO', $historial->first()->tipo);
            $this->assertSame($p->fecharegistro, $historial->first()->fecha);
            $cambios = $historial->where('tipo', 'CAMBIO_ESTADO');
            if ($cambios->isNotEmpty()) {
                $this->assertSame($p->estadopropiedad, json_decode($cambios->last()->valoractual, true)['estado'], "Estado distinto del historial: {$p->titulo}");
                // Cada cambio parte del estado en que dejó la propiedad el anterior
                $estado = 'DISPONIBLE';
                foreach ($cambios as $cambio) {
                    $this->assertSame($estado, json_decode($cambio->valoranterior, true)['estado']);
                    $estado = json_decode($cambio->valoractual, true)['estado'];
                }
            } else {
                $this->assertSame('DISPONIBLE', $p->estadopropiedad);
            }
            // Un contrato vigente deja la propiedad vendida o alquilada según su tipo
            $vigente = DB::table('contrato')->where('idpropiedad', $p->idpropiedad)->where('estado', 'VIGENTE')->first();
            if ($vigente) {
                $this->assertSame($vigente->tipocontrato === 'Venta' ? 'VENDIDO' : 'ALQUILADO', $p->estadopropiedad);
            }
        }
    }

    public function test_los_datos_del_crm_visitas_inquilinos_y_resenas_respetan_las_reglas(): void
    {
        $this->poblar();
        $clientes = $this->demoClientes()->keyBy('idcliente');
        $props = $this->demoProps()->keyBy('idpropiedad');
        $agentes = Usuario::whereHas('rol', fn ($r) => $r->where('nombre', 'Agente Inmobiliario'))->pluck('idusuario')->all();

        // Clientes: tipos y estados variados, asignados a agentes reales, registrados en los últimos 4 meses
        $this->assertEqualsCanonicalizing(['Comprador', 'Inversionista', 'Inquilino'], $clientes->pluck('tipocliente')->unique()->values()->all());
        $this->assertGreaterThanOrEqual(4, $clientes->pluck('estadoseguimiento')->unique()->count());
        foreach ($clientes as $c) {
            $this->assertContains((int) $c->idusuario, $agentes);
            $this->assertMatchesRegularExpression('/^7\d{7}$/', $c->telefono);
            $this->assertGreaterThanOrEqual('2026-07-20', $c->fecharegistro);
            $this->assertLessThanOrEqual('2026-11-21 23:59:59', $c->fecharegistro);
        }
        $this->assertGreaterThan(0, $clientes->where('idusuario', $clientes->first()->idusuario)->count());

        // Seguimientos: después del registro del cliente y, algunos, con próximo contacto futuro
        $seguimientos = DB::table('seguimiento')->whereIn('idcliente', $clientes->keys())->get();
        $this->assertGreaterThanOrEqual(4, $seguimientos->pluck('medio')->unique()->count());
        foreach ($seguimientos as $s) {
            $this->assertGreaterThanOrEqual(substr($clientes[$s->idcliente]->fecharegistro, 0, 10), substr($s->fechacontacto, 0, 10));
            $this->assertLessThan('2026-11-21', substr($s->fechacontacto, 0, 10));
            $this->assertContains((int) $s->idusuario, $agentes);
        }
        $this->assertGreaterThan(3, $seguimientos->where('proximocontacto', '>', '2026-11-21')->count());

        // Inquilinos: uno por cliente, de clientes tipo Inquilino, con mezcla de garantías y estados
        $inquilinos = DB::table('inquilino')->whereIn('idcliente', $clientes->keys())->get();
        $this->assertCount($inquilinos->pluck('idcliente')->unique()->count(), $inquilinos);
        foreach ($inquilinos as $i) {
            $this->assertSame('Inquilino', $clientes[$i->idcliente]->tipocliente);
            $this->assertSame($i->tipogarantia === 'NINGUNA', $i->detallegarantia === null);
        }
        $this->assertGreaterThanOrEqual(3, $inquilinos->pluck('tipogarantia')->unique()->count());
        $this->assertEqualsCanonicalizing(['ACTIVO', 'INACTIVO'], $inquilinos->pluck('estado')->unique()->values()->all());

        // Reseñas: solo de un cliente demo con contrato (no rescindido) en esa propiedad, una por cliente y propiedad
        $resenas = DB::table('resena')->whereIn('idpropiedad', $props->keys())->get();
        $this->assertCount($resenas->map(fn ($r) => $r->idcliente . '|' . $r->idpropiedad)->unique()->count(), $resenas);
        foreach ($resenas as $r) {
            $this->assertBetween(3, 5, (int) $r->puntuacion);
            $this->assertTrue($clientes->has($r->idcliente));
            $this->assertTrue(DB::table('contrato')->where('idcliente', $r->idcliente)->where('idpropiedad', $r->idpropiedad)->whereIn('estado', ['VIGENTE', 'FINALIZADO'])->exists());
        }
        $this->assertGreaterThan(0, $resenas->whereNotNull('comentario')->count());

        // Visitas: sin cruces de horario de un mismo agente, pasadas hechas o canceladas y futuras programadas o reagendadas
        $visitas = DB::table('visita')->whereIn('idpropiedad', $props->keys())->get();
        $this->assertEqualsCanonicalizing(['PROGRAMADA', 'REAGENDADA', 'CANCELADA', 'REALIZADA'], $visitas->pluck('estado')->unique()->values()->all());
        $horarios = [];
        foreach ($visitas as $v) {
            $esFutura = $v->fecha >= '2026-11-21';
            $this->assertSame($esFutura, in_array($v->estado, ['PROGRAMADA', 'REAGENDADA'], true), "Visita {$v->idvisita}: estado incoherente con su fecha.");
            $this->assertMatchesRegularExpression('/^(0[89]|1[0-7]):00:00$/', $v->hora);
            $llave = $props[$v->idpropiedad]->idusuario . '|' . $v->fecha . '|' . $v->hora;
            $this->assertArrayNotHasKey($llave, $horarios, 'Cruce de horario de un mismo agente.');
            $horarios[$llave] = true;
            $this->assertGreaterThanOrEqual(substr($clientes[$v->idcliente]->fecharegistro, 0, 10), $v->fecha);
        }
        $this->assertSame(0, Visita::whereIn('idpropiedad', $props->keys())->count() - $visitas->count());
    }

    public function test_los_reportes_del_cu20_devuelven_filas_y_totales_distintos_entre_meses(): void
    {
        $this->poblar();
        $admin = Usuario::whereHas('rol', fn ($r) => $r->where('nombre', 'Administrador'))->firstOrFail();
        $constructor = new ConstructorDeReportes();
        $reporte = fn (array $datos) => $constructor->construir(CriteriosDeReporte::desdeArreglo($datos), $admin);

        $porCategoria = $reporte(['tipo' => 'propiedades', 'estado' => 'DISPONIBLE', 'agrupacion' => 'categoria']);
        $this->assertGreaterThan(1, count($porCategoria->filas));
        $this->assertGreaterThan(0, $porCategoria->totales['suma']);

        $porAgente = $reporte(['tipo' => 'propiedades', 'agrupacion' => 'agente']);
        $this->assertGreaterThanOrEqual(3, count($porAgente->filas));
        $this->assertGreaterThan(0, $reporte(['tipo' => 'clientes', 'agrupacion' => 'estado'])->totales['cantidad']);
        $this->assertGreaterThanOrEqual(4, count($reporte(['tipo' => 'clientes', 'agrupacion' => 'estado'])->filas));

        $visitasMes = $reporte(['tipo' => 'visitas', 'desde' => '2026-10-21', 'hasta' => '2026-11-21', 'agrupacion' => 'estado']);
        $this->assertGreaterThan(0, $visitasMes->totales['cantidad']);

        $contratos = $reporte(['tipo' => 'contratos', 'agrupacion' => 'estado']);
        $this->assertGreaterThan(0, $contratos->totales['suma']);
        $this->assertGreaterThanOrEqual(3, count($contratos->filas));

        // Pagos por mes: varios meses, todos con filas y con totales distintos
        $pagosPorMes = $reporte(['tipo' => 'pagos', 'agrupacion' => 'mes']);
        $this->assertGreaterThanOrEqual(4, count($pagosPorMes->filas));
        $totales = array_map(fn ($f) => $f[2], $pagosPorMes->filas);
        $this->assertGreaterThan(count($totales) - 1, count(array_unique($totales)) + 1, 'Los totales por mes deben ser distintos entre sí.');
        $this->assertGreaterThan(0, min($totales));

        // "Último mes" y "últimos 3 meses" dan resultados distintos
        $ultimoMes = $reporte(['tipo' => 'pagos', 'desde' => '2026-10-21', 'hasta' => '2026-11-21']);
        $tresMeses = $reporte(['tipo' => 'pagos', 'desde' => '2026-08-21', 'hasta' => '2026-11-21']);
        $this->assertGreaterThan($ultimoMes->totales['cantidad'], $tresMeses->totales['cantidad']);
        $this->assertGreaterThan($ultimoMes->totales['suma'], $tresMeses->totales['suma']);
    }

    // ---------------------------------------------------------------------------------------------- SQL

    public function test_el_script_sql_se_genera_sin_tocar_la_base_y_es_equivalente_al_comando(): void
    {
        $this->datosReales();
        $antes = $this->conteos();
        $carpeta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'demo_sql_' . uniqid();
        $poblar = $carpeta . DIRECTORY_SEPARATOR . 'datos_demo_reportes.sql';
        $quitar = $carpeta . DIRECTORY_SEPARATOR . 'quitar_datos_demo.sql';

        $this->artisan('demo:poblar-reportes', ['--hoy' => '2026-11-21', '--sql' => $poblar])->assertSuccessful();
        $this->artisan('demo:poblar-reportes', ['--quitar' => true, '--sql' => $quitar])->assertSuccessful();
        $this->assertSame($antes, $this->conteos(), 'Generar el SQL no debe tocar la base.');

        $insertar = File::get($poblar);
        $borrar = File::get($quitar);

        // Transacción, bloque previo que aborta si ya hay datos demo, y solo INSERTs
        $this->assertStringContainsString('BEGIN;', $insertar);
        $this->assertStringContainsString('COMMIT;', $insertar);
        $this->assertStringContainsString("RAISE EXCEPTION 'Ya existen datos demo", $insertar);
        $this->assertStringContainsString("nombre LIKE 'DEMO - %'", $insertar);
        $this->assertStringContainsString('@demo.lit.test', $insertar);
        $this->assertDoesNotMatchRegularExpression('/^\s*(UPDATE|DELETE|DROP|TRUNCATE|ALTER)\b/mi', $insertar);
        $this->assertDoesNotMatchRegularExpression('/INSERT INTO \w+ \((id\w+, )?id(propiedad|cliente|contrato|pago|reserva|usuario) /', preg_replace('/INSERT INTO (\w+) \(/', 'INSERT INTO $1 (', $insertar) ?? '', 'sin ids fijos');
        $this->assertStringContainsString("'CP-2026-' ||", $insertar);

        // El mismo plan: igual cantidad de INSERT por tabla que filas que crea el comando
        preg_match_all('/^INSERT INTO (\w+) /m', $insertar, $coincidencias);
        $porTabla = array_count_values($coincidencias[1]);
        $this->poblar();
        foreach ($porTabla as $tabla => $cantidad) {
            $this->assertSame($cantidad, DB::table($tabla)->count() - $antes[$tabla], "El SQL y el comando difieren en {$tabla}.");
        }

        // El script de borrado solo borra lo demo: todos sus DELETE tienen condición y se apoyan en las marcas
        $this->assertStringContainsString('BEGIN;', $borrar);
        $this->assertStringContainsString('COMMIT;', $borrar);
        $this->assertStringContainsString("nombre LIKE 'DEMO - %'", $borrar);
        $this->assertStringContainsString("email LIKE '%@demo.lit.test'", $borrar);
        // (ON COMMIT DROP solo elimina las tablas temporales del propio script)
        $this->assertDoesNotMatchRegularExpression('/\b(TRUNCATE|ALTER|INSERT|UPDATE)\b|DROP\s+(TABLE|DATABASE|SCHEMA|COLUMN)/i', $borrar);
        preg_match_all('/^DELETE FROM \w+[^;]*;/m', $borrar, $borrados);
        $this->assertGreaterThanOrEqual(16, count($borrados[0]));
        foreach ($borrados[0] as $sentencia) {
            $this->assertStringContainsString(' WHERE ', $sentencia, 'Un DELETE sin condición borraría datos reales.');
        }

        File::deleteDirectory($carpeta);
    }

    // ---------------------------------------------------------------------------------------- protecciones

    public function test_un_horario_ocupado_por_una_visita_real_se_omite_sin_chocar(): void
    {
        $plan = (new PlanDeDatosDemo(CarbonImmutable::parse('2026-11-21')))->construir();
        $activas = array_values(array_filter($plan, fn ($op) => $op['visita'] !== null));
        $this->assertNotEmpty($activas);

        // Una visita real del mismo agente, a la misma fecha y hora que la primera visita activa del plan
        $agentes = Usuario::whereHas('rol', fn ($r) => $r->where('nombre', 'Agente Inmobiliario'))->orderBy('idusuario')->pluck('idusuario')->all();
        /** @var Ref $ref */
        $ref = $activas[0]['visita']['agente'];
        $agente = Usuario::findOrFail($agentes[($ref->datos['posicion'] - 1) % count($agentes)]);
        $propiedad = $this->propiedad(['titulo' => 'Casa con una visita real'], $agente);
        $cliente = Cliente::create(['nombre' => 'Cliente Visita', 'email' => 'visita@correo.com']);
        Visita::create(['idpropiedad' => $propiedad->idpropiedad, 'idcliente' => $cliente->idcliente, 'fecha' => $activas[0]['visita']['fecha'],
            'hora' => $activas[0]['visita']['hora'], 'estado' => Visita::PROGRAMADA]);

        $this->artisan('demo:poblar-reportes', ['--hoy' => '2026-11-21'])->expectsOutputToContain('Visitas omitidas por cruce de horario')->assertSuccessful();

        $esperadas = count(array_filter($plan, fn ($op) => $op['tabla'] === 'visita')) - 1;
        $this->assertSame($esperadas, Visita::whereIn('idpropiedad', $this->demoProps()->pluck('idpropiedad'))->count());
    }

    public function test_con_menos_agentes_que_posiciones_no_hay_cruces_de_horario(): void
    {
        // Solo 2 agentes activos: varias posiciones del plan caen en el mismo agente y el cruce se evita omitiendo visitas.
        Usuario::whereIn('nombre', ['Agente Tres', 'Agente Cuatro', 'Agente Cinco'])->update(['estado' => Usuario::INACTIVO]);

        $this->poblar();

        $props = $this->demoProps()->keyBy('idpropiedad');
        $visitas = DB::table('visita')->whereIn('idpropiedad', $props->keys())->get();
        $this->assertGreaterThan(25, $visitas->count());
        $horarios = $visitas->map(fn ($v) => $props[$v->idpropiedad]->idusuario . '|' . $v->fecha . '|' . $v->hora);
        $this->assertCount($horarios->unique()->count(), $horarios, 'Un agente no puede tener dos visitas a la misma hora.');
        $this->assertSame(2, $props->pluck('idusuario')->unique()->count());
    }

    public function test_no_se_ejecuta_en_produccion_sin_force_y_sin_agentes_da_un_error_claro(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('demo:poblar-reportes', ['--hoy' => '2026-11-21'])->expectsOutputToContain('no se ejecuta en producción')->assertFailed();
        $this->assertFalse(MarcasDemo::existen());
        $this->app['env'] = 'testing';

        // Fecha mal escrita
        $this->artisan('demo:poblar-reportes', ['--hoy' => '21/11/2026'])->expectsOutputToContain('AAAA-MM-DD')->assertFailed();

        // Sin agentes activos no hay con quién repartir los datos: nada queda a medias
        Usuario::whereHas('rol', fn ($r) => $r->where('nombre', 'Agente Inmobiliario'))->update(['estado' => Usuario::INACTIVO]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No hay agentes activos');
        try {
            $this->artisan('demo:poblar-reportes', ['--hoy' => '2026-11-21']);
        } finally {
            $this->assertFalse(MarcasDemo::existen(), 'Nada debe quedar a medias si falla.');
        }
    }

    public function test_los_datos_demo_no_pueden_entrar_al_archivo_compartido_del_equipo(): void
    {
        $this->poblar();

        // datos:exportar se niega mientras haya datos demo (no escribe nada)
        $this->artisan('datos:exportar')->expectsOutputToContain('Hay datos demo en la base')->assertFailed();

        $this->artisan('demo:poblar-reportes', ['--quitar' => true])->assertSuccessful();
        $this->assertFalse(MarcasDemo::existen());
    }

    private function assertBetween(int $minimo, int $maximo, int $valor): void
    {
        $this->assertGreaterThanOrEqual($minimo, $valor);
        $this->assertLessThanOrEqual($maximo, $valor);
    }
}
