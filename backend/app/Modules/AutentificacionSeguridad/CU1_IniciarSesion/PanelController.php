<?php

namespace App\Modules\AutentificacionSeguridad\CU1_IniciarSesion;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Pantalla de inicio después de iniciar sesión (paso 5 del CU1).
 * Muestra los paquetes del sistema y, dentro de cada uno, los casos de uso que el rol puede usar (config/paquetes.php).
 */
class PanelController extends Controller
{
    public function __invoke(): View
    {
        $rol = auth()->user()->rol?->nombre;
        $esAdministrador = $rol === Rol::ADMINISTRADOR;

        $paquetes = collect(config('paquetes'))
            ->map(fn (array $paquete) => [
                'codigo' => $paquete['codigo'],
                'nombre' => $paquete['nombre'],
                'casos' => $this->visibles($paquete['casos'] ?? [], $rol),
                'administracion' => $this->visibles($paquete['administracion'] ?? [], $rol),
            ])
            // El administrador ve los 6 paquetes; los demás roles, solo los que tienen algo para ellos.
            ->filter(fn (array $paquete) => $esAdministrador || $paquete['casos'] || $paquete['administracion'])
            ->values();

        return view('autentificacionseguridad::CU1_IniciarSesion.panel', [
            'paquetes' => $paquetes,
            'sinOpciones' => $paquetes->every(fn (array $paquete) => ! $paquete['casos'] && ! $paquete['administracion']),
        ]);
    }

    /** Los elementos que el rol puede usar y cuya ruta existe (un CU sin ruta todavía no se muestra). */
    private function visibles(array $elementos, ?string $rol): array
    {
        return array_values(array_filter(
            $elementos,
            fn (array $elemento) => in_array($rol, $elemento['roles'], true) && Route::has($elemento['ruta'])
        ));
    }
}
