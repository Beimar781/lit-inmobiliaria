<?php

use App\Http\Middleware\RolMiddleware;
use App\Models\Rol;
use App\Modules\Reportes\Bitacora\BitacoraController;
use Illuminate\Support\Facades\Route;

// Paquete P6 - Reportes y Administración del sistema
// Bitácora del sistema (solo Administrador). No es un caso de uso: es una función de control del administrador.
Route::middleware(['auth', RolMiddleware::class . ':' . Rol::ADMINISTRADOR])->group(function () {
    Route::get('/bitacora', [BitacoraController::class, 'index'])->name('bitacora.index');
});
