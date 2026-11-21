<?php

namespace App\Modules\ReportesAdministracion\CU20_GenerarReportes;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

/** Exporta un reporte a PDF (tabla, totales, filtros aplicados, quién lo generó y cuándo). */
class ExportadorPdf
{
    public function generar(ResultadoDeReporte $resultado, array $filtros, string $generadoPor, string $fechaHora): string
    {
        $cache = storage_path('framework/cache/dompdf');
        File::ensureDirectoryExists($cache);

        $opciones = new Options();
        $opciones->set('defaultFont', 'DejaVu Sans'); // trae tildes y ñ
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('fontCache', $cache);
        $opciones->set('tempDir', $cache);

        $html = view('reportesadministracion::CU20_GenerarReportes.pdf', [
            'resultado' => $resultado,
            'filtros' => $filtros,
            'generadoPor' => $generadoPor,
            'fechaHora' => $fechaHora,
        ])->render();

        $pdf = new Dompdf($opciones);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return $pdf->output();
    }
}