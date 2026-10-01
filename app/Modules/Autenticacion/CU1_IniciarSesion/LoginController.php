<?php

namespace App\Modules\Autenticacion\CU1_IniciarSesion;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * CU1: Iniciar sesión
 * Flujo principal:
 *  1. El usuario accede a "Iniciar sesión"            -> mostrar()
 *  2. Ingresa correo y contraseña                     -> formulario login.blade.php
 *  3. El sistema valida las credenciales              -> ingresar()
 *  4. El sistema identifica el rol del usuario        -> Usuario::rol()
 *  5. Concede el acceso al panel según su rol         -> redirige a /panel
 * Excepciones: credenciales incorrectas, usuario no registrado, cuenta inactiva.
 */
class LoginController extends Controller
{
    public function mostrar(): View
    {
        return view('autenticacion::CU1_IniciarSesion.login');
    }

    public function ingresar(LoginRequest $request): RedirectResponse
    {
        // Solo pueden entrar usuarios con estado ACTIVO.
        $credenciales = [
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'estado' => Usuario::ACTIVO,
        ];

        if (! Auth::attempt($credenciales, $request->boolean('recordar'))) {
            throw ValidationException::withMessages([
                'email' => $this->mensajeDeError($request),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('panel'));
    }

    /** Distingue "cuenta inactiva" de "credenciales incorrectas" (sin revelar si el correo existe). */
    private function mensajeDeError(Request $request): string
    {
        $usuario = Usuario::where('email', $request->input('email'))->first();

        if ($usuario
            && $usuario->estado !== Usuario::ACTIVO
            && Hash::check($request->input('password'), $usuario->password)) {
            return 'Tu cuenta está inactiva o bloqueada. Comunícate con el administrador.';
        }

        return 'El correo o la contraseña son incorrectos.';
    }
}
