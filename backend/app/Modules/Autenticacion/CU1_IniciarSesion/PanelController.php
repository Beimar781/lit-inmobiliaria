<?php

namespace App\Modules\Autenticacion\CU1_IniciarSesion;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Pantalla de inicio después de iniciar sesión (paso 5 del CU1). */
class PanelController extends Controller
{
    public function __invoke(): View
    {
        return view('autenticacion::CU1_IniciarSesion.panel');
    }
}
