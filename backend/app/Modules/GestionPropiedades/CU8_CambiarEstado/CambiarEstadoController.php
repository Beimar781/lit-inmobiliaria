<?php

namespace App\Modules\GestionPropiedades\CU8_CambiarEstado;

use App\Http\Controllers\Controller;
use App\Models\Propiedad;
use App\Modules\GestionPropiedades\Compartido\CambioDeEstadoPropiedad;
use Illuminate\Http\RedirectResponse;
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

    public function actualizar(CambiarEstadoRequest $request, Propiedad $propiedad, CambioDeEstadoPropiedad $cambio): RedirectResponse
    {
        $this->autorizar($propiedad);

        $nuevo = $request->input('estadopropiedad');
        $motivo = trim((string) $request->input('motivo')) ?: null;

        // El cambio y su historial se guardan juntos, o ninguno (lógica compartida con CU26 y CU27).
        $anterior = $cambio->cambiar($propiedad, $nuevo, $motivo);

        $cambio->registrarEnBitacora($propiedad, $anterior, $nuevo, $motivo);

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
