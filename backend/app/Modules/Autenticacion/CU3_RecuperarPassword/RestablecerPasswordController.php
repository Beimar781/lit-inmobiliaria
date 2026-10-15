<?php

namespace App\Modules\Autenticacion\CU3_RecuperarPassword;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * CU3: Recuperar contraseña (parte 2: definir la nueva contraseña)
 *  6. El usuario accede al enlace recibido            -> mostrar()
 *  7-8. El sistema pide la nueva contraseña y el usuario la ingresa y confirma
 *  9. El sistema actualiza la contraseña              -> actualizar()
 * Excepciones: enlace expirado, contraseñas que no coinciden, contraseña débil.
 */
class RestablecerPasswordController extends Controller
{
    public function mostrar(Request $request, string $token): View
    {
        return view('autenticacion::CU3_RecuperarPassword.restablecer', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function actualizar(RestablecerPasswordRequest $request): RedirectResponse
    {
        $estado = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Usuario $usuario, string $password) {
                // El modelo guarda la contraseña con hash automáticamente.
                $usuario->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($usuario));
            }
        );

        if ($estado === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Tu contraseña fue actualizada. Ya puedes iniciar sesión.');
        }

        return back()->withInput($request->only('email'))
            ->withErrors(['email' => 'El enlace de recuperación es inválido o ha expirado. Solicita uno nuevo.']);
    }
}
