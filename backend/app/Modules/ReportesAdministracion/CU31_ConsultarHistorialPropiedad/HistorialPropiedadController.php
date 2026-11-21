<?php

namespace App\Modules\ReportesAdministracion\CU31_ConsultarHistorialPropiedad;

use App\Http\Controllers\Controller;
use App\Models\Historial;
use App\Models\Propiedad;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * CU31: Consultar historial de una propiedad (Administrador y Agente). Es una consulta: no modifica datos.
 * El Administrador ve el historial de todas las propiedades (también las dadas de baja);
 * el Agente, solo el de las que él registró.
 *  1. Muestra los datos de la propiedad y sus cambios, del más reciente al más antiguo
 *  2. Filtra por tipo de cambio y por rango de fechas (ambos días incluidos)
 *  3. Si "desde" es posterior a "hasta" no consulta y avisa
 */
class HistorialPropiedadController extends Controller
{
    private const POR_PAGINA = 15;

    public function mostrar(HistorialPropiedadRequest $request, Propiedad $propiedad): View
    {
        $this->autorizar($propiedad);

        $rangoValido = $request->rangoValido();

        if ($rangoValido) {
            $cambios = Historial::with('usuario')
                ->where('idpropiedad', $propiedad->idpropiedad)
                ->when($request->tipo(), fn ($c, $tipo) => $c->where('tipo', $tipo))
                ->when($request->desde(), fn ($c, $desde) => $c->where('fecha', '>=', $desde->copy()->startOfDay()))
                ->when($request->hasta(), fn ($c, $hasta) => $c->where('fecha', '<=', $hasta->copy()->endOfDay()))
                ->orderByDesc('fecha')
                ->orderByDesc('idhistorial')
                ->paginate(self::POR_PAGINA)
                ->withQueryString();
        } else {
            $cambios = new LengthAwarePaginator([], 0, self::POR_PAGINA);
        }

        return view('reportesadministracion::CU31_ConsultarHistorialPropiedad.historial', [
            'propiedad' => $propiedad,
            'cambios' => $cambios,
            'tipos' => HistorialPropiedadRequest::TIPOS,
            'rangoValido' => $rangoValido,
            'tipo' => $request->tipo(),
            'desde' => $request->desde()?->format('Y-m-d'),
            'hasta' => $request->hasta()?->format('Y-m-d'),
        ]);
    }

    /** Un Agente que no registró la propiedad -> 403. El Administrador puede ver también las dadas de baja. */
    private function autorizar(Propiedad $propiedad): void
    {
        $usuario = auth()->user();

        abort_unless(
            $usuario->tieneRol('Administrador') || $propiedad->idusuario === $usuario->idusuario,
            403,
            'Solo puedes ver el historial de las propiedades que tú registraste.'
        );
    }

    /** Convierte el JSON guardado en pares campo => valor legibles; vacío si no hay datos. */
    public static function pares(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $datos = json_decode($json, true);

        if (! is_array($datos)) {
            return ['valor' => $json];
        }

        return array_map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), $datos);
    }
}