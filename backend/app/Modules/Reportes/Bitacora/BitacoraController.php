<?php

namespace App\Modules\Reportes\Bitacora;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Usuario;
use App\Services\BitacoraService;
use App\Support\ParametrosConsulta;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Bitácora del sistema (solo Administrador). Pertenece al paquete Reportes y Administración del sistema;
 * no es un caso de uso del documento, es una función de control para el administrador.
 * El administrador ve todo lo que ocurre en el sistema: inicios y cierres de sesión,
 * intentos fallidos, cambios en usuarios y en propiedades, con filtros por usuario,
 * módulo, acción y fechas.
 */
class BitacoraController extends Controller
{
    public function index(Request $request): View
    {
        $busqueda = ParametrosConsulta::texto($request, 'q');
        $desde = $this->fecha(ParametrosConsulta::texto($request, 'desde'));
        $hasta = $this->fecha(ParametrosConsulta::texto($request, 'hasta'));
        $idusuario = ParametrosConsulta::entero($request, 'idusuario');
        $modulo = ParametrosConsulta::texto($request, 'modulo');
        $accion = ParametrosConsulta::texto($request, 'accion');

        $registros = Bitacora::with('usuario')
            ->when($idusuario !== null, fn ($c) => $c->where('idusuario', $idusuario))
            ->when($modulo !== '', fn ($c) => $c->where('modulo', $modulo))
            ->when($accion !== '', fn ($c) => $c->where('accion', $accion))
            ->when($desde, fn ($c) => $c->where('fecha', '>=', $desde->copy()->startOfDay()))
            ->when($hasta, fn ($c) => $c->where('fecha', '<=', $hasta->copy()->endOfDay()))
            ->when($busqueda !== '', function ($c) use ($busqueda) {
                $c->where(function ($donde) use ($busqueda) {
                    $donde->whereLike('descripcion', "%{$busqueda}%")
                        ->orWhereLike('ip', "%{$busqueda}%");
                });
            })
            ->orderByDesc('idbitacora')
            ->paginate(25)
            ->withQueryString();

        return view('reportes::Bitacora.index', [
            'registros' => $registros,
            'usuarios' => Usuario::orderBy('nombre')->pluck('nombre', 'idusuario'),
            'modulos' => BitacoraService::MODULOS,
            'acciones' => BitacoraService::ETIQUETAS,
            'busqueda' => $busqueda,
        ]);
    }

    /** Convierte el texto de un filtro de fecha; si no es válido lo ignora. */
    private function fecha(?string $texto): ?Carbon
    {
        if (! $texto) {
            return null;
        }

        try {
            return Carbon::parse($texto);
        } catch (\Throwable) {
            return null;
        }
    }
}
