<?php

namespace App\Modules\Propiedades\CU5_RegistrarPropiedad;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Historial;
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
 * CU5: Registrar propiedad (Administrador o Agente)
 * Flujo principal:
 *  1. El usuario selecciona "Registrar propiedad"               -> crear()
 *  2. El sistema muestra el formulario                           -> crear.blade.php
 *  3. Ingresa datos generales (título, precio, superficie...)    -> campos.blade.php (carpeta Compartido)
 *  4. Selecciona o registra el propietario, la categoría y la ubicación (zona/coordenadas dentro del 4.º anillo)
 *  5. Carga las imágenes
 *  6. Confirma el registro
 *  7. El sistema valida, guarda y deja la propiedad "Disponible" -> guardar() (validación en RegistrarPropiedadRequest)
 */
class RegistrarPropiedadController extends Controller
{
    public function crear(): View
    {
        return view('propiedades::CU5_RegistrarPropiedad.crear', [
            'propiedad' => null,
            'categorias' => Categoria::orderBy('nombre')->pluck('nombre', 'idcategoria'),
            'propietarios' => Propietario::orderBy('nombre')->pluck('nombre', 'idpropietario'),
        ]);
    }

    public function guardar(RegistrarPropiedadRequest $request): RedirectResponse
    {
        $propiedad = DB::transaction(function () use ($request) {
            // Propietario existente o nuevo
            $idpropietario = $request->input('idpropietario') ?: Propietario::create([
                'nombre' => $request->input('propietario_nombre'),
                'telefono' => $request->input('propietario_telefono'),
                'email' => $request->input('propietario_email'),
                'direccion' => $request->input('propietario_direccion'),
            ])->idpropietario;

            $ubicacion = Ubicacion::create([
                'ciudad' => config('santacruz.ciudad'),
                'zona' => $request->input('zona'),
                'direccion' => $request->input('direccion'),
                'latitud' => $request->input('latitud'),
                'longitud' => $request->input('longitud'),
            ]);

            $propiedad = Propiedad::create([
                'idpropietario' => $idpropietario,
                'idcategoria' => $request->input('idcategoria'),
                'idubicacion' => $ubicacion->idubicacion,
                'idusuario' => $request->user()->idusuario, // agente que la registra
                'titulo' => $request->input('titulo'),
                'descripcion' => $request->input('descripcion'),
                'precio' => $request->input('precio'),
                'tipopropiedad' => $request->input('tipopropiedad'),
                'estadopropiedad' => Propiedad::DISPONIBLE,
                'superficie' => $request->input('superficie'),
                'areaconstruida' => $request->input('areaconstruida'),
                'habitaciones' => $request->input('habitaciones'),
                'banos' => $request->input('banos'),
                'antiguedad' => $request->input('antiguedad'),
            ]);

            $nuevas = GestorImagenes::guardar($propiedad, $request->file('imagenes') ?? []);
            GestorImagenes::definirPortada($propiedad, $request->input('portada'), $nuevas);

            HistorialService::registrar(
                Historial::REGISTRO,
                $propiedad->idpropiedad,
                null,
                HistorialService::instantanea($propiedad->fresh('ubicacion')),
                'Registro inicial de la propiedad'
            );

            return $propiedad;
        });

        BitacoraService::registrar('PROPIEDAD_REGISTRADA', 'Propiedades', "Registró la propiedad «{$propiedad->titulo}» (ID {$propiedad->idpropiedad})", [
            'idpropiedad' => $propiedad->idpropiedad,
            'tipo' => $propiedad->tipopropiedad,
            'precio' => (string) $propiedad->precio,
            'zona' => $request->input('zona'),
        ]);

        return redirect()->route('propiedades.index')->with('status', 'Propiedad registrada correctamente.');
    }
}
