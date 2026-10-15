<?php

use App\Http\Middleware\RolMiddleware;
use App\Models\Rol;
use App\Modules\Usuarios\CU4_GestionarUsuarios\UsuarioController;
use Illuminate\Support\Facades\Route;

// Paquete P1 - Autenticación y Seguridad (gestión de cuentas)
// CU4: Gestionar usuarios (solo Administrador)
Route::middleware(['auth', RolMiddleware::class . ':' . Rol::ADMINISTRADOR])->group(function () {
    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/crear', [UsuarioController::class, 'crear'])->name('usuarios.create');
    Route::post('/usuarios', [UsuarioController::class, 'guardar'])->name('usuarios.store');
    Route::get('/usuarios/{usuario}/editar', [UsuarioController::class, 'editar'])->name('usuarios.edit');
    Route::put('/usuarios/{usuario}', [UsuarioController::class, 'actualizar'])->name('usuarios.update');
    Route::patch('/usuarios/{usuario}/estado', [UsuarioController::class, 'cambiarEstado'])->name('usuarios.estado');
});
