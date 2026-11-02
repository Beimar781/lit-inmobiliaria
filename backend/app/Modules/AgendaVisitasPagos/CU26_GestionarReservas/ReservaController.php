<?php

namespace App\Modules\AgendaVisitasPagos\CU26_GestionarReservas;

use App\Http\Controllers\Controller;
use App\Models\MovimientoReserva;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Rol;
use App\Models\Visita;
use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use App\Modules\AgendaVisitasPagos\Compartido\DineroDeReservas;
use App\Modules\AgendaVisitasPagos\Compartido\VencimientoDeReservas;
use App\Modules\GestionPropiedades\Compartido\CambioDeEstadoPropiedad;
use App\Services\BitacoraService;
use App\Support\ParametrosConsulta;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * CU26: Gestionar reservas de propiedad
 * La reserva se hace DESPUÉS de una visita realizada y se paga en EFECTIVO (dólares o bolivianos). La registra quien maneja el
 * dinero: el Asistente Administrativo o el Administrador. El Agente consulta las reservas de sus propiedades.
 * Si el cliente no formaliza la compra (la reserva vence o se cancela) se le devuelve en efectivo casi todo lo pagado: la
 * empresa retiene un porcentaje (config/reservas.php) por haber tenido la propiedad bloqueada. Si formaliza, la reserva se
 * descuenta del contrato (CU27). Incluye el CU8: al reservar la propiedad pasa a "Reservado" y al terminar vuelve a "Disponible".
 * Flujo principal:
 *  1. El actor accede al módulo de reservas                                   -> index()
 *  2. El sistema lista las reservas activas y permite filtrarlas por estado   -> index.blade.php
 *  3. El actor selecciona "Nueva reserva"                                     -> crear()
 *  4. El actor elige la visita realizada (de ella salen la propiedad disponible y el cliente)
 *  5. El actor indica lo que paga el cliente en efectivo y la fecha de plazo, y confirma -> guardar()
 *  6. El sistema valida los datos, que la visita esté realizada, que la propiedad siga disponible y el monto mínimo -> ReservaRequest / guardar()
 *  7. El sistema guarda la reserva como "Vigente" y el cobro con su recibo      -> DineroDeReservas::cobrar()
 *  8. El sistema cambia el estado de la propiedad a "Reservado" (CU8) y registra el historial -> CambioDeEstadoPropiedad
 *  9. El sistema muestra el recibo de la reserva                              -> recibo()
 * Flujo alternativo: cancelar una reserva vigente -> cancelar() (la propiedad vuelve a "Disponible" y queda pendiente la devolución).
 * Devolución -> devolver(): se devuelve en efectivo lo pagado menos el porcentaje retenido, con su recibo.
 * Las reservas con el plazo vencido se marcan "Vencidas" al consultarlas o al intentar usarlas (VencimientoDeReservas); también
 * queda pendiente su devolución. Las reservas vencidas o canceladas y ya devueltas salen de la lista activa pero se conservan.
 * Excepciones: visita no realizada, propiedad no disponible o con una reserva vigente, monto menor al mínimo, plazo pasado o no
 * válido, tipo de cambio faltante en bolivianos, error de base de datos.
 */
class ReservaController extends Controller
{
    public function index(Request $request, VencimientoDeReservas $vencimiento, DineroDeReservas $dinero): View
    {
        $vencimiento->vencer();

        $usuario = $request->user();
        $estado = ParametrosConsulta::opcion($request, 'estado', array_keys(Reserva::ESTADOS));

        $reservas = Reserva::with(['propiedad.agente', 'cliente', 'cobro', 'devolucion'])
            ->when($usuario->tieneRol(Rol::AGENTE), fn ($q) => $q->whereHas('propiedad', fn ($p) => $p->where('idusuario', $usuario->idusuario)))
            // Sin filtro se ven las activas; las vencidas o canceladas ya devueltas se conservan y salen al filtrar por su estado.
            ->when($estado, fn ($q) => $q->where('estado', $estado), fn ($q) => $q->activas())
            ->orderByDesc('idreserva')
            ->paginate(10)
            ->withQueryString();

        return view('agendavisitaspagos::CU26_GestionarReservas.index', [
            'reservas' => $reservas,
            'estado' => $estado,
            'estados' => Reserva::ESTADOS,
            'esAdmin' => $usuario->tieneRol(Rol::ADMINISTRADOR),
            'puedeCobrar' => $usuario->tieneRol(Rol::ADMINISTRADOR, Rol::ASISTENTE),
            'puedeContratar' => $usuario->tieneRol(Rol::ADMINISTRADOR, Rol::AGENTE) && Route::has('contratos.create'),
            'dinero' => $dinero,
            'retencion' => (float) config('reservas.retencion'),
        ]);
    }

    public function crear(Request $request, VencimientoDeReservas $vencimiento): View
    {
        $vencimiento->vencer();

        // Visitas realizadas de propiedades que siguen disponibles y sin una reserva vigente.
        $visitas = Visita::where('estado', Visita::REALIZADA)
            ->whereHas('propiedad', fn ($p) => $p->where('estadopropiedad', Propiedad::DISPONIBLE))
            ->whereDoesntHave('propiedad.reservas', fn ($r) => $r->where('estado', Reserva::VIGENTE))
            ->with(['propiedad', 'cliente'])
            ->orderByDesc('fecha')
            ->orderByDesc('idvisita')
            ->get();

        return view('agendavisitaspagos::CU26_GestionarReservas.crear', [
            'visitas' => $visitas->mapWithKeys(fn (Visita $v) => [
                $v->idvisita => "{$v->cliente->nombre} · {$v->propiedad->codigo} {$v->propiedad->titulo} · visita del {$v->fecha->format('d/m/Y')} (precio USD " . number_format($v->propiedad->precio, 0, '.', ',') . ')',
            ])->all(),
            'visitaElegida' => $request->query('visita'),
            'tipoCambioSugerido' => DineroDeReservas::ultimoTipoCambio(),
            'minimoPorcentaje' => (float) config('reservas.minimo'),
            'retencion' => (float) config('reservas.retencion'),
            'minimo' => AgendaDeVisitas::ahora()->addDay()->toDateString(),
        ]);
    }

    public function guardar(ReservaRequest $request, VencimientoDeReservas $vencimiento, CambioDeEstadoPropiedad $cambio, DineroDeReservas $dinero): RedirectResponse
    {
        $vencimiento->vencer(); // una reserva vencida libera la propiedad antes de intentar reservarla de nuevo

        $usuario = $request->user();
        $visita = Visita::with(['propiedad', 'cliente'])->findOrFail($request->input('idvisita'));
        $moneda = $request->input('moneda');
        $monto = $request->input('monto');
        $tipoCambio = $moneda === 'BOB' ? $request->input('tipocambio') : null;
        $plazo = $request->input('fechaplazo');
        $cliente = $visita->cliente;

        // Paso 6: la reserva se apoya en una visita realizada.
        if ($visita->estado !== Visita::REALIZADA) {
            return back()->withInput()->withErrors(['idvisita' => 'Solo se puede reservar después de una visita realizada: esa visita está ' . strtolower($visita->estado_etiqueta) . '.']);
        }

        // El monto mínimo, en dólares, es un porcentaje del precio de la propiedad.
        $montoUsd = DineroDeReservas::aUsd($monto, $moneda, $tipoCambio);
        $minimoUsd = DineroDeReservas::minimoUsd($visita->propiedad->precio);
        if ($montoUsd < $minimoUsd) {
            return back()->withInput()->withErrors(['monto' => 'La reserva debe ser al menos el ' . rtrim(rtrim(number_format((float) config('reservas.minimo'), 2), '0'), '.') . '% del precio: USD ' . number_format($minimoUsd, 2, '.', ',') . ' (el pago equivale a USD ' . number_format($montoUsd, 2, '.', ',') . ').']);
        }
        if ($montoUsd > (float) $visita->propiedad->precio) {
            return back()->withInput()->withErrors(['monto' => 'La reserva no puede superar el precio de la propiedad (USD ' . number_format($visita->propiedad->precio, 2, '.', ',') . ').']);
        }

        try {
            $resultado = DB::transaction(function () use ($visita, $cliente, $usuario, $plazo, $moneda, $monto, $tipoCambio, $montoUsd, $cambio, $dinero) {
                $propiedad = Propiedad::lockForUpdate()->findOrFail($visita->idpropiedad);

                // Paso 6: la propiedad debe seguir disponible y sin otra reserva vigente.
                if ($propiedad->estadopropiedad === Propiedad::BAJA) {
                    return ['error' => 'Esa propiedad fue dada de baja.'];
                }
                if (Reserva::vigentes()->where('idpropiedad', $propiedad->idpropiedad)->exists()) {
                    return ['error' => 'La propiedad ya tiene una reserva vigente.'];
                }
                if ($propiedad->estadopropiedad !== Propiedad::DISPONIBLE) {
                    return ['error' => 'La propiedad no está disponible (está ' . strtolower($propiedad->estadopropiedad) . ').'];
                }

                // Paso 7: la reserva queda "Vigente" y se registra el cobro en efectivo con su recibo.
                $reserva = Reserva::create([
                    'idcliente' => $cliente->idcliente,
                    'idpropiedad' => $propiedad->idpropiedad,
                    'idvisita' => $visita->idvisita,
                    'fechareserva' => AgendaDeVisitas::ahora()->toDateString(),
                    'fechaplazo' => $plazo,
                    'estado' => Reserva::VIGENTE,
                    'montousd' => $montoUsd,
                    'idusuario' => $usuario->idusuario,
                ]);
                $cobro = $dinero->cobrar($reserva, $monto, $moneda, $tipoCambio, $usuario);

                // Paso 8: la propiedad pasa a "Reservado" (CU8) con su historial.
                $motivo = "Reserva #{$reserva->idreserva} de {$cliente->nombre} hasta el " . date('d/m/Y', strtotime($plazo));
                $anterior = $cambio->cambiar($propiedad, Propiedad::RESERVADO, $motivo);

                return compact('reserva', 'cobro', 'propiedad', 'anterior', 'motivo');
            });
        } catch (QueryException $error) {
            report($error);

            return back()->withInput()->with('error', 'No se pudo registrar la reserva por un problema con la base de datos. Intenta de nuevo en unos minutos.');
        }

        if (isset($resultado['error'])) {
            return back()->withInput()->withErrors(['idvisita' => $resultado['error']]);
        }

        ['reserva' => $reserva, 'cobro' => $cobro, 'propiedad' => $propiedad, 'anterior' => $anterior, 'motivo' => $motivo] = $resultado;

        BitacoraService::registrar('RESERVA_REGISTRADA', 'Reservas', "Registró la reserva #{$reserva->idreserva} de «{$propiedad->titulo}» ({$propiedad->codigo}) para {$cliente->nombre}: cobró {$cobro->moneda} " . number_format($cobro->monto, 2, '.', ',') . " (recibo {$cobro->comprobante})", [
            'idreserva' => $reserva->idreserva,
            'idpropiedad' => $propiedad->idpropiedad,
            'idcliente' => $cliente->idcliente,
            'idvisita' => $visita->idvisita,
            'fechaplazo' => $plazo,
            'monto' => (string) $cobro->monto,
            'moneda' => $cobro->moneda,
            'tipocambio' => $cobro->tipocambio !== null ? (string) $cobro->tipocambio : null,
            'montousd' => (string) $cobro->montousd,
            'comprobante' => $cobro->comprobante,
        ]);
        $cambio->registrarEnBitacora($propiedad, $anterior, Propiedad::RESERVADO, $motivo, ['idreserva' => $reserva->idreserva]);

        // Paso 9: el recibo de la reserva.
        return redirect()->route('reservas.recibo', $cobro)
            ->with('status', "Reserva registrada: «{$propiedad->titulo}» quedó reservada para {$cliente->nombre} hasta el " . date('d/m/Y', strtotime($plazo)) . '.');
    }

    /** Flujo alternativo: cancelar una reserva vigente; la propiedad vuelve a "Disponible" y queda pendiente la devolución. */
    public function cancelar(Request $request, Reserva $reserva, VencimientoDeReservas $vencimiento, CambioDeEstadoPropiedad $cambio, DineroDeReservas $dinero): RedirectResponse
    {
        $vencimiento->vencer();

        $reserva->load(['propiedad', 'cliente']);

        $resultado = DB::transaction(function () use ($reserva, $cambio) {
            $actual = Reserva::lockForUpdate()->findOrFail($reserva->idreserva);

            if ($actual->estado !== Reserva::VIGENTE) {
                return ['error' => 'Solo se pueden cancelar las reservas vigentes: esta reserva está ' . strtolower($actual->estado_etiqueta) . '.'];
            }

            $actual->update(['estado' => Reserva::CANCELADA]);

            // La propiedad vuelve a "Disponible" (CU8), salvo que ya no esté reservada.
            $anterior = null;
            $motivo = "Se canceló la reserva #{$actual->idreserva}";
            $propiedad = Propiedad::find($actual->idpropiedad);
            if ($propiedad && $propiedad->estadopropiedad === Propiedad::RESERVADO) {
                $anterior = $cambio->cambiar($propiedad, Propiedad::DISPONIBLE, $motivo);
            }

            return compact('propiedad', 'anterior', 'motivo');
        });

        if (isset($resultado['error'])) {
            return back()->with('error', $resultado['error']);
        }

        BitacoraService::registrar('RESERVA_CANCELADA', 'Reservas', "Canceló la reserva #{$reserva->idreserva} de «{$reserva->propiedad->titulo}» ({$reserva->propiedad->codigo}) de {$reserva->cliente->nombre}", [
            'idreserva' => $reserva->idreserva,
            'idpropiedad' => $reserva->idpropiedad,
            'idcliente' => $reserva->idcliente,
        ]);
        if ($resultado['anterior'] !== null) {
            $cambio->registrarEnBitacora($resultado['propiedad'], $resultado['anterior'], Propiedad::DISPONIBLE, $resultado['motivo'], ['idreserva' => $reserva->idreserva]);
        }

        $mensaje = "La reserva fue cancelada y «{$reserva->propiedad->titulo}» volvió a estar disponible.";
        $calculo = $dinero->calcularDevolucion($reserva->fresh('cobro'));
        if ($calculo) {
            $mensaje .= " Falta devolver al cliente {$calculo['moneda']} " . number_format($calculo['monto'], 2, '.', ',') . ' en efectivo.';
        }

        return redirect()->route('reservas.index')->with('status', $mensaje);
    }

    /**
     * Devolución en efectivo: lo pagado menos el porcentaje que retiene la empresa.
     * Solo cuando la reserva venció o se canceló (el cliente no formalizó la compra) y todavía no se devolvió.
     */
    public function devolver(Request $request, Reserva $reserva, DineroDeReservas $dinero): RedirectResponse
    {
        $usuario = $request->user();

        try {
            $resultado = DB::transaction(function () use ($reserva, $usuario, $dinero) {
                $actual = Reserva::with('cobro')->lockForUpdate()->findOrFail($reserva->idreserva);

                if (! $actual->devolucionPendiente() || ! $actual->cobro) {
                    return ['error' => $actual->devolucion()->exists()
                        ? 'La devolución de esta reserva ya se registró.'
                        : 'Esta reserva no tiene una devolución pendiente: solo se devuelve cuando la reserva venció o se canceló.'];
                }

                return ['movimiento' => $dinero->devolver($actual, $usuario), 'reserva' => $actual, 'calculo' => $dinero->calcularDevolucion($actual)];
            });
        } catch (QueryException $error) {
            report($error);

            return back()->with('error', 'No se pudo registrar la devolución por un problema con la base de datos. Intenta de nuevo en unos minutos.');
        }

        if (isset($resultado['error'])) {
            return back()->with('error', $resultado['error']);
        }

        ['movimiento' => $movimiento, 'reserva' => $actual, 'calculo' => $calculo] = $resultado;
        $actual->load(['propiedad', 'cliente']);

        BitacoraService::registrar('RESERVA_DEVUELTA', 'Reservas', "Devolvió {$movimiento->moneda} " . number_format($movimiento->monto, 2, '.', ',') . " de la reserva #{$actual->idreserva} a {$actual->cliente->nombre} (recibo {$movimiento->comprobante}); la empresa retuvo el {$calculo['porcentaje']} %", [
            'idreserva' => $actual->idreserva,
            'idpropiedad' => $actual->idpropiedad,
            'idcliente' => $actual->idcliente,
            'estado_reserva' => $actual->estado,
            'monto' => (string) $movimiento->monto,
            'moneda' => $movimiento->moneda,
            'montousd' => (string) $movimiento->montousd,
            'retenido_usd' => number_format($calculo['retenido_usd'], 2, '.', ''),
            'porcentaje_retenido' => $calculo['porcentaje'],
            'comprobante' => $movimiento->comprobante,
        ]);

        return redirect()->route('reservas.recibo', $movimiento)
            ->with('status', "Devolución registrada: {$movimiento->moneda} " . number_format($movimiento->monto, 2, '.', ',') . ' en efectivo para ' . $actual->cliente->nombre . '.');
    }

    /** Recibo imprimible del cobro o de la devolución de una reserva. */
    public function recibo(MovimientoReserva $movimiento): View
    {
        $movimiento->load(['reserva.propiedad', 'reserva.cliente', 'reserva.cobro', 'usuario']);

        return view('agendavisitaspagos::CU26_GestionarReservas.recibo', [
            'movimiento' => $movimiento,
            'reserva' => $movimiento->reserva,
            'retenidoUsd' => $movimiento->tipo === MovimientoReserva::DEVOLUCION
                ? round((float) $movimiento->reserva->cobro->montousd - (float) $movimiento->montousd, 2)
                : null,
        ]);
    }
}
