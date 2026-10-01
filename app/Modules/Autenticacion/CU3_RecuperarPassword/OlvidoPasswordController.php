<?php

namespace App\Modules\Autenticacion\CU3_RecuperarPassword;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * CU3: Recuperar contraseña (parte 1: solicitar el enlace)
 * Flujo principal:
 *  1. El usuario selecciona "¿Olvidaste tu contraseña?"  -> login.blade.php
 *  2-3. El sistema pide el correo y el usuario lo ingresa -> mostrar()
 *  4. El sistema valida que la cuenta exista              -> Password::sendResetLink()
 *  5. El sistema envía un enlace de recuperación          -> enviar()
 *
 * En local el correo NO se envía de verdad: queda en storage/logs/laravel.log
 * (buscar "ENLACE DE RECUPERACION").
 */
class OlvidoPasswordController extends Controller
{
    public function mostrar(): View
    {
        return view('autenticacion::CU3_RecuperarPassword.olvido');
    }

    public function enviar(OlvidoPasswordRequest $request): RedirectResponse
    {
        // Deja el enlace limpio en el log para copiarlo fácil durante el desarrollo.
        ResetPassword::createUrlUsing(function ($usuario, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $usuario->email]);

            if (app()->environment('local')) {
                Log::info('ENLACE DE RECUPERACION: ' . $url);
            }

            return $url;
        });

        $estado = Password::sendResetLink($request->only('email'));

        if ($estado === Password::RESET_LINK_SENT) {
            return back()->with('status', 'Te enviamos un enlace de recuperación a tu correo.');
        }

        if ($estado === Password::RESET_THROTTLED) {
            return back()->withInput()->withErrors(['email' => 'Espera unos minutos antes de volver a solicitarlo.']);
        }

        return back()->withInput()->withErrors(['email' => 'No existe una cuenta registrada con ese correo.']);
    }
}
