<?php

use App\Modules\Busqueda\CU11_VerDetallePropiedad\VerDetallePropiedadController;
use App\Modules\Busqueda\CU9_BuscarPropiedades\BuscarPropiedadesController;
use Illuminate\Support\Facades\Route;

// Paquete P3 - Búsqueda y Consulta de Inmuebles
// El catálogo es PÚBLICO: estas rutas no piden iniciar sesión.

// CU9: Buscar propiedades (con los filtros del CU10 sobre la misma pantalla)
Route::get('/catalogo', [BuscarPropiedadesController::class, 'index'])->name('catalogo.index');

// CU11: Ver detalle de propiedad (el "Me interesa" de la ficha es el CU12, paquete CRM)
Route::get('/catalogo/{propiedad}', [VerDetallePropiedadController::class, 'mostrar'])
    ->whereNumber('propiedad')
    ->name('catalogo.detalle');
