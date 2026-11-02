<?php

namespace App\Services;

use App\Models\Bitacora;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;

/**
 * Bitácora del sistema: guarda quién hizo qué, cuándo y desde qué IP.
 * Uso: BitacoraService::registrar('PROPIEDAD_BAJA', 'Propiedades', 'Dio de baja la propiedad X', ['motivo' => '...']);
 */
class BitacoraService
{
    public const MODULOS = ['Autenticación', 'Usuarios', 'Propiedades', 'CRM', 'Agenda', 'Reservas', 'Contratos', 'Pagos'];

    public const ETIQUETAS = [
        'INICIO_SESION' => 'Inicio de sesión',
        'LOGIN_FALLIDO' => 'Intento fallido de inicio de sesión',
        'LOGIN_BLOQUEADO' => 'Inicio de sesión bloqueado por intentos fallidos',
        'CIERRE_SESION' => 'Cierre de sesión',
        'RECUPERACION_SOLICITADA' => 'Solicitud de recuperación de contraseña',
        'PASSWORD_RESTABLECIDA' => 'Contraseña restablecida',
        'USUARIO_CREADO' => 'Usuario registrado',
        'USUARIO_MODIFICADO' => 'Usuario modificado',
        'USUARIO_ACTIVADO' => 'Usuario activado',
        'USUARIO_DESACTIVADO' => 'Usuario desactivado',
        'PROPIEDAD_REGISTRADA' => 'Propiedad registrada',
        'PROPIEDAD_MODIFICADA' => 'Propiedad modificada',
        'PROPIEDAD_BAJA' => 'Propiedad dada de baja',
        'PROPIEDAD_ESTADO' => 'Estado de propiedad cambiado',
        'INTERES_REGISTRADO' => 'Cliente interesado registrado',
        'PROSPECTO_SEGUIMIENTO' => 'Seguimiento de prospecto actualizado',
        'PROSPECTO_REASIGNADO' => 'Prospecto reasignado',
        'VISITA_PROGRAMADA' => 'Visita agendada',
        'VISITA_REALIZADA' => 'Visita marcada como realizada',
        'VISITA_REAGENDADA' => 'Visita reagendada',
        'VISITA_CANCELADA' => 'Visita cancelada',
        'RESERVA_REGISTRADA' => 'Reserva registrada',
        'RESERVA_CANCELADA' => 'Reserva cancelada',
        'RESERVA_VENCIDA' => 'Reserva vencida',
        'RESERVA_DEVUELTA' => 'Reserva devuelta al cliente',
        'RESERVA_CONCRETADA' => 'Reserva concretada en contrato',
        'CONTRATO_REGISTRADO' => 'Contrato registrado',
        'CONTRATO_FINALIZADO' => 'Contrato finalizado',
        'CONTRATO_RESCINDIDO' => 'Contrato rescindido',
        'PAGO_REGISTRADO' => 'Pago registrado',
        'COMPROBANTE_GENERADO' => 'Comprobante de pago generado',
        'PAGO_TARJETA_INICIADO' => 'Pago con tarjeta iniciado',
        'PAGO_TARJETA_CONFIRMADO' => 'Pago con tarjeta confirmado',
        'PAGO_TARJETA_CANCELADO' => 'Pago con tarjeta cancelado',
        'PAGO_TARJETA_FALLIDO' => 'Pago con tarjeta no iniciado',
        'PAGO_TARJETA_DISCREPANCIA' => 'Pago con tarjeta con datos que no coinciden',
    ];

    /**
     * @param int|false|null $idusuario  false = usar el usuario con sesión iniciada; null = sin usuario
     */
    public static function registrar(
        string $accion,
        string $modulo,
        string $descripcion,
        ?array $detalle = null,
        int|false|null $idusuario = false
    ): void {
        try {
            Bitacora::create([
                'idusuario' => $idusuario === false ? auth()->id() : $idusuario,
                'accion' => $accion,
                'modulo' => $modulo,
                'descripcion' => $descripcion,
                'detalle' => $detalle ?: null, // el cast 'array' del modelo lo convierte a JSON
                'ip' => request()->ip(),
                'fecha' => now(),
            ]);
        } catch (\Throwable $error) {
            // Un fallo de la bitácora nunca debe romper la operación principal.
            report($error);
        }
    }

    /** Eventos de autenticación (CU1, CU2, CU3): se registran solos, sin tocar los controladores. */
    public static function escuchar(): void
    {
        Event::listen(Login::class, function (Login $evento) {
            self::registrar('INICIO_SESION', 'Autenticación', 'Inició sesión: ' . $evento->user->email, null, $evento->user->getAuthIdentifier());
        });

        Event::listen(Failed::class, function (Failed $evento) {
            $correo = $evento->credentials['email'] ?? '(vacío)';
            self::registrar('LOGIN_FALLIDO', 'Autenticación', 'Intento fallido de inicio de sesión con el correo ' . $correo, null, null);
        });

        Event::listen(Logout::class, function (Logout $evento) {
            if ($evento->user) {
                self::registrar('CIERRE_SESION', 'Autenticación', 'Cerró sesión: ' . $evento->user->email, null, $evento->user->getAuthIdentifier());
            }
        });

        Event::listen(PasswordReset::class, function (PasswordReset $evento) {
            self::registrar('PASSWORD_RESTABLECIDA', 'Autenticación', 'Restableció su contraseña: ' . $evento->user->email, null, $evento->user->getAuthIdentifier());
        });
    }
}
