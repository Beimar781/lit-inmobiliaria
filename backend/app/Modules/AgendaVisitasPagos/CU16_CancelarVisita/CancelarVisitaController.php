<?php

namespace App\Modules\AgendaVisitasPagos\CU16_CancelarVisita;

use App\Http\Controllers\Controller;
use App\Models\Visita;
use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use App\Services\BitacoraService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CU16: Cancelar visita (Agente inmobiliario y Administrador)
 * Se entra desde el detalle de la visita en el calendario (CU17). La visita no se borra: queda "Cancelada" en el historial
 * y su horario queda libre para el agente.
 * Flujo principal:
 *  1. El actor abre el calendario de visitas (CU17) y selecciona la visita
 *  2. El actor elige la opción "Cancelar"                                      -> confirmar()
 *  3. El sistema verifica que el actor tenga permiso sobre la visita y que esta pueda cancelarse
 *                                                                              -> AgendaDeVisitas::autorizar() / estaActiva()
 *  4. El sistema solicita el motivo de la cancelación y la confirmación        -> cancelar.blade.php
 *  5. El actor escribe el motivo y confirma                                    -> cancelar()
 *  6. El sistema cambia el estado de la visita a "Cancelada" y registra la bitácora
 *  7. El sistema muestra la confirmación y actualiza el calendario
 * Flujo alternativo: si el actor desiste en la confirmación ("No, mantener la visita"), la visita sigue igual y no se registra nada.
 * Excepciones: visita inexistente (404), ya cancelada o realizada, visita de otro agente (403), falta el motivo, error de base de datos.
 */
class CancelarVisitaController extends Controller
{
    public function confirmar(Request $request, Visita $visita): View|RedirectResponse
    {
        AgendaDeVisitas::autorizar($request->user(), $visita);

        if (! $visita->estaActiva()) {
            return $this->noCancelable($visita);
        }

        return view('agendavisitaspagos::CU16_CancelarVisita.cancelar', [
            'visita' => $visita->load(['propiedad.agente', 'cliente']),
        ]);
    }

    public function cancelar(CancelarVisitaRequest $request, Visita $visita): RedirectResponse
    {
        AgendaDeVisitas::autorizar($request->user(), $visita);

        // Flujo alternativo: el actor desiste, no se cambia nada.
        if (! $request->confirmada()) {
            return redirect()->route('visitas.show', $visita)->with('status', 'La visita se mantiene sin cambios.');
        }

        if (! $visita->estaActiva()) {
            return $this->noCancelable($visita);
        }

        $estadoAnterior = $visita->estado;
        $motivo = trim($request->input('motivo'));

        try {
            $cancelada = DB::transaction(function () use ($visita, $motivo) {
                $actual = Visita::whereKey($visita->idvisita)->lockForUpdate()->first();

                if (! $actual || ! $actual->estaActiva()) {
                    return null;
                }

                // Paso 6: queda "Cancelada" (no se borra); el horario del agente queda libre.
                $actual->update(['estado' => Visita::CANCELADA, 'motivo' => $motivo]);

                return $actual;
            });
        } catch (QueryException $error) {
            report($error);

            return back()->withInput()->with('error', 'No se pudo cancelar la visita por un problema con la base de datos. Intenta de nuevo en unos minutos.');
        }

        if ($cancelada === null) {
            return $this->noCancelable($visita->fresh());
        }

        $cancelada->load(['propiedad', 'cliente']);

        BitacoraService::registrar('VISITA_CANCELADA', 'Agenda', "Canceló la visita de {$cancelada->cliente->nombre} a «{$cancelada->propiedad->titulo}» ({$cancelada->propiedad->codigo}) del " . $cancelada->fecha->format('d/m/Y') . ' a las ' . $cancelada->hora_corta, [
            'idvisita' => $cancelada->idvisita,
            'idpropiedad' => $cancelada->idpropiedad,
            'idagente' => $cancelada->propiedad->idusuario,
            'fecha' => $cancelada->fecha->format('Y-m-d'),
            'hora' => $cancelada->hora_corta,
            'estado_anterior' => $estadoAnterior,
            'motivo' => $motivo,
        ]);

        // Paso 7: confirmación y calendario actualizado (en el mes de la visita).
        return redirect()->route('visitas.calendario', ['fecha' => $cancelada->fecha->format('Y-m-d')])
            ->with('status', 'La visita fue cancelada y se conserva en el historial. El horario quedó libre.');
    }

    private function noCancelable(Visita $visita): RedirectResponse
    {
        return redirect()->route('visitas.show', $visita)
            ->with('error', 'La visita no puede cancelarse porque ya está ' . strtolower($visita->estado_etiqueta) . '.');
    }
}
