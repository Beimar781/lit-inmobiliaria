<?php

namespace App\Modules\GestionPropiedades\CU8_CambiarEstado;

use App\Http\Controllers\Controller;
use App\Models\Historial;
use App\Models\Propiedad;
use App\Services\BitacoraService;
use App\Services\HistorialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CU8: Cambiar estado de propiedad (Administrador o Agente)
 * El Administrador puede cambiar el estado de cualquier propiedad; el Agente, solo el de las que él registró.
 * Flujo principal:
 *  1. El actor selecciona "Cambiar estado" en una propiedad del inventario    -> listado en Compartido/index.blade.php
 *  2. El sistema verifica que el actor tenga permiso sobre esa propiedad       -> autorizar()
 *  3. Muestra el estado actual y los estados permitidos según el tipo de operación -> mostrar() / cambiar.blade.php
 *  4. El actor elige el nuevo estado y, si desea, escribe un motivo
 *  5. El sistema valida que el estado sea permitido y distinto del actual      -> CambiarEstadoRequest
 *  6. Actualiza el estado y registra el cambio en el historial y la bitácora   -> actualizar()
 *  7. El sistema confirma la actualización
 * La baja de una propiedad no se hace aquí: se hace con el CU7.
 */
class CambiarEstadoController extends Controller
{
    public function mostrar(Propiedad $propiedad): View
    {
        $this->autorizar($propiedad);

        return view('gestionpropiedades::CU8_CambiarEstado.cambiar', [
            'propiedad' => $propiedad->load(['categoria', 'ubicacion', 'imagenes']),
            'estados' => Propiedad::estadosPermitidos($propiedad->tipopropiedad),
        ]);
    }

    public function actualizar(CambiarEstadoRequest $request, Propiedad $propiedad): RedirectResponse
    {
        $this->autorizar($propiedad);

        $nuevo = $request->input('estadopropiedad');
        $motivo = trim((string) $request->input('motivo')) ?: null;

        // El cambio y su historial se guardan juntos, o ninguno. La fila se bloquea mientras tanto para que dos
        // cambios casi simultáneos no dejen el historial con un "estado anterior" equivocado.
        $anterior = DB::transaction(function () use ($propiedad, $nuevo, $motivo) {
            $actual = Propiedad::lockForUpdate()->findOrFail($propiedad->idpropiedad);
            abort_if($actual->estadopropiedad === Propiedad::BAJA, 404);

            $anterior = $actual->estadopropiedad;
            $actual->update(['estadopropiedad' => $nuevo]);

            HistorialService::registrar(
                Historial::CAMBIO_ESTADO,
                $propiedad->idpropiedad,
                ['estado' => $anterior],
                ['estado' => $nuevo],
                $motivo
            );

            return $anterior;
        });

        // La bitácora va fuera de la transacción: si fallara, no debe deshacer el cambio ya hecho.
        BitacoraService::registrar('PROPIEDAD_ESTADO', 'Propiedades', "Cambió el estado de la propiedad «{$propiedad->titulo}» (ID {$propiedad->idpropiedad}) de {$anterior} a {$nuevo}", [
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'motivo' => $motivo,
        ]);

        return redirect()->route('propiedades.index')
            ->with('status', 'El estado de la propiedad ahora es «' . ucfirst(strtolower($nuevo)) . '».');
    }

    /** No existe o fue dada de baja -> 404. Un Agente que no la registró -> 403. */
    private function autorizar(Propiedad $propiedad): void
    {
        abort_if($propiedad->estadopropiedad === Propiedad::BAJA, 404);

        $usuario = auth()->user();

        abort_unless(
            $usuario->tieneRol('Administrador') || $propiedad->idusuario === $usuario->idusuario,
            403,
            'Solo puedes cambiar el estado de las propiedades que tú registraste.'
        );
    }
}
