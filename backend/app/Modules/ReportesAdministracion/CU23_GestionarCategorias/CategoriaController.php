<?php

namespace App\Modules\ReportesAdministracion\CU23_GestionarCategorias;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * CU23: Gestionar categorías de propiedad (solo Administrador)
 *  1. Lista las categorías con su cantidad de propiedades  -> index()
 *  2. Registra una categoría nueva                         -> guardar()
 *  3. Edita nombre o descripción                           -> editar() / actualizar()
 *  4. Elimina solo si no tiene propiedades asociadas       -> eliminar()
 */
class CategoriaController extends Controller
{
    public function index(): View
    {
        return $this->pantalla(null);
    }

    public function editar(Categoria $categoria): View
    {
        return $this->pantalla($categoria);
    }

    public function guardar(CategoriaRequest $request): RedirectResponse
    {
        $categoria = Categoria::create($request->validated());

        BitacoraService::registrar('CATEGORIA_CREADA', 'Propiedades', "Registró la categoría {$categoria->nombre}", [
            'idcategoria' => $categoria->idcategoria,
        ]);

        return redirect()->route('categorias.index')->with('status', 'Categoría registrada correctamente.');
    }

    public function actualizar(CategoriaRequest $request, Categoria $categoria): RedirectResponse
    {
        $anterior = $categoria->nombre;
        $categoria->update($request->validated());

        BitacoraService::registrar('CATEGORIA_MODIFICADA', 'Propiedades', "Modificó la categoría {$anterior}", [
            'idcategoria' => $categoria->idcategoria,
            'nombre' => $categoria->nombre,
        ]);

        return redirect()->route('categorias.index')->with('status', 'Categoría actualizada correctamente.');
    }

    public function eliminar(Categoria $categoria): RedirectResponse
    {
        // Cuenta todas las propiedades, también las dadas de baja.
        if ($categoria->propiedades()->exists()) {
            return redirect()->route('categorias.index')
                ->with('error', 'No se puede eliminar: la categoría está en uso por propiedades.');
        }

        $categoria->delete();

        BitacoraService::registrar('CATEGORIA_ELIMINADA', 'Propiedades', "Eliminó la categoría {$categoria->nombre}", [
            'idcategoria' => $categoria->idcategoria,
        ]);

        return redirect()->route('categorias.index')->with('status', 'Categoría eliminada correctamente.');
    }

    private function pantalla(?Categoria $categoria): View
    {
        return view('reportesadministracion::CU23_GestionarCategorias.index', [
            'categorias' => Categoria::withCount('propiedades')->orderBy('nombre')->get(),
            'categoria' => $categoria,
        ]);
    }
}