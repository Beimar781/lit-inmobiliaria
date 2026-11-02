<?php

namespace App\Notifications;

use App\Models\Usuario;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Correo que recibe un cliente cuando se le crea su cuenta de acceso (después de su primer pago).
 * Lleva un enlace para que ELLOS establezcan su contraseña: se arma con el mismo sistema de restablecimiento de contraseña del CU3
 * (mismo enlace, mismo vencimiento y mismo correo en producción: Brevo). La contraseña inicial es aleatoria y nadie la conoce.
 */
class ActivarCuenta extends Notification
{
    public function __construct(public string $token)
    {
    }

    public function via(object $notificable): array
    {
        return ['mail'];
    }

    public function toMail(Usuario $usuario): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $usuario->email]);

        // En local el correo no se envía de verdad (MAIL_MAILER=log): el enlace queda en el log para copiarlo.
        if (app()->environment('local')) {
            Log::info('ENLACE DE ACTIVACION: ' . $url);
        }

        $minutos = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');

        return (new MailMessage)
            ->subject('Activa tu cuenta - LIT Inmobiliaria')
            ->greeting('Hola, ' . $usuario->nombre)
            ->line('Registramos tu primer pago en LIT Inmobiliaria. Ahora puedes ver tus contratos, tus pagos y tus comprobantes en línea.')
            ->line('Para activar tu cuenta, establece tu contraseña con este botón:')
            ->action('Establecer mi contraseña', $url)
            ->line("Este enlace vence en {$minutos} minutos. Si vence, usa «¿Olvidaste tu contraseña?» en la pantalla de inicio de sesión con este mismo correo.")
            ->line('Si no esperabas este mensaje, ignóralo.')
            ->salutation('LIT Inmobiliaria');
    }
}
