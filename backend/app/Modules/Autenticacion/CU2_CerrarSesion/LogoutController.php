<?php

namespace App\Modules\Autenticacion\CU2_CerrarSesion;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * CU2: Cerrar sesión
 * Flujo principal:
 *  1. El usuario selecciona "Cerrar sesión"        -> botón del menú (layouts/app.blade.php)
 *  2-3. El sistema pide confirmación y el usuario confirma -> confirm() del botón
 *  4. El sistema finaliza la sesión activa         -> __invoke()
 *  5. Redirige a la pantalla de inicio de sesión
 */
class LogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Sesión cerrada correctamente.');
    }
}
