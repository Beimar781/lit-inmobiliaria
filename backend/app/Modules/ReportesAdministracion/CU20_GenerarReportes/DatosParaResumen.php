<?php

namespace App\Modules\ReportesAdministracion\CU20_GenerarReportes;

/**
 * Lo único que la IA recibe para redactar el resumen: cifras agregadas (total, suma y conteos por grupo).
 * Nunca filas individuales, ni nombres, correos o teléfonos. Si se agrupa por agente, los nombres se cambian por "Agente 1", "Agente 2"...
 */
final class DatosParaResumen
{
    private const MAXIMO_GRUPOS = 50;

    public static function desde(CriteriosDeReporte $criterios, ResultadoDeReporte $resultado): array
    {
        $datos = [
            'reporte' => CriteriosDeReporte::TIPOS[$criterios->tipo],
            'filtros' => array_filter([
                'desde' => $criterios->desde?->format('Y-m-d'),
                'hasta' => $criterios->hasta?->format('Y-m-d'),
                'estado' => $criterios->estado,
                'zona' => $criterios->zona,
                'agrupacion' => $criterios->agrupacion !== 'ninguna' ? $criterios->agrupacion : null,
            ]),
            'total_filas' => $resultado->totales['cantidad'],
        ];

        if ($resultado->totales['suma'] !== null) {
            $datos['suma'] = ['concepto' => $resultado->totales['etiquetaSuma'], 'valor' => round($resultado->totales['suma'], 2)];
        }

        if ($resultado->agrupado) {
            $grupos = [];
            foreach (array_slice($resultado->filas, 0, self::MAXIMO_GRUPOS) as $i => $fila) {
                $grupos[] = array_filter([
                    'grupo' => $criterios->agrupacion === 'agente' ? 'Agente ' . ($i + 1) : $fila[0],
                    'cantidad' => $fila[1],
                    'suma' => $fila[2] ?? null,
                ], fn ($v) => $v !== null);
            }
            $datos['grupos'] = $grupos;
        }

        return $datos;
    }
}