<?php

use App\Http\Middleware\RolMiddleware;
use App\Models\Rol;
use App\Modules\GestionPropiedades\Compartido\PropiedadController;
use App\Modules\GestionPropiedades\CU5_RegistrarPropiedad\RegistrarPropiedadController;
use App\Modules\GestionPropiedades\CU6_ModificarPropiedad\ModificarPropiedadController;
use App\Modules\GestionPropiedades\CU7_EliminarPropiedad\EliminarPropiedadController;
use App\Modules\GestionPropiedades\CU8_CambiarEstado\CambiarEstadoController;
use Illuminate\Support\Facades\Route;

// Paquete P2 - Gestión de Propiedades
Route::middleware('auth')->group(function () {

    // Administrador y Agente: inventario, registrar (CU5), modificar (CU6) y cambiar estado (CU8)
    Route::middleware(RolMiddleware::class . ':' . Rol::ADMINISTRADOR . ',' . Rol::AGENTE)->group(function () {
        Route::get('/propiedades', [PropiedadController::class, 'index'])->name('propiedades.index');

        // CU5: Registrar propiedad
        Route::get('/propiedades/crear', [RegistrarPropiedadController::class, 'crear'])->name('propiedades.create');
        Route::post('/propiedades', [RegistrarPropiedadController::class, 'guardar'])->name('propiedades.store');

        // CU6: Modificar propiedad
        Route::get('/propiedades/{propiedad}/editar', [ModificarPropiedadController::class, 'editar'])->name('propiedades.edit');
        Route::put('/propiedades/{propiedad}', [ModificarPropiedadController::class, 'actualizar'])->name('propiedades.update');

        // CU8: Cambiar estado (el Agente solo en las propiedades que él registró: se comprueba en el controlador)
        Route::get('/propiedades/{propiedad}/estado', [CambiarEstadoController::class, 'mostrar'])->name('propiedades.estado');
        Route::put('/propiedades/{propiedad}/estado', [CambiarEstadoController::class, 'actualizar'])->name('propiedades.estado.actualizar');
    });

    // Solo Administrador: CU7 Eliminar (dar de baja) propiedad
    Route::middleware(RolMiddleware::class . ':' . Rol::ADMINISTRADOR)->group(function () {
        Route::get('/propiedades/{propiedad}/baja', [EliminarPropiedadController::class, 'confirmar'])->name('propiedades.baja');
        Route::delete('/propiedades/{propiedad}', [EliminarPropiedadController::class, 'darDeBaja'])->name('propiedades.destroy');
    });
});
