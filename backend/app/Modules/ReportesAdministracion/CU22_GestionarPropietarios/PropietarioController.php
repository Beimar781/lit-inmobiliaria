<?php

namespace App\Modules\ReportesAdministracion\CU22_GestionarPropietarios;

use App\Http\Controllers\Controller;
use App\Models\Propietario;
use App\Services\BitacoraService;
use App\Support\ParametrosConsulta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CU22: Gestionar propietarios (Agente y Administrador; eliminar solo el Administrador)
 *  1. Lista y busca propietarios con su cantidad de propiedades -> index()
 *  2. Registra un propietario                                    -> guardar()
 *  3. Edita sus datos                                            -> editar() / actualizar()
 *  4. Elimina solo si no tiene propiedades asociadas             -> eliminar()
 */
class PropietarioController extends Controller
{
    public function index(Request $request): View
    {
        $busqueda = ParametrosConsulta::texto($request, 'q');

        $propietarios = Propietario::withCount('propiedades')
            ->when($busqueda !== '', function ($consulta) use ($busqueda) {
                $consulta->where(function ($donde) use ($busqueda) {
                    $donde->whereLike('nombre', "%{$busqueda}%")
                        ->orWhereLike('email', "%{$busqueda}%")
                        ->orWhereLike('telefono', "%{$busqueda}%");
                });
            })
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        return view('reportesadministracion::CU22_GestionarPropietarios.index', [
            'propietarios' => $propietarios,
            'busqueda' => $busqueda,
        ]);
    }

    public function guardar(PropietarioRequest $request): RedirectResponse
    {
        $propietario = Propietario::create($request->validated());

        BitacoraService::registrar('PROPIETARIO_CREADO', 'Propiedades', "Registró al propietario {$propietario->nombre}", [
            'idpropietario' => $propietario->idpropietario,
        ]);

        return redirect()->route('propietarios.index')->with('status', 'Propietario registrado correctamente.');
    }

    public function editar(Propietario $propietario): View
    {
        return view('reportesadministracion::CU22_GestionarPropietarios.editar', ['propietario' => $propietario]);
    }

    public function actualizar(PropietarioRequest $request, Propietario $propietario): RedirectResponse
    {
        $propietario->update($request->validated());

        BitacoraService::registrar('PROPIETARIO_MODIFICADO', 'Propiedades', "Modificó al propietario {$propietario->nombre}", [
            'idpropietario' => $propietario->idpropietario,
        ]);

        return redirect()->route('propietarios.index')->with('status', 'Propietario actualizado correctamente.');
    }

    public function eliminar(Propietario $propietario): RedirectResponse
    {
        // Cuenta todas las propiedades, también las dadas de baja.
        if ($propietario->propiedades()->exists()) {
            return redirect()->route('propietarios.index')
                ->with('error', 'No se puede eliminar: el propietario tiene propiedades asociadas.');
        }

        $propietario->delete();

        BitacoraService::registrar('PROPIETARIO_ELIMINADO', 'Propiedades', "Eliminó al propietario {$propietario->nombre}", [
            'idpropietario' => $propietario->idpropietario,
        ]);

        return redirect()->route('propietarios.index')->with('status', 'Propietario eliminado correctamente.');
    }
}