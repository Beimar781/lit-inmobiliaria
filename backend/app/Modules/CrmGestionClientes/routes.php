<?php

use App\Http\Middleware\RolMiddleware;
use App\Models\Rol;
use App\Modules\CrmGestionClientes\CU12_RegistrarClienteInteresado\RegistrarClienteController;
use App\Modules\CrmGestionClientes\CU12_RegistrarClienteInteresado\RegistrarInteresController;
use App\Modules\CrmGestionClientes\CU13_GestionarProspectos\ProspectoController;
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
    });

    // CU13: solo el Administrador reasigna prospectos a otro agente
    Route::middleware(RolMiddleware::class . ':' . Rol::ADMINISTRADOR)->group(function () {
        Route::patch('/crm/prospectos/{cliente}/agente', [ProspectoController::class, 'reasignar'])->whereNumber('cliente')->name('prospectos.asignar');
    });
});
