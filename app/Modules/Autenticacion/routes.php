<?php

use App\Modules\Autenticacion\CU1_IniciarSesion\LoginController;
use App\Modules\Autenticacion\CU1_IniciarSesion\PanelController;
use App\Modules\Autenticacion\CU2_CerrarSesion\LogoutController;
use App\Modules\Autenticacion\CU3_RecuperarPassword\OlvidoPasswordController;
use App\Modules\Autenticacion\CU3_RecuperarPassword\RestablecerPasswordController;
use Illuminate\Support\Facades\Route;

// Paquete P1 - Autenticación y Seguridad

// Pantallas para quien NO ha iniciado sesión
Route::middleware('guest')->group(function () {
    // CU1: Iniciar sesión
    Route::get('/login', [LoginController::class, 'mostrar'])->name('login');
    Route::post('/login', [LoginController::class, 'ingresar'])->middleware('throttle:10,1')->name('login.ingresar');

    // CU3: Recuperar contraseña
    Route::get('/olvide-password', [OlvidoPasswordController::class, 'mostrar'])->name('password.request');
    Route::post('/olvide-password', [OlvidoPasswordController::class, 'enviar'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/restablecer-password/{token}', [RestablecerPasswordController::class, 'mostrar'])->name('password.reset');
    Route::post('/restablecer-password', [RestablecerPasswordController::class, 'actualizar'])->name('password.update');
});

// Pantallas para quien YA inició sesión
Route::middleware('auth')->group(function () {
    Route::get('/panel', PanelController::class)->name('panel');   // destino después del CU1
    Route::post('/logout', LogoutController::class)->name('logout'); // CU2: Cerrar sesión
});
