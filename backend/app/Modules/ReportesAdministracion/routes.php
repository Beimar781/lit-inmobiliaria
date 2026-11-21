<?php

use App\Http\Middleware\RolMiddleware;
use App\Models\Rol;
use App\Modules\ReportesAdministracion\Bitacora\BitacoraController;
use App\Modules\ReportesAdministracion\CU20_GenerarReportes\ReporteController;
use App\Modules\ReportesAdministracion\CU22_GestionarPropietarios\PropietarioController;
use App\Modules\ReportesAdministracion\CU23_GestionarCategorias\CategoriaController;
use App\Modules\ReportesAdministracion\CU31_ConsultarHistorialPropiedad\HistorialPropiedadController;
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

// CU22 - Gestionar propietarios (Administrador y Agente)
Route::middleware(['auth', RolMiddleware::class . ':' . Rol::ADMINISTRADOR . ',' . Rol::AGENTE])->group(function () {
    Route::get('/propietarios', [PropietarioController::class, 'index'])->name('propietarios.index');
    Route::post('/propietarios', [PropietarioController::class, 'guardar'])->name('propietarios.store');
    Route::get('/propietarios/{propietario}/editar', [PropietarioController::class, 'editar'])->name('propietarios.edit');
    Route::put('/propietarios/{propietario}', [PropietarioController::class, 'actualizar'])->name('propietarios.update');
});

// CU22 - Eliminar propietario: solo el Administrador
Route::middleware(['auth', RolMiddleware::class . ':' . Rol::ADMINISTRADOR])->group(function () {
    Route::delete('/propietarios/{propietario}', [PropietarioController::class, 'eliminar'])->name('propietarios.destroy');
});

// CU31 - Consultar historial de una propiedad (Administrador y Agente; el Agente solo en las suyas: se comprueba en el controlador)
Route::middleware(['auth', RolMiddleware::class . ':' . Rol::ADMINISTRADOR . ',' . Rol::AGENTE])->group(function () {
    Route::get('/propiedades/{propiedad}/historial', [HistorialPropiedadController::class, 'mostrar'])->name('propiedades.historial');
});

// CU20 - Generar reportes (Administrador, Agente y Asistente; qué tipos ve cada rol se comprueba en el constructor de reportes)
Route::middleware(['auth', RolMiddleware::class . ':' . Rol::ADMINISTRADOR . ',' . Rol::AGENTE . ',' . Rol::ASISTENTE])->group(function () {
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::post('/reportes/interpretar', [ReporteController::class, 'interpretar'])->middleware('throttle:10,1')->name('reportes.interpretar');
    Route::get('/reportes/generar', [ReporteController::class, 'generar'])->name('reportes.generar');
    Route::get('/reportes/exportar/{formato}', [ReporteController::class, 'exportar'])->name('reportes.exportar');
});
