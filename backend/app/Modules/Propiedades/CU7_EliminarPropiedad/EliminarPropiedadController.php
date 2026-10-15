<?php

namespace App\Modules\Propiedades\CU7_EliminarPropiedad;

use App\Http\Controllers\Controller;
use App\Models\Historial;
use App\Models\Propiedad;
use App\Services\BitacoraService;
use App\Services\HistorialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CU7: Eliminar propiedad (solo Administrador) - baja lógica
 * Flujo principal:
 *  1. El administrador busca y selecciona la propiedad          -> listado en Compartido/index.blade.php
 *  2. Selecciona "Dar de baja"                                  -> confirmar()
 *  3-4. El sistema pide confirmación (con motivo) y el administrador confirma -> baja.blade.php
 *  5. El sistema verifica restricciones y da de baja el registro -> darDeBaja()
 *
 * La propiedad NO se borra de la base de datos: pasa a estado BAJA y deja de verse en el catálogo,
 * así se conserva su historial.
 * Restricción: una propiedad RESERVADA no puede darse de baja (reserva vigente).
 */
class EliminarPropiedadController extends Controller
{
    public function confirmar(Propiedad $propiedad): View
    {
        abort_if($propiedad->estadopropiedad === Propiedad::BAJA, 404);

        return view('propiedades::CU7_EliminarPropiedad.baja', [
            'propiedad' => $propiedad->load(['categoria', 'ubicacion', 'imagenes']),
        ]);
    }

    public function darDeBaja(Request $request, Propiedad $propiedad): RedirectResponse
    {
        abort_if($propiedad->estadopropiedad === Propiedad::BAJA, 404);

        $request->validate(
            ['motivo' => ['required', 'string', 'min:5', 'max:500']],
            ['motivo.required' => 'Indica el motivo de la baja.', 'motivo.min' => 'El motivo debe tener al menos 5 caracteres.']
        );

        if ($propiedad->estadopropiedad === Propiedad::RESERVADO) {
            return back()->withInput()->withErrors([
                'propiedad' => 'No se puede dar de baja: la propiedad tiene una reserva vigente.',
            ]);
        }

        $estadoAnterior = $propiedad->estadopropiedad;

        $propiedad->update(['estadopropiedad' => Propiedad::BAJA]);

        HistorialService::registrar(
            Historial::BAJA,
            $propiedad->idpropiedad,
            ['estado' => $estadoAnterior],
            ['estado' => Propiedad::BAJA],
            $request->input('motivo')
        );

        BitacoraService::registrar('PROPIEDAD_BAJA', 'Propiedades', "Dio de baja la propiedad «{$propiedad->titulo}» (ID {$propiedad->idpropiedad})", [
            'estado_anterior' => $estadoAnterior,
            'motivo' => $request->input('motivo'),
        ]);

        return redirect()->route('propiedades.index')->with('status', 'La propiedad fue dada de baja.');
    }
}
