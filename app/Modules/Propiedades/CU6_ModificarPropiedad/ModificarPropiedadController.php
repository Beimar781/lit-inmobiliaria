<?php

namespace App\Modules\Propiedades\CU6_ModificarPropiedad;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Historial;
use App\Models\Imagen;
use App\Models\Propiedad;
use App\Models\Propietario;
use App\Models\Ubicacion;
use App\Modules\Propiedades\Compartido\GestorImagenes;
use App\Services\BitacoraService;
use App\Services\HistorialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CU6: Modificar propiedad (Administrador o Agente)
 * Flujo principal:
 *  1. El usuario busca y selecciona la propiedad en el inventario  -> listado en Compartido/index.blade.php
 *  2. Selecciona "Modificar propiedad"                              -> editar()
 *  3. El sistema carga los datos actuales en un formulario editable -> editar.blade.php
 *  4-5. Actualiza los campos (precio, estado, descripción...) y confirma
 *  6. El sistema valida, actualiza y genera un registro en el historial -> actualizar()
 * Excepción: propiedad no encontrada o dada de baja -> error 404.
 */
class ModificarPropiedadController extends Controller
{
    public function editar(Propiedad $propiedad): View
    {
        abort_if($propiedad->estadopropiedad === Propiedad::BAJA, 404);

        return view('propiedades::CU6_ModificarPropiedad.editar', [
            'propiedad' => $propiedad->load(['ubicacion', 'imagenes']),
            'categorias' => Categoria::orderBy('nombre')->pluck('nombre', 'idcategoria'),
            'propietarios' => Propietario::orderBy('nombre')->pluck('nombre', 'idpropietario'),
        ]);
    }

    public function actualizar(ModificarPropiedadRequest $request, Propiedad $propiedad): RedirectResponse
    {
        abort_if($propiedad->estadopropiedad === Propiedad::BAJA, 404);

        DB::transaction(function () use ($request, $propiedad) {
            $antes = HistorialService::instantanea($propiedad->fresh('ubicacion'));

            $idpropietario = $request->input('idpropietario') ?: Propietario::create([
                'nombre' => $request->input('propietario_nombre'),
                'telefono' => $request->input('propietario_telefono'),
                'email' => $request->input('propietario_email'),
                'direccion' => $request->input('propietario_direccion'),
            ])->idpropietario;

            $datosUbicacion = [
                'ciudad' => config('santacruz.ciudad'),
                'zona' => $request->input('zona'),
                'direccion' => $request->input('direccion'),
                'latitud' => $request->input('latitud'),
                'longitud' => $request->input('longitud'),
            ];

            $ubicacion = $propiedad->ubicacion;
            if ($ubicacion) {
                $ubicacion->update($datosUbicacion);
            } else {
                $ubicacion = Ubicacion::create($datosUbicacion);
            }

            $propiedad->update([
                'idpropietario' => $idpropietario,
                'idcategoria' => $request->input('idcategoria'),
                'idubicacion' => $ubicacion->idubicacion,
                'titulo' => $request->input('titulo'),
                'descripcion' => $request->input('descripcion'),
                'precio' => $request->input('precio'),
                'tipopropiedad' => $request->input('tipopropiedad'),
                'estadopropiedad' => $request->input('estadopropiedad'),
                'superficie' => $request->input('superficie'),
                'areaconstruida' => $request->input('areaconstruida'),
                'habitaciones' => $request->input('habitaciones'),
                'banos' => $request->input('banos'),
                'antiguedad' => $request->input('antiguedad'),
            ]);

            // Imágenes: borrar las marcadas (solo de esta propiedad) y agregar las nuevas
            $idsAEliminar = (array) $request->input('eliminar_imagenes', []);
            $imagenesBorradas = Imagen::where('idpropiedad', $propiedad->idpropiedad)
                ->whereIn('idimagen', $idsAEliminar)
                ->get();
            $imagenesBorradas->each(fn (Imagen $imagen) => GestorImagenes::eliminar($imagen));

            $nuevas = $request->file('imagenes') ?? [];
            $creadas = GestorImagenes::guardar($propiedad, $nuevas);

            $portadaAntes = Imagen::where('idpropiedad', $propiedad->idpropiedad)->where('portada', true)->value('idimagen');
            GestorImagenes::definirPortada($propiedad, $request->input('portada'), $creadas);
            $portadaDespues = Imagen::where('idpropiedad', $propiedad->idpropiedad)->where('portada', true)->value('idimagen');
            $cambioPortada = $portadaAntes !== $portadaDespues && $portadaAntes !== null && ! $imagenesBorradas->contains('idimagen', $portadaAntes);

            // Historial: solo si algo cambió
            $despues = HistorialService::instantanea($propiedad->fresh('ubicacion'));
            [$anterior, $actual] = HistorialService::cambios($antes, $despues);

            if ($anterior !== [] || $actual !== []) {
                HistorialService::registrar(Historial::MODIFICACION, $propiedad->idpropiedad, $anterior, $actual);
            }

            if ($anterior !== [] || $actual !== [] || $imagenesBorradas->isNotEmpty() || count($nuevas) > 0 || $cambioPortada) {
                BitacoraService::registrar('PROPIEDAD_MODIFICADA', 'Propiedades', "Modificó la propiedad «{$propiedad->titulo}» (ID {$propiedad->idpropiedad})", [
                    'antes' => $anterior,
                    'despues' => $actual,
                    'imagenes_eliminadas' => $imagenesBorradas->count(),
                    'imagenes_agregadas' => count($nuevas),
                    'portada_cambiada' => $cambioPortada,
                ]);
            }
        });

        return redirect()->route('propiedades.index')->with('status', 'Propiedad actualizada correctamente.');
    }
}
