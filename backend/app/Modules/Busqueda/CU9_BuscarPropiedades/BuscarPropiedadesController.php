<?php

namespace App\Modules\Busqueda\CU9_BuscarPropiedades;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Propiedad;
use App\Models\Ubicacion;
use App\Modules\Busqueda\Compartido\BuscadorPropiedades;
use App\Modules\Busqueda\CU10_FiltrarPropiedades\FiltrarPropiedadesRequest;
use Illuminate\View\View;

/**
 * CU9: Buscar propiedades (Cliente público; el Agente usa la misma búsqueda en su inventario)
 * No requiere iniciar sesión. Solo se muestran las propiedades en estado "Disponible".
 * Flujo principal:
 *  1. La persona abre el catálogo de propiedades                          -> index()
 *  2. Escribe una palabra clave (título, zona o dirección) o un código (LIT-0007) y pulsa "Buscar"
 *  3. El sistema valida el texto ingresado                                -> FiltrarPropiedadesRequest
 *  4. Ejecuta la búsqueda solo entre propiedades "Disponibles"            -> BuscadorPropiedades::texto()
 *  5. Muestra el listado (9 por página, de la más reciente a la más antigua) -> index.blade.php
 * El CU10 (filtros avanzados) se aplica sobre esta misma pantalla: ver CU10_FiltrarPropiedades.
 */
class BuscarPropiedadesController extends Controller
{
    public function index(FiltrarPropiedadesRequest $request): View
    {
        $filtros = $request->filtros();
        $busqueda = trim((string) $request->validated('q'));

        $consulta = Propiedad::disponibles()->with(['categoria', 'ubicacion', 'imagenes']);
        BuscadorPropiedades::texto($consulta, $busqueda);
        BuscadorPropiedades::filtros($consulta, $filtros);

        return view('busqueda::CU9_BuscarPropiedades.index', [
            'propiedades' => $consulta->orderByDesc('idpropiedad')->paginate(9)->withQueryString(),
            'busqueda' => $busqueda,
            'filtros' => $filtros,
            'categorias' => Categoria::orderBy('nombre')->pluck('nombre', 'idcategoria'),
            'zonas' => Ubicacion::whereHas('propiedades', fn ($p) => $p->disponibles())
                ->whereNotNull('zona')->distinct()->orderBy('zona')->pluck('zona'),
        ]);
    }
}
