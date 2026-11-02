<?php

namespace App\Modules\AgendaVisitasPagos\Compartido;

use App\Models\Cliente;
use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\ActivarCuenta;
use App\Services\BitacoraService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Cuenta de acceso del cliente. Después de su primer pago (en efectivo, o con tarjeta cuando se confirma, o el pago inicial que viene
 * de una reserva) el cliente pasa a ser cliente formal y se le crea una cuenta con rol Cliente para que vea sus contratos y pagos.
 *
 *  - Necesita correo: sin correo no se crea la cuenta, pero el pago no falla (se avisa).
 *  - Contraseña aleatoria que no se muestra ni se guarda en ningún lado; el cliente la establece con un enlace por correo
 *    (el mismo sistema del CU3, con Brevo en producción).
 *  - Si el correo ya es de otro usuario, no se crea ni se enlaza nada sin avisar.
 *  - Es idempotente: si el cliente ya tiene cuenta, no hace nada (varios pagos no duplican la cuenta).
 */
class CuentaDelCliente
{
    public const CREADA = 'creada';
    public const EXISTENTE = 'existente';
    public const SIN_CORREO = 'sin_correo';
    public const CORREO_EN_USO = 'correo_en_uso';

    /** @return array{estado: string, correo_enviado?: bool} */
    public function asegurar(Cliente $cliente): array
    {
        $usuario = null;
        $estado = self::EXISTENTE;

        DB::transaction(function () use ($cliente, &$usuario, &$estado) {
            $actual = Cliente::lockForUpdate()->findOrFail($cliente->idcliente);

            if ($actual->idusuariocuenta) {
                return;
            }

            $correo = mb_strtolower(trim((string) $actual->email));
            if ($correo === '') {
                $estado = self::SIN_CORREO;

                return;
            }
            if (Usuario::whereRaw('lower(email) = ?', [$correo])->exists()) {
                $estado = self::CORREO_EN_USO;

                return;
            }

            $usuario = Usuario::create([
                'nombre' => $actual->nombre,
                'email' => $correo,
                'telefono' => $actual->telefono,
                'password' => Str::random(40), // aleatoria: nadie la conoce; el cliente pone la suya con el enlace del correo
                'estado' => Usuario::ACTIVO,
                'idrol' => Rol::where('nombre', Rol::CLIENTE)->value('idrol'),
            ]);
            $actual->update(['idusuariocuenta' => $usuario->idusuario]);
            $estado = self::CREADA;
        });

        if ($estado === self::CORREO_EN_USO) {
            BitacoraService::registrar('CUENTA_CLIENTE_NO_CREADA', 'Clientes', "No se creó la cuenta de {$cliente->nombre}: su correo ya pertenece a otro usuario", [
                'idcliente' => $cliente->idcliente, 'email' => $cliente->email,
            ]);

            return ['estado' => $estado];
        }
        if ($estado !== self::CREADA) {
            return ['estado' => $estado];
        }

        BitacoraService::registrar('CUENTA_CLIENTE_CREADA', 'Clientes', "Se creó la cuenta de acceso de {$cliente->nombre} ({$usuario->email})", [
            'idcliente' => $cliente->idcliente, 'idusuario' => $usuario->idusuario,
        ]);

        // El pago ya está hecho: si el correo falla, la cuenta queda creada y se avisa (el cliente puede pedir su enlace con el CU3).
        try {
            $usuario->notify(new ActivarCuenta(Password::broker()->createToken($usuario)));
            $enviado = true;
        } catch (\Throwable $error) {
            report($error);
            $enviado = false;
        }

        return ['estado' => $estado, 'correo_enviado' => $enviado];
    }

    /**
     * Los mensajes para el personal que registró el pago.
     *
     * @return array{status_cuenta?: string, aviso?: string}
     */
    public static function mensajes(array $resultado, Cliente $cliente): array
    {
        return match ($resultado['estado']) {
            self::CREADA => ($resultado['correo_enviado'] ?? false)
                ? ['status_cuenta' => "Se creó la cuenta de acceso de {$cliente->nombre} y se le envió un correo para activarla."]
                : ['aviso' => "La cuenta de acceso de {$cliente->nombre} se creó, pero no se pudo enviar el correo. El cliente puede usar «¿Olvidaste tu contraseña?» con su correo ({$cliente->email}) para establecer su contraseña."],
            self::SIN_CORREO => ['aviso' => 'Registre el correo del cliente para crear su cuenta.'],
            self::CORREO_EN_USO => ['aviso' => "No se creó la cuenta de {$cliente->nombre}: el correo {$cliente->email} ya pertenece a otro usuario del sistema. Revise el correo del cliente."],
            default => [],
        };
    }
}
