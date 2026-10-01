<?php

namespace App\Providers;

use App\Models\Usuario;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // El login (CU1) usa nuestra tabla USUARIO en lugar de la tabla "users" de Laravel.
        config(['auth.providers.users.model' => Usuario::class]);

        $this->cargarModulos();
    }

    /**
     * Carga automáticamente cada paquete que exista en app/Modules:
     *  - sus rutas:  app/Modules/<Paquete>/routes.php
     *  - sus vistas: view('<paquete>::<CU>.<vista>')  → app/Modules/<Paquete>/<CU>/<vista>.blade.php
     * Ejemplo: view('autenticacion::CU1_IniciarSesion.login')
     */
    private function cargarModulos(): void
    {
        foreach (glob(app_path('Modules/*'), GLOB_ONLYDIR) as $carpeta) {
            View::addNamespace(strtolower(basename($carpeta)), $carpeta);

            if (file_exists($carpeta . '/routes.php')) {
                Route::middleware('web')->group($carpeta . '/routes.php');
            }
        }
    }
}
