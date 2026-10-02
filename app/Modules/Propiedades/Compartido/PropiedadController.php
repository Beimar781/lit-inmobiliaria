<?php

namespace App\Modules\Propiedades\Compartido;

use App\Http\Controllers\Controller;
use App\Models\Propiedad;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Inventario: listado de propiedades vigentes. Es el punto de partida de CU6 y CU7 ("busca y selecciona la propiedad"). */
class PropiedadController extends Controller
{
    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('q'));

        $propiedades = Propiedad::vigentes()
            ->with(['categoria', 'ubicacion', 'imagenes'])
            ->when($busqueda !== '', fn ($consulta) => $consulta->where('titulo', 'like', "%{$busqueda}%"))
            ->when($request->filled('estado'), fn ($consulta) => $consulta->where('estadopropiedad', $request->query('estado')))
            ->when($request->filled('tipo'), fn ($consulta) => $consulta->where('tipopropiedad', $request->query('tipo')))
            ->orderByDesc('idpropiedad')
            ->paginate(9)
            ->withQueryString();

        return view('propiedades::Compartido.index', [
            'propiedades' => $propiedades,
            'busqueda' => $busqueda,
        ]);
    }
}
