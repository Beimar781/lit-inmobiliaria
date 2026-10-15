<?php

use Illuminate\Support\Facades\Route;

// Las rutas de cada caso de uso están en app/Modules/<Paquete>/routes.php
Route::get('/', function () {
    return auth()->check() ? redirect()->route('panel') : redirect()->route('login');
});
