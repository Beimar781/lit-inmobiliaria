<?php

namespace App\Modules\ReportesAdministracion\CU20_GenerarReportes;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Models\Ubicacion;
use App\Models\Usuario;
use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * CU20: Generar reportes (Administrador: todos; Agente: propiedades, clientes y visitas suyos; Asistente: contratos y pagos)
 * El reporte es de solo lectura. Se piden tipo, fechas, estado, agente, zona y agrupación, se ve la tabla con sus totales
 * y se exporta a PDF o Excel con los mismos criterios.
 *  1. Muestra el formulario                -> index()
 *  2. Genera la tabla en pantalla           -> generar()
 *  3. Exporta a PDF o Excel                 -> exportar()
 */
class ReporteController extends Controller
{
    public function index(): View
    {
        return $this->pantalla(null, null, null);
    }

    public function generar(ReporteRequest $request): View|Response
    {
        if (! $request->hayTipo()) {
            return $this->pantalla(null, null, 'Elige el tipo de reporte.');
        }

        $criterios = $this->criteriosPermitidos($request);
        if ($criterios === null) {
            return $this->denegado();
        }

        if (! $criterios->rangoValido()) {
            return $this->pantalla($criterios, null, 'El rango de fechas no es válido.');
        }

        try {
            $resultado = app(ConstructorDeReportes::class)->construir($criterios, auth()->user());
        } catch (ReporteDemasiadoGrande $e) {
            return $this->pantalla($criterios, null, $e->getMessage());
        }

        BitacoraService::registrar('REPORTE_GENERADO', 'Reportes', 'Generó el ' . strtolower($resultado->titulo), [
            'tipo' => $criterios->tipo,
            'filtros' => $criterios->aParametros(),
            'filas' => $resultado->totales['cantidad'],
        ]);

        return $this->pantalla($criterios, $resultado, null);
    }

    public function exportar(ReporteRequest $request, string $formato): Response|BinaryFileResponse|RedirectResponse
    {
        abort_unless(in_array($formato, ['pdf', 'xlsx'], true), 404);

        $criterios = $this->criteriosPermitidos($request);
        if ($criterios === null) {
            return $this->denegado();
        }

        $volver = route('reportes.generar', $criterios->aParametros());

        if (! $criterios->rangoValido()) {
            return redirect($volver)->with('error', 'El rango de fechas no es válido.');
        }

        try {
            $resultado = app(ConstructorDeReportes::class)->construir($criterios, auth()->user());
        } catch (ReporteDemasiadoGrande $e) {
            return redirect($volver)->with('error', $e->getMessage());
        }

        if ($resultado->estaVacio()) {
            return redirect($volver)->with('error', 'Sin resultados para el reporte');
        }

        $usuario = auth()->user();
        $filtros = $criterios->descripcion($this->nombreAgente($criterios));
        $fechaHora = now()->format('d/m/Y H:i');
        $nombre = 'reporte-' . $criterios->tipo . '-' . now()->format('Ymd-His') . '.' . $formato;

        BitacoraService::registrar('REPORTE_EXPORTADO', 'Reportes', 'Exportó el ' . strtolower($resultado->titulo) . ' a ' . strtoupper($formato), [
            'tipo' => $criterios->tipo,
            'formato' => $formato,
            'filtros' => $criterios->aParametros(),
            'filas' => $resultado->totales['cantidad'],
        ]);

        if ($formato === 'pdf') {
            $contenido = (new ExportadorPdf())->generar($resultado, $filtros, $usuario->nombre, $fechaHora);

            return response($contenido, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
            ]);
        }

        $carpeta = storage_path('app/reportes');
        File::ensureDirectoryExists($carpeta);
        $ruta = $carpeta . DIRECTORY_SEPARATOR . uniqid('rep_', true) . '.xlsx';
        (new ExportadorExcel())->generar($resultado, $filtros, $usuario->nombre, $fechaHora, $ruta);

        return response()->download($ruta, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** Criterios de la URL si son válidos y el rol puede pedirlos; null si no. */
    private function criteriosPermitidos(ReporteRequest $request): ?CriteriosDeReporte
    {
        try {
            $criterios = $request->criterios();
        } catch (CriteriosNoValidos) {
            return null;
        }

        return ConstructorDeReportes::permite(auth()->user(), $criterios) ? $criterios : null;
    }

    private function denegado(): Response
    {
        return response()->view('reportesadministracion::CU20_GenerarReportes.denegado', [], 403);
    }

    private function nombreAgente(CriteriosDeReporte $criterios): ?string
    {
        return $criterios->idagente ? Usuario::whereKey($criterios->idagente)->value('nombre') : null;
    }

    private function pantalla(?CriteriosDeReporte $criterios, ?ResultadoDeReporte $resultado, ?string $error): View
    {
        $usuario = auth()->user();
        $tipos = array_intersect_key(CriteriosDeReporte::TIPOS, array_flip(ConstructorDeReportes::tiposPermitidos($usuario)));
        $esAdmin = $usuario->tieneRol(Rol::ADMINISTRADOR);

        return view('reportesadministracion::CU20_GenerarReportes.reportes', [
            'criterios' => $criterios,
            'resultado' => $resultado,
            'error' => $error,
            'tipos' => $tipos,
            'estadosPorTipo' => collect(array_keys($tipos))->mapWithKeys(fn ($t) => [$t => CriteriosDeReporte::etiquetasDeEstado($t)])->all(),
            'agrupaciones' => $esAdmin ? CriteriosDeReporte::AGRUPACIONES : array_diff_key(CriteriosDeReporte::AGRUPACIONES, ['agente' => 1]),
            'agentes' => $esAdmin
                ? Usuario::whereHas('rol', fn ($r) => $r->where('nombre', Rol::AGENTE))->orderBy('nombre')->pluck('nombre', 'idusuario')->all()
                : [],
            'zonas' => Ubicacion::whereNotNull('zona')->distinct()->orderBy('zona')->pluck('zona')->all(),
            'esAdmin' => $esAdmin,
            'filtros' => $criterios ? $criterios->descripcion($this->nombreAgente($criterios)) : [],
            'parametros' => $criterios?->aParametros() ?? [],
        ]);
    }
}