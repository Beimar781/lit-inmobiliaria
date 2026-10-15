<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si el administrador desactiva una cuenta (CU4) mientras esa persona tiene la sesión abierta,
 * en su próxima acción se le cierra la sesión. Se aplica a todas las rutas de los módulos.
 */
class VerificarCuentaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->estaActivo()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Tu cuenta fue desactivada. Comunícate con el administrador.']);
        }

        return $next($request);
    }
}
