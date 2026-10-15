<?php

namespace App\Providers;

use App\Http\Middleware\VerificarCuentaActiva;
use App\Models\Usuario;
use App\Services\BitacoraService;
use App\Support\BrevoTransport;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
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

        // Correo real en producción (MAIL_MAILER=brevo): envío por la API web de Brevo.
        Mail::extend('brevo', fn (array $config) => new BrevoTransport(config('services.brevo.key')));

        $this->correoDeRecuperacion();

        $this->cargarModulos();
    }

    /** CU3: texto en español del correo con el enlace para recuperar la contraseña. */
    private function correoDeRecuperacion(): void
    {
        ResetPassword::toMailUsing(function ($usuario, string $token) {
            // Si el CU3 definió cómo armar el enlace (por ejemplo, para dejarlo en el log en local), se respeta.
            $url = ResetPassword::$createUrlCallback
                ? call_user_func(ResetPassword::$createUrlCallback, $usuario, $token)
                : route('password.reset', ['token' => $token, 'email' => $usuario->getEmailForPasswordReset()]);

            $minutos = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');

            return (new MailMessage)
                ->subject('Recupera tu contraseña - LIT Inmobiliaria')
                ->greeting('Hola, ' . $usuario->nombre)
                ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
                ->action('Restablecer contraseña', $url)
                ->line("Este enlace vence en {$minutos} minutos.")
                ->line('Si no solicitaste el cambio, ignora este correo: tu contraseña seguirá igual.')
                ->salutation('LIT Inmobiliaria');
        });
    }

    /**
     * Carga automáticamente cada paquete que exista en backend/app/Modules:
     *  - sus rutas:  backend/app/Modules/<Paquete>/routes.php
     *  - sus vistas: view('<paquete>::<CU>.<vista>')  → frontend/<Paquete>/<CU>/<vista>.blade.php
     * Ejemplo: view('autenticacion::CU1_IniciarSesion.login')
     */
    private function cargarModulos(): void
    {
        // El frontend vive en la carpeta hermana de backend (ver también config/view.php).
        $frontend = dirname(base_path()) . DIRECTORY_SEPARATOR . 'frontend';

        foreach (glob(app_path('Modules/*'), GLOB_ONLYDIR) as $carpeta) {
            View::addNamespace(strtolower(basename($carpeta)), $frontend . DIRECTORY_SEPARATOR . basename($carpeta));

            if (file_exists($carpeta . '/routes.php')) {
                Route::middleware(['web', VerificarCuentaActiva::class])->group($carpeta . '/routes.php');
            }
        }
    }
}
