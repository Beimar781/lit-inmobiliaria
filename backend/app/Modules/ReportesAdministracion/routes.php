<?php

use App\Http\Middleware\RolMiddleware;
use App\Models\Rol;
use App\Modules\ReportesAdministracion\Bitacora\BitacoraController;
use App\Modules\ReportesAdministracion\CU23_GestionarCategorias\CategoriaController;
use Illuminate\Support\Facades\Route;

// Paquete P6 - Reportes y Administración del sistema
// Bitácora del sistema (solo Administrador). No es un caso de uso: es una función de control del administrador.
Route::middleware(['auth', RolMiddleware::class . ':' . Rol::ADMINISTRADOR])->group(function () {
    Route::get('/bitacora', [BitacoraController::class, 'index'])->name('bitacora.index');

    // CU23 - Gestionar categorías de propiedad
    Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
    Route::post('/categorias', [CategoriaController::class, 'guardar'])->name('categorias.store');
    Route::get('/categorias/{categoria}/editar', [CategoriaController::class, 'editar'])->name('categorias.edit');
    Route::put('/categorias/{categoria}', [CategoriaController::class, 'actualizar'])->name('categorias.update');
    Route::delete('/categorias/{categoria}', [CategoriaController::class, 'eliminar'])->name('categorias.destroy');
});
