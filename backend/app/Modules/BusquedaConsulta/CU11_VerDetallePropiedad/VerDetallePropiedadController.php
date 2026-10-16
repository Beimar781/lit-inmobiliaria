<?php

namespace App\Modules\BusquedaConsulta\CU11_VerDetallePropiedad;

use App\Http\Controllers\Controller;
use App\Models\Propiedad;
use Illuminate\View\View;

/**
 * CU11: Ver detalle de propiedad (Cliente público)
 * No requiere iniciar sesión. Se puede abrir desde el listado o con el enlace/código de la propiedad.
 * Flujo principal:
 *  1. La persona selecciona una propiedad del listado (o abre su enlace)   -> mostrar()
 *  2. El sistema muestra la ficha: galería, mapa, características, precio, estado, código y agente (sin datos del propietario)
 *  3. Si está "Disponible" ofrece "Me interesa" (CU12) y el contacto por WhatsApp; si no, solo muestra su estado
 * Excepción: la propiedad no existe o fue dada de baja -> error 404.
 */
class VerDetallePropiedadController extends Controller
{
    public function mostrar(Propiedad $propiedad): View
    {
        abort_if($propiedad->estadopropiedad === Propiedad::BAJA, 404);

        // Nunca se carga el propietario: sus datos no se muestran al público.
        $propiedad->load(['categoria', 'ubicacion', 'agente', 'imagenes']);

        return view('busquedaconsulta::CU11_VerDetallePropiedad.detalle', [
            'propiedad' => $propiedad,
            'imagenes' => $propiedad->imagenes->sortByDesc('portada')->values(), // la portada va primero
            'disponible' => $propiedad->estadopropiedad === Propiedad::DISPONIBLE,
        ]);
    }
}
