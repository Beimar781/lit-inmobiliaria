<?php

namespace App\Modules\AgendaVisitasPagos\CU26_GestionarReservas;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Rol;
use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use App\Modules\AgendaVisitasPagos\Compartido\ClientesDelActor;
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
 * CU26: Gestionar reservas de propiedad (Agente inmobiliario y Administrador)
 * El Agente ve y reserva solo las propiedades que él registró; el Administrador, todas.
 * Incluye el CU8: al reservar la propiedad pasa a "Reservado" y al cancelar vuelve a "Disponible" (con su historial).
 * Flujo principal:
 *  1. El actor accede al módulo de reservas                                   -> index()
 *  2. El sistema lista las reservas y permite filtrarlas por estado           -> index.blade.php
 *  3. El actor selecciona "Nueva reserva"                                     -> crear()
 *  4. El actor elige la propiedad disponible y el cliente
 *  5. El actor define la fecha de plazo de la reserva y confirma             -> guardar()
 *  6. El sistema valida los datos y que la propiedad siga disponible          -> ReservaRequest / guardar()
 *  7. El sistema guarda la reserva como "Vigente"
 *  8. El sistema cambia el estado de la propiedad a "Reservado" (CU8) y registra el historial -> CambioDeEstadoPropiedad
 *  9. El sistema muestra la confirmación
 * Flujo alternativo: cancelar una reserva vigente -> cancelar() (la reserva queda "Cancelada" y la propiedad vuelve a "Disponible").
 * Las reservas con el plazo vencido se marcan "Vencidas" al consultarlas o al intentar usarlas (VencimientoDeReservas).
 * Excepciones: propiedad no disponible o con una reserva vigente, cliente inexistente, plazo pasado o no válido,
 * Agente con una propiedad que no registró (403), error de base de datos.
 */
class ReservaController extends Controller
{
    public function index(Request $request, VencimientoDeReservas $vencimiento): View
    {
        $vencimiento->vencer();

        $usuario = $request->user();
        $estado = ParametrosConsulta::opcion($request, 'estado', array_keys(Reserva::ESTADOS));

        $reservas = Reserva::with(['propiedad.agente', 'cliente'])
            ->when($usuario->tieneRol(Rol::AGENTE), fn ($q) => $q->whereHas('propiedad', fn ($p) => $p->where('idusuario', $usuario->idusuario)))
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('idreserva')
            ->paginate(10)
            ->withQueryString();

        return view('agendavisitaspagos::CU26_GestionarReservas.index', [
            'reservas' => $reservas,
            'estado' => $estado,
            'estados' => Reserva::ESTADOS,
            'esAdmin' => $usuario->tieneRol(Rol::ADMINISTRADOR),
            'puedeContratar' => Route::has('contratos.create'),
        ]);
    }

    public function crear(Request $request, VencimientoDeReservas $vencimiento): View
    {
        $vencimiento->vencer();

        $usuario = $request->user();

        $propiedades = Propiedad::disponibles()
            ->when($usuario->tieneRol(Rol::AGENTE), fn ($q) => $q->where('idusuario', $usuario->idusuario))
            ->orderByDesc('idpropiedad')
            ->get()
            ->mapWithKeys(fn (Propiedad $p) => [$p->idpropiedad => "{$p->codigo} · {$p->titulo}"])
            ->all();

        return view('agendavisitaspagos::CU26_GestionarReservas.crear', [
            'propiedades' => $propiedades,
            'clientes' => ClientesDelActor::opciones($usuario),
            'propiedadElegida' => $request->query('propiedad'),
            'clienteElegido' => $request->query('cliente'),
            'minimo' => AgendaDeVisitas::ahora()->addDay()->toDateString(),
        ]);
    }

    public function guardar(ReservaRequest $request, VencimientoDeReservas $vencimiento, CambioDeEstadoPropiedad $cambio): RedirectResponse
    {
        $vencimiento->vencer(); // una reserva vencida libera la propiedad antes de intentar reservarla de nuevo

        $usuario = $request->user();
        $cliente = Cliente::findOrFail($request->input('idcliente'));

        if (! ClientesDelActor::permitido($usuario, $cliente)) {
            return back()->withInput()->withErrors(['idcliente' => 'Ese cliente no está entre tus prospectos.']);
        }

        $plazo = $request->input('fechaplazo');

        try {
            $resultado = DB::transaction(function () use ($request, $usuario, $cliente, $plazo, $cambio) {
                $propiedad = Propiedad::lockForUpdate()->findOrFail($request->input('idpropiedad'));

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

                // Paso 7: la reserva queda "Vigente".
                $reserva = Reserva::create([
                    'idcliente' => $cliente->idcliente,
                    'idpropiedad' => $propiedad->idpropiedad,
                    'fechareserva' => AgendaDeVisitas::ahora()->toDateString(),
                    'fechaplazo' => $plazo,
                    'estado' => Reserva::VIGENTE,
                    'idusuario' => $usuario->idusuario,
                ]);

                // Paso 8: la propiedad pasa a "Reservado" (CU8) con su historial.
                $motivo = "Reserva #{$reserva->idreserva} de {$cliente->nombre} hasta el " . date('d/m/Y', strtotime($plazo));
                $anterior = $cambio->cambiar($propiedad, Propiedad::RESERVADO, $motivo);

                return compact('reserva', 'propiedad', 'anterior', 'motivo');
            });
        } catch (QueryException $error) {
            report($error);

            return back()->withInput()->with('error', 'No se pudo registrar la reserva por un problema con la base de datos. Intenta de nuevo en unos minutos.');
        }

        if (isset($resultado['error'])) {
            return back()->withInput()->withErrors(['idpropiedad' => $resultado['error']]);
        }

        ['reserva' => $reserva, 'propiedad' => $propiedad, 'anterior' => $anterior, 'motivo' => $motivo] = $resultado;

        BitacoraService::registrar('RESERVA_REGISTRADA', 'Reservas', "Registró la reserva #{$reserva->idreserva} de «{$propiedad->titulo}» ({$propiedad->codigo}) para {$cliente->nombre}", [
            'idreserva' => $reserva->idreserva,
            'idpropiedad' => $propiedad->idpropiedad,
            'idcliente' => $cliente->idcliente,
            'fechaplazo' => $plazo,
        ]);
        $cambio->registrarEnBitacora($propiedad, $anterior, Propiedad::RESERVADO, $motivo, ['idreserva' => $reserva->idreserva]);

        // Paso 9: confirmación.
        return redirect()->route('reservas.index')
            ->with('status', "Reserva registrada: «{$propiedad->titulo}» quedó reservada para {$cliente->nombre} hasta el " . date('d/m/Y', strtotime($plazo)) . '.');
    }

    /** Flujo alternativo: cancelar una reserva vigente; la propiedad vuelve a "Disponible". */
    public function cancelar(Request $request, Reserva $reserva, VencimientoDeReservas $vencimiento, CambioDeEstadoPropiedad $cambio): RedirectResponse
    {
        $vencimiento->vencer();

        $usuario = $request->user();
        $reserva->load(['propiedad', 'cliente']);

        abort_if(
            $usuario->tieneRol(Rol::AGENTE) && $reserva->propiedad->idusuario !== $usuario->idusuario,
            403,
            'Solo puedes cancelar las reservas de las propiedades que tú registraste.'
        );

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

        return redirect()->route('reservas.index')
            ->with('status', "La reserva fue cancelada y «{$reserva->propiedad->titulo}» volvió a estar disponible.");
    }
}
