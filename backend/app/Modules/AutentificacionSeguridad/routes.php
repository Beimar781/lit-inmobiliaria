<?php

use App\Http\Middleware\RolMiddleware;
use App\Models\Rol;
use App\Modules\AutentificacionSeguridad\CU1_IniciarSesion\LoginController;
use App\Modules\AutentificacionSeguridad\CU1_IniciarSesion\PanelController;
use App\Modules\AutentificacionSeguridad\CU2_CerrarSesion\LogoutController;
use App\Modules\AutentificacionSeguridad\CU3_RecuperarContrasena\OlvidoPasswordController;
use App\Modules\AutentificacionSeguridad\CU3_RecuperarContrasena\RestablecerPasswordController;
use App\Modules\AutentificacionSeguridad\CU4_GestionarUsuarios\UsuarioController;
use Illuminate\Support\Facades\Route;

// Paquete P1 - Autentificación y Seguridad (CU1, CU2, CU3 y CU4)

// Pantallas para quien NO ha iniciado sesión
Route::middleware('guest')->group(function () {
    // CU1: Iniciar sesión
    Route::get('/login', [LoginController::class, 'mostrar'])->name('login');
    Route::post('/login', [LoginController::class, 'ingresar'])->name('login.ingresar');

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

// CU4: Gestionar usuarios (solo Administrador)
Route::middleware(['auth', RolMiddleware::class . ':' . Rol::ADMINISTRADOR])->group(function () {
    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/crear', [UsuarioController::class, 'crear'])->name('usuarios.create');
    Route::post('/usuarios', [UsuarioController::class, 'guardar'])->name('usuarios.store');
    Route::get('/usuarios/{usuario}/editar', [UsuarioController::class, 'editar'])->name('usuarios.edit');
    Route::put('/usuarios/{usuario}', [UsuarioController::class, 'actualizar'])->name('usuarios.update');
    Route::patch('/usuarios/{usuario}/estado', [UsuarioController::class, 'cambiarEstado'])->name('usuarios.estado');
});
