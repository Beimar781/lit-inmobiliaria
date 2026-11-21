<?php

use App\Http\Middleware\RolMiddleware;
use App\Models\Rol;
use App\Modules\CrmGestionClientes\CU12_RegistrarClienteInteresado\RegistrarClienteController;
use App\Modules\CrmGestionClientes\CU12_RegistrarClienteInteresado\RegistrarInteresController;
use App\Modules\CrmGestionClientes\CU13_GestionarProspectos\ProspectoController;
use App\Modules\CrmGestionClientes\CU18_RegistrarSeguimiento\SeguimientoController;
use App\Modules\CrmGestionClientes\CU24_GestionarInquilinos\InquilinoController;
use App\Modules\CrmGestionClientes\CU25_RegistrarResena\ResenaController;
use Illuminate\Support\Facades\Route;

// Paquete P4 - CRM y Gestión de Clientes

// CU12 (vía 1): "Me interesa" del cliente público. Sin iniciar sesión; máximo 6 envíos por minuto por conexión.
Route::post('/catalogo/{propiedad}/me-interesa', [RegistrarInteresController::class, 'guardar'])
    ->whereNumber('propiedad')
    ->middleware('throttle:6,1')
    ->name('catalogo.interes');

Route::middleware('auth')->group(function () {

    // CU12 (vía 2): solo el Agente registra clientes interesados que lo contactaron por otro medio
    Route::middleware(RolMiddleware::class . ':' . Rol::AGENTE)->group(function () {
        Route::get('/crm/prospectos/crear', [RegistrarClienteController::class, 'crear'])->name('prospectos.create');
        Route::post('/crm/prospectos', [RegistrarClienteController::class, 'guardar'])->name('prospectos.store');
    });

    // CU13: Agente (sus prospectos) y Administrador (todos)
    Route::middleware(RolMiddleware::class . ':' . Rol::ADMINISTRADOR . ',' . Rol::AGENTE)->group(function () {
        Route::get('/crm/prospectos', [ProspectoController::class, 'index'])->name('prospectos.index');
        Route::get('/crm/prospectos/{cliente}', [ProspectoController::class, 'show'])->whereNumber('cliente')->name('prospectos.show');
        Route::patch('/crm/prospectos/{cliente}/seguimiento', [ProspectoController::class, 'actualizarSeguimiento'])->whereNumber('cliente')->name('prospectos.seguimiento');

        // CU18: historial de contactos y registro de seguimiento (no se editan ni se eliminan)
        Route::get('/crm/prospectos/{cliente}/seguimientos', [SeguimientoController::class, 'mostrar'])->whereNumber('cliente')->name('prospectos.seguimientos');
        Route::post('/crm/prospectos/{cliente}/seguimientos', [SeguimientoController::class, 'guardar'])->whereNumber('cliente')->name('prospectos.seguimientos.guardar');

        // CU24: inquilino del cliente (uno solo por cliente; registrar de nuevo lo actualiza)
        Route::get('/crm/prospectos/{cliente}/inquilino', [InquilinoController::class, 'mostrar'])->whereNumber('cliente')->name('prospectos.inquilino');
        Route::post('/crm/prospectos/{cliente}/inquilino', [InquilinoController::class, 'guardar'])->whereNumber('cliente')->name('prospectos.inquilino.guardar');
    });

    // CU25: reseña de una propiedad (solo el Cliente con contrato vigente o finalizado sobre ella)
    Route::middleware(RolMiddleware::class . ':' . Rol::CLIENTE)->group(function () {
        Route::get('/mis-contratos/propiedad/{propiedad}/resena', [ResenaController::class, 'crear'])->whereNumber('propiedad')->name('resenas.crear');
        Route::post('/mis-contratos/propiedad/{propiedad}/resena', [ResenaController::class, 'guardar'])->whereNumber('propiedad')->name('resenas.guardar');
    });
    // CU13: solo el Administrador reasigna prospectos a otro agente
    Route::middleware(RolMiddleware::class . ':' . Rol::ADMINISTRADOR)->group(function () {
        Route::patch('/crm/prospectos/{cliente}/agente', [ProspectoController::class, 'reasignar'])->whereNumber('cliente')->name('prospectos.asignar');
    });
});
