<?php

namespace App\Modules\AgendaVisitasPagos\CU27_GestionarContratos;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Rol;
use App\Modules\AgendaVisitasPagos\Compartido\ClientesDelActor;
use App\Modules\AgendaVisitasPagos\Compartido\VencimientoDeReservas;
use App\Modules\GestionPropiedades\Compartido\CambioDeEstadoPropiedad;
use App\Services\BitacoraService;
use App\Support\ParametrosConsulta;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CU27: Gestionar contratos (Agente inmobiliario y Administrador)
 * El Agente ve y formaliza solo las propiedades que él registró; el Administrador, todas.
 * Incluye el CU8: al registrar el contrato la propiedad pasa a "Vendido" o "Alquilado"; si se rescinde, vuelve a "Disponible".
 * Puede originarse en una reserva previa (CU26), que pasa a "Concretada".
 * Flujo principal:
 *  1. El actor accede al módulo de contratos                                   -> index()
 *  2. El sistema lista los contratos y permite filtrarlos por estado y tipo    -> index.blade.php
 *  3. El actor selecciona "Nuevo contrato"                                     -> crear()
 *  4. El actor elige la propiedad y el cliente
 *  5. El actor indica el monto y las fechas de inicio y fin (el tipo sale de la propiedad: Venta, Alquiler o Anticrético)
 *  6. El actor confirma el registro                                            -> guardar()
 *  7. El sistema valida los datos y que la propiedad esté disponible (o reservada por ese cliente) -> ContratoRequest / guardar()
 *  8. El sistema guarda el contrato como "Vigente"
 *  9. El sistema actualiza el estado de la propiedad a "Vendido" o "Alquilado" (CU8) y registra el historial -> CambioDeEstadoPropiedad
 * 10. El sistema muestra la confirmación
 * Flujo alternativo: cambiar el estado de un contrato (Finalizar o Rescindir) -> cambiarEstado(); si se rescinde, la propiedad vuelve a "Disponible" (CU8).
 * Si el contrato viene de una reserva, esta pasa a "Concretada" y deja de estar vigente.
 * Excepciones: propiedad no disponible, dada de baja o con un contrato vigente; cliente inexistente; monto no válido o fecha de fin anterior
 * a la de inicio; Agente con una propiedad que no registró (403); error de base de datos.
 */
class ContratoController extends Controller
{
    public function index(Request $request, VencimientoDeReservas $vencimiento): View
    {
        $vencimiento->vencer();

        $usuario = $request->user();
        $estado = ParametrosConsulta::opcion($request, 'estado', array_keys(Contrato::ESTADOS));
        $tipo = ParametrosConsulta::opcion($request, 'tipo', Contrato::TIPOS);

        $contratos = Contrato::with(['propiedad', 'cliente'])
            ->when($usuario->tieneRol(Rol::AGENTE), fn ($q) => $q->whereHas('propiedad', fn ($p) => $p->where('idusuario', $usuario->idusuario)))
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($tipo, fn ($q) => $q->where('tipocontrato', $tipo))
            ->orderByDesc('idcontrato')
            ->paginate(10)
            ->withQueryString();

        return view('agendavisitaspagos::CU27_GestionarContratos.index', [
            'contratos' => $contratos,
            'estado' => $estado,
            'tipo' => $tipo,
            'estados' => Contrato::ESTADOS,
            'tipos' => array_combine(Contrato::TIPOS, Contrato::TIPOS),
        ]);
    }

    public function crear(Request $request, VencimientoDeReservas $vencimiento): View
    {
        $vencimiento->vencer();

        $usuario = $request->user();
        $propiedadElegida = $request->query('propiedad');
        $clienteElegido = $request->query('cliente');

        // Desde una reserva vigente se llena la propiedad y el cliente.
        $reserva = ctype_digit((string) $request->query('reserva')) ? Reserva::vigentes()->with('propiedad')->find($request->query('reserva')) : null;
        if ($reserva && (! $usuario->tieneRol(Rol::AGENTE) || $reserva->propiedad->idusuario === $usuario->idusuario)) {
            $propiedadElegida = $reserva->idpropiedad;
            $clienteElegido = $reserva->idcliente;
        }

        // Propiedades que se pueden formalizar: disponibles, o reservadas (con una reserva vigente).
        $reservadas = Reserva::vigentes()->get()->keyBy('idpropiedad');
        $propiedades = Propiedad::whereIn('estadopropiedad', [Propiedad::DISPONIBLE, Propiedad::RESERVADO])
            ->when($usuario->tieneRol(Rol::AGENTE), fn ($q) => $q->where('idusuario', $usuario->idusuario))
            ->orderByDesc('idpropiedad')
            ->get()
            ->filter(fn (Propiedad $p) => $p->estadopropiedad === Propiedad::DISPONIBLE || $reservadas->has($p->idpropiedad));

        $clientes = ClientesDelActor::opciones($usuario);
        // El cliente de una reserva se puede elegir aunque no sea un prospecto propio del Agente.
        foreach ($reservadas as $r) {
            if ($propiedades->contains('idpropiedad', $r->idpropiedad)) {
                $c = $r->cliente;
                $clientes[$c->idcliente] ??= $c->nombre . ($c->email ? " · {$c->email}" : '');
            }
        }

        return view('agendavisitaspagos::CU27_GestionarContratos.crear', [
            'propiedades' => $propiedades,
            'reservadas' => $reservadas->map(fn (Reserva $r) => $r->idcliente)->all(),
            'clientes' => $clientes,
            'propiedadElegida' => $propiedadElegida,
            'clienteElegido' => $clienteElegido,
        ]);
    }

    public function guardar(ContratoRequest $request, VencimientoDeReservas $vencimiento, CambioDeEstadoPropiedad $cambio): RedirectResponse
    {
        $vencimiento->vencer(); // una reserva vencida libera la propiedad antes de intentar formalizarla

        $usuario = $request->user();
        $cliente = Cliente::findOrFail($request->input('idcliente'));

        try {
            $resultado = DB::transaction(function () use ($request, $usuario, $cliente, $cambio) {
                $propiedad = Propiedad::lockForUpdate()->findOrFail($request->input('idpropiedad'));

                // Paso 7: la propiedad debe estar disponible, o reservada por ese mismo cliente.
                if ($propiedad->estadopropiedad === Propiedad::BAJA) {
                    return ['error' => 'Esa propiedad fue dada de baja: no se puede formalizar.'];
                }
                if (Contrato::vigentes()->where('idpropiedad', $propiedad->idpropiedad)->exists()) {
                    return ['error' => 'La propiedad ya tiene un contrato vigente.'];
                }
                if (! in_array($propiedad->estadopropiedad, [Propiedad::DISPONIBLE, Propiedad::RESERVADO], true)) {
                    return ['error' => 'La propiedad no está disponible (está ' . strtolower($propiedad->estadopropiedad) . ').'];
                }
                if (! $propiedad->tipopropiedad) {
                    return ['error' => 'La propiedad no tiene definido su tipo de operación (Venta, Alquiler o Anticrético). Complétalo con la modificación de la propiedad.'];
                }

                $reserva = Reserva::vigentes()->where('idpropiedad', $propiedad->idpropiedad)->lockForUpdate()->first();

                if ($reserva && $reserva->idcliente !== $cliente->idcliente) {
                    return ['error' => 'La propiedad está reservada por otro cliente.'];
                }
                if (! $reserva && $propiedad->estadopropiedad === Propiedad::RESERVADO) {
                    return ['error' => 'La propiedad figura como reservada pero no tiene una reserva vigente. Cámbiala a disponible o registra la reserva antes de formalizarla.'];
                }
                if (! $reserva && ! ClientesDelActor::permitido($usuario, $cliente)) {
                    return ['error' => 'Ese cliente no está entre tus prospectos.', 'campo' => 'idcliente'];
                }

                // Lo que el cliente pagó para reservar se descuenta del contrato: el monto no puede ser menor.
                if ($reserva && (float) $reserva->montousd > (float) $request->input('monto')) {
                    return ['error' => 'El monto del contrato no puede ser menor a lo que el cliente ya pagó por la reserva (USD ' . number_format((float) $reserva->montousd, 2, '.', ',') . ').', 'campo' => 'monto'];
                }

                // Paso 8: el contrato queda "Vigente". El tipo sale de la propiedad.
                $contrato = Contrato::create([
                    'idpropiedad' => $propiedad->idpropiedad,
                    'idcliente' => $cliente->idcliente,
                    'idusuario' => $usuario->idusuario,
                    'idreserva' => $reserva?->idreserva,
                    'tipocontrato' => $propiedad->tipopropiedad,
                    'monto' => $request->input('monto'),
                    'fechainicio' => $request->input('fechainicio'),
                    'fechafin' => $request->input('fechafin') ?: null,
                    'estado' => Contrato::VIGENTE,
                ]);

                // Si venía de una reserva, esta queda concretada y deja de estar vigente.
                $reserva?->update(['estado' => Reserva::CONCRETADA]);

                // Paso 9: la propiedad pasa a "Vendido" o "Alquilado" (CU8) con su historial. En el anticrético es "Alquilado".
                $nuevo = collect(Propiedad::estadosPermitidos($propiedad->tipopropiedad))->last();
                $motivo = "Contrato #{$contrato->idcontrato} ({$contrato->tipocontrato}) con {$cliente->nombre}";
                $anterior = $cambio->cambiar($propiedad, $nuevo, $motivo);

                return compact('contrato', 'propiedad', 'reserva', 'anterior', 'nuevo', 'motivo');
            });
        } catch (QueryException $error) {
            report($error);

            return back()->withInput()->with('error', 'No se pudo registrar el contrato por un problema con la base de datos. Intenta de nuevo en unos minutos.');
        }

        if (isset($resultado['error'])) {
            return back()->withInput()->withErrors([($resultado['campo'] ?? 'idpropiedad') => $resultado['error']]);
        }

        ['contrato' => $contrato, 'propiedad' => $propiedad, 'reserva' => $reserva, 'anterior' => $anterior, 'nuevo' => $nuevo, 'motivo' => $motivo] = $resultado;

        BitacoraService::registrar('CONTRATO_REGISTRADO', 'Contratos', "Registró el contrato #{$contrato->idcontrato} ({$contrato->tipocontrato}) de «{$propiedad->titulo}» ({$propiedad->codigo}) con {$cliente->nombre}", [
            'idcontrato' => $contrato->idcontrato,
            'idpropiedad' => $propiedad->idpropiedad,
            'idcliente' => $cliente->idcliente,
            'tipo' => $contrato->tipocontrato,
            'monto' => (string) $contrato->monto,
            'idreserva' => $reserva?->idreserva,
        ]);
        if ($reserva) {
            BitacoraService::registrar('RESERVA_CONCRETADA', 'Reservas', "La reserva #{$reserva->idreserva} se concretó en el contrato #{$contrato->idcontrato}", [
                'idreserva' => $reserva->idreserva,
                'idcontrato' => $contrato->idcontrato,
            ]);
        }
        $cambio->registrarEnBitacora($propiedad, $anterior, $nuevo, $motivo, ['idcontrato' => $contrato->idcontrato]);

        // Paso 10: confirmación.
        return redirect()->route('contratos.show', $contrato)
            ->with('status', "Contrato registrado: «{$propiedad->titulo}» ahora está " . strtolower($nuevo) . '.');
    }

    public function show(Request $request, Contrato $contrato): View
    {
        $this->autorizar($request, $contrato);

        return view('agendavisitaspagos::CU27_GestionarContratos.detalle', [
            'contrato' => $contrato->load(['propiedad.agente', 'cliente', 'reserva', 'agente', 'pagos']),
            'pagado' => $contrato->totalPagadoUsd(),
            'saldo' => $contrato->saldoUsd(),
        ]);
    }

    /** Flujo alternativo: cambiar el estado del contrato. Si se rescinde, la propiedad vuelve a "Disponible" (CU8). */
    public function cambiarEstado(CambiarEstadoContratoRequest $request, Contrato $contrato, CambioDeEstadoPropiedad $cambio): RedirectResponse
    {
        $nuevoEstado = $request->input('estado');
        $motivo = trim((string) $request->input('motivo')) ?: null;

        $resultado = DB::transaction(function () use ($contrato, $nuevoEstado, $motivo, $cambio) {
            $actual = Contrato::lockForUpdate()->findOrFail($contrato->idcontrato);

            if ($actual->estado !== Contrato::VIGENTE) {
                return ['error' => 'Solo se puede cambiar el estado de un contrato vigente: este contrato está ' . strtolower($actual->estado_etiqueta) . '.'];
            }

            $actual->update(['estado' => $nuevoEstado]);

            // Al rescindir, la propiedad vuelve a estar disponible (salvo que ya haya cambiado de estado por otro camino).
            $anterior = null;
            $propiedad = Propiedad::find($actual->idpropiedad);
            $motivoPropiedad = "Se rescindió el contrato #{$actual->idcontrato}" . ($motivo ? ": {$motivo}" : '');
            if ($nuevoEstado === Contrato::RESCINDIDO && $propiedad && in_array($propiedad->estadopropiedad, [Propiedad::VENDIDO, Propiedad::ALQUILADO], true)) {
                $anterior = $cambio->cambiar($propiedad, Propiedad::DISPONIBLE, $motivoPropiedad);
            }

            return compact('actual', 'propiedad', 'anterior', 'motivoPropiedad');
        });

        if (isset($resultado['error'])) {
            return back()->with('error', $resultado['error']);
        }

        $propiedad = $resultado['propiedad'];
        $accion = $nuevoEstado === Contrato::RESCINDIDO ? 'CONTRATO_RESCINDIDO' : 'CONTRATO_FINALIZADO';
        $verbo = $nuevoEstado === Contrato::RESCINDIDO ? 'Rescindió' : 'Finalizó';

        BitacoraService::registrar($accion, 'Contratos', "{$verbo} el contrato #{$contrato->idcontrato} de «{$propiedad?->titulo}»", [
            'idcontrato' => $contrato->idcontrato,
            'idpropiedad' => $contrato->idpropiedad,
            'motivo' => $motivo,
        ]);
        if ($resultado['anterior'] !== null) {
            $cambio->registrarEnBitacora($propiedad, $resultado['anterior'], Propiedad::DISPONIBLE, $resultado['motivoPropiedad'], ['idcontrato' => $contrato->idcontrato]);
        }

        return redirect()->route('contratos.show', $contrato)
            ->with('status', $nuevoEstado === Contrato::RESCINDIDO
                ? 'El contrato fue rescindido y la propiedad volvió a estar disponible.'
                : 'El contrato fue finalizado.');
    }

    /** El Agente solo accede a los contratos de las propiedades que registró; el Administrador, a todos. */
    private function autorizar(Request $request, Contrato $contrato): void
    {
        $usuario = $request->user();

        abort_if(
            $usuario->tieneRol(Rol::AGENTE) && $contrato->propiedad->idusuario !== $usuario->idusuario,
            403,
            'Este contrato no es de tus propiedades.'
        );
    }
}
