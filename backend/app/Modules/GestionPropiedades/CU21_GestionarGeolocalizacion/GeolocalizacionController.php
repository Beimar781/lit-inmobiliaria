<?php

namespace App\Modules\GestionPropiedades\CU21_GestionarGeolocalizacion;

use App\Http\Controllers\Controller;
use App\Models\Historial;
use App\Models\Propiedad;
use App\Models\Ubicacion;
use App\Services\BitacoraService;
use App\Services\HistorialService;
use App\Support\ParametrosConsulta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CU21: Gestionar geolocalización (Administrador, o Agente en las propiedades que él registró)
 *  1. Muestra el mapa con la ubicación actual y el límite del 4.º anillo   -> ubicacion()
 *  2. Guarda zona, dirección y coordenadas (validadas en UbicacionRequest) -> guardar()
 *  3. Mapa de la cartera: propiedades vigentes con ubicación, por zona     -> mapa()
 */
class GeolocalizacionController extends Controller
{
    private const CAMPOS = ['zona', 'direccion', 'latitud', 'longitud'];

    public function ubicacion(Propiedad $propiedad): View
    {
        self::autorizar($propiedad);

        return view('gestionpropiedades::CU21_GestionarGeolocalizacion.ubicacion', [
            'propiedad' => $propiedad->load('ubicacion'),
        ]);
    }

    public function guardar(UbicacionRequest $request, Propiedad $propiedad): RedirectResponse
    {
        DB::transaction(function () use ($request, $propiedad) {
            $antes = Arr::only(HistorialService::instantanea($propiedad->fresh('ubicacion')), self::CAMPOS);
            // Las coordenadas se comparan como números con 7 decimales (la columna es decimal(10,7)).
            foreach (['latitud', 'longitud'] as $campo) {
                if ($antes[$campo] !== null) {
                    $antes[$campo] = number_format((float) $antes[$campo], 7, '.', '');
                }
            }

            $nuevos = [
                'zona' => $request->input('zona'),
                'direccion' => $request->input('direccion'),
                // Se guardan con 7 decimales (como la columna) para comparar igual que lo guardado.
                'latitud' => number_format((float) $request->input('latitud'), 7, '.', ''),
                'longitud' => number_format((float) $request->input('longitud'), 7, '.', ''),
            ];

            if ($propiedad->ubicacion) {
                $propiedad->ubicacion->update($nuevos);
            } else {
                $ubicacion = Ubicacion::create($nuevos + ['ciudad' => config('santacruz.ciudad')]);
                $propiedad->update(['idubicacion' => $ubicacion->idubicacion]);
            }

            [$anterior, $actual] = HistorialService::cambios($antes, $nuevos);

            if ($actual !== []) {
                HistorialService::registrar(Historial::MODIFICACION, $propiedad->idpropiedad, $anterior, $actual, 'Ubicación actualizada');
                BitacoraService::registrar('UBICACION_MODIFICADA', 'Propiedades', "Actualizó la ubicación de la propiedad {$propiedad->codigo}", [
                    'idpropiedad' => $propiedad->idpropiedad,
                    'campos' => array_keys($actual),
                ]);
            }
        });

        return redirect()->route('propiedades.ubicacion', $propiedad)->with('status', 'La ubicación se guardó correctamente.');
    }

    public function mapa(Request $request): View
    {
        $zona = ParametrosConsulta::texto($request, 'zona');

        $base = Propiedad::vigentes()
            ->join('ubicacion', 'ubicacion.idubicacion', '=', 'propiedad.idubicacion')
            ->whereNotNull('ubicacion.latitud')
            ->whereNotNull('ubicacion.longitud');

        $zonas = (clone $base)->whereNotNull('ubicacion.zona')->distinct()->orderBy('ubicacion.zona')->pluck('ubicacion.zona');

        $propiedades = $base
            ->when($zona !== '', fn ($c) => $c->where('ubicacion.zona', $zona))
            ->select('propiedad.*')
            ->with('ubicacion')
            ->orderBy('propiedad.idpropiedad')
            ->get();

        $marcadores = $propiedades->map(fn (Propiedad $p) => [
            'codigo' => $p->codigo,
            'titulo' => $p->titulo,
            'precio' => '$ ' . number_format((float) $p->precio, 0, '.', ','),
            'estado' => ucfirst(strtolower($p->estadopropiedad)),
            'zona' => $p->ubicacion->zona,
            'lat' => (float) $p->ubicacion->latitud,
            'lng' => (float) $p->ubicacion->longitud,
            'url' => route('catalogo.detalle', $p),
        ])->values();

        return view('gestionpropiedades::CU21_GestionarGeolocalizacion.mapa', [
            'marcadores' => $marcadores,
            'zonas' => $zonas,
            'zona' => $zona,
        ]);
    }

    /** Propiedad dada de baja -> 404. Un Agente que no la registró -> 403. */
    public static function autorizar(Propiedad $propiedad): void
    {
        abort_if($propiedad->estadopropiedad === Propiedad::BAJA, 404);

        $usuario = auth()->user();

        abort_unless(
            $usuario->tieneRol('Administrador') || $propiedad->idusuario === $usuario->idusuario,
            403,
            'Solo puedes cambiar la ubicación de las propiedades que tú registraste.'
        );
    }
}