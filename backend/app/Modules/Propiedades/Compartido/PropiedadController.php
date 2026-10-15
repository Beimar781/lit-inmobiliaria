<?php

namespace App\Modules\Propiedades\Compartido;

use App\Http\Controllers\Controller;
use App\Models\Propiedad;
use App\Modules\Busqueda\Compartido\BuscadorPropiedades;
use App\Support\ParametrosConsulta;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Inventario: listado de propiedades vigentes. Es el punto de partida de CU6, CU7 y CU8 ("busca y selecciona la propiedad"). */
class PropiedadController extends Controller
{
    public function index(Request $request): View
    {
        $busqueda = ParametrosConsulta::texto($request, 'q');
        $estado = ParametrosConsulta::texto($request, 'estado');
        $tipo = ParametrosConsulta::texto($request, 'tipo');

        // La palabra clave (título, zona, dirección o código) es la misma búsqueda del CU9.
        $propiedades = BuscadorPropiedades::texto(Propiedad::vigentes(), $busqueda)
            ->with(['categoria', 'ubicacion', 'imagenes'])
            ->when($estado !== '', fn ($consulta) => $consulta->where('estadopropiedad', $estado))
            ->when($tipo !== '', fn ($consulta) => $consulta->where('tipopropiedad', $tipo))
            ->orderByDesc('idpropiedad')
            ->paginate(9)
            ->withQueryString();

        return view('propiedades::Compartido.index', [
            'propiedades' => $propiedades,
            'busqueda' => $busqueda,
        ]);
    }
}
