<?php

namespace App\Modules\AutentificacionSeguridad\CU1_IniciarSesion;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Pantalla de inicio después de iniciar sesión (paso 5 del CU1).
 * Muestra una tarjeta por módulo, solo las que el rol puede usar (config/paquetes.php).
 */
class PanelController extends Controller
{
    public function __invoke(): View
    {
        $rol = auth()->user()->rol?->nombre;

        $tarjetas = array_values(array_filter(
            config('paquetes.tarjetas'),
            fn (array $tarjeta) => in_array($rol, $tarjeta['roles'], true) && Route::has($tarjeta['ruta'])
        ));

        return view('autentificacionseguridad::CU1_IniciarSesion.panel', [
            'tarjetas' => $tarjetas,
        ]);
    }
}
