<?php

namespace App\Providers;

use App\Models\Usuario;
use App\Services\BitacoraService;
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
        // Mensajes del sistema (validaciones, etc.) en español.
        $this->app->setLocale('es');

        // Hora de Bolivia (las fechas de la bitácora y del historial salen en hora local).
        config(['app.timezone' => 'America/La_Paz']);
        date_default_timezone_set('America/La_Paz');

        // El login (CU1) usa nuestra tabla USUARIO en lugar de la tabla "users" de Laravel.
        config(['auth.providers.users.model' => Usuario::class]);

        // La bitácora escucha inicios/cierres de sesión y cambios de contraseña.
        BitacoraService::escuchar();

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
