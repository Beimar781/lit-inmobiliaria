<?php

namespace App\Modules\AgendaVisitasPagos\CU15_ReagendarVisita;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Models\Visita;
use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use App\Models\Propiedad;
use App\Services\BitacoraService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CU15: Reagendar visita (Agente inmobiliario y Administrador)
 * Se entra desde el detalle de la visita en el calendario (CU17). El Agente solo reagenda visitas de sus propiedades;
 * el Administrador, las de cualquier agente.
 * Flujo principal:
 *  1. El actor abre el calendario de visitas (CU17) y selecciona la visita
 *  2. El actor elige la opción "Reagendar"                                     -> editar()
 *  3. El sistema verifica que el actor tenga permiso sobre la visita y que esta pueda reagendarse
 *                                                                              -> AgendaDeVisitas::autorizar() / estaActiva()
 *  4. El sistema muestra la fecha y hora actuales de la visita                -> reagendar.blade.php
 *  5. El actor ingresa la nueva fecha, la nueva hora y el motivo del cambio    -> actualizar()
 *  6. El sistema valida que la fecha sea futura y que el agente no tenga otra visita en ese horario
 *                                                                              -> ReagendarVisitaRequest / AgendaDeVisitas::ocupado()
 *  7. El sistema actualiza la visita, cambia su estado a "Reagendada" (el horario anterior queda libre) y registra la bitácora
 *  8. El sistema muestra la confirmación y actualiza el calendario             -> redirige al calendario del mes de la nueva fecha
 * Flujo alternativo: horario ocupado -> se informa y se vuelve al paso 5.
 * Excepciones: visita inexistente (404), cancelada o ya realizada, visita de otro agente (403), nueva fecha pasada o igual a la actual,
 * falta el motivo, horario ocupado, error de base de datos.
 */
class ReagendarVisitaController extends Controller
{
    public function editar(Request $request, Visita $visita, AgendaDeVisitas $agenda): View|RedirectResponse
    {
        AgendaDeVisitas::autorizar($request->user(), $visita);

        if (! $visita->estaActiva()) {
            return $this->noReagendable($visita);
        }

        $visita->load(['propiedad.agente', 'cliente']);

        return view('agendavisitaspagos::CU15_ReagendarVisita.reagendar', [
            'visita' => $visita,
            'horas' => AgendaDeVisitas::horas(),
            'ocupadas' => $agenda->ocupadasProximas($visita->propiedad, 90, $visita->idvisita),
            'minimo' => AgendaDeVisitas::ahora()->toDateString(),
        ]);
    }

    public function actualizar(ReagendarVisitaRequest $request, Visita $visita, AgendaDeVisitas $agenda): RedirectResponse
    {
        AgendaDeVisitas::autorizar($request->user(), $visita);

        if (! $visita->estaActiva()) {
            return $this->noReagendable($visita);
        }

        $fecha = $request->input('fecha');
        $hora = AgendaDeVisitas::hora($request->input('hora'));
        $fechaAnterior = $visita->fecha->format('Y-m-d');
        $horaAnterior = AgendaDeVisitas::hora(substr((string) $visita->hora, 0, 5));
        $estadoAnterior = $visita->estado;

        // Excepción: la nueva fecha y hora no pueden ser iguales a las actuales.
        if ($fecha === $fechaAnterior && $hora === $horaAnterior) {
            return back()->withInput()->withErrors(['fecha' => 'Elige una fecha u hora distinta de la actual.']);
        }

        try {
            $resultado = DB::transaction(function () use ($visita, $agenda, $request, $fecha, $hora) {
                // Se bloquea al agente responsable (o la propiedad, si no tiene) para que dos personas no tomen el mismo horario a la vez.
                $propiedad = Propiedad::findOrFail($visita->idpropiedad);
                if ($propiedad->idusuario) {
                    Usuario::whereKey($propiedad->idusuario)->lockForUpdate()->first();
                }
                $actual = Visita::whereKey($visita->idvisita)->lockForUpdate()->first();

                if (! $actual || ! $actual->estaActiva()) {
                    return ['no_reagendable' => true];
                }

                // Paso 6: el agente no puede tener otra visita en el nuevo horario (la propia visita no cuenta).
                if ($agenda->ocupado($propiedad, $fecha, $hora, $actual->idvisita)) {
                    return ['ocupado' => true];
                }

                // Paso 7: la visita queda con la nueva fecha y hora, "Reagendada"; el horario anterior queda libre.
                $actual->update([
                    'fecha' => $fecha,
                    'hora' => $hora,
                    'estado' => Visita::REAGENDADA,
                    'motivo' => trim($request->input('motivo')),
                ]);

                return ['visita' => $actual, 'propiedad' => $propiedad];
            });
        } catch (QueryException $error) {
            report($error);

            return back()->withInput()->with('error', 'No se pudo reagendar la visita por un problema con la base de datos. Intenta de nuevo en unos minutos.');
        }

        if (isset($resultado['no_reagendable'])) {
            return $this->noReagendable($visita->fresh());
        }

        // Flujo alternativo: horario ocupado -> se informa y el actor elige otro (vuelve al paso 5).
        if (isset($resultado['ocupado'])) {
            $libres = $agenda->horasLibres($visita->propiedad, $fecha, $visita->idvisita);
            $sugerencia = $libres
                ? ' Horarios libres ese día: ' . implode(', ', $libres) . '.'
                : ' Ese día ya no quedan horarios libres: elige otra fecha.';

            return back()->withInput()->withErrors(['hora' => 'Ese horario ya está ocupado.' . $sugerencia]);
        }

        /** @var Visita $nueva */
        $nueva = $resultado['visita']->load('cliente');
        $propiedad = $resultado['propiedad'];

        BitacoraService::registrar('VISITA_REAGENDADA', 'Agenda', "Reagendó la visita de {$nueva->cliente->nombre} a «{$propiedad->titulo}» ({$propiedad->codigo}): del " . date('d/m/Y', strtotime($fechaAnterior)) . ' ' . substr($horaAnterior, 0, 5) . ' al ' . date('d/m/Y', strtotime($fecha)) . ' ' . substr($hora, 0, 5), [
            'idvisita' => $nueva->idvisita,
            'idpropiedad' => $propiedad->idpropiedad,
            'idagente' => $propiedad->idusuario,
            'fecha_anterior' => $fechaAnterior,
            'hora_anterior' => substr($horaAnterior, 0, 5),
            'fecha_nueva' => $fecha,
            'hora_nueva' => substr($hora, 0, 5),
            'estado_anterior' => $estadoAnterior,
            'motivo' => $nueva->motivo,
        ]);

        // Paso 8: confirmación y calendario actualizado (en el mes de la nueva fecha).
        return redirect()->route('visitas.calendario', ['fecha' => $fecha])
            ->with('status', 'La visita fue reagendada para el ' . date('d/m/Y', strtotime($fecha)) . ' a las ' . substr($hora, 0, 5) . '.');
    }

    private function noReagendable(Visita $visita): RedirectResponse
    {
        return redirect()->route('visitas.show', $visita)
            ->with('error', 'La visita no puede reagendarse porque está ' . strtolower($visita->estado_etiqueta) . '.');
    }
}
