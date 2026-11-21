<?php

namespace App\Modules\ReportesAdministracion\CU20_GenerarReportes;

/** Tabla de un reporte ya armada: columnas, filas y totales. Las columnas numéricas guardan números (no texto). */
final class ResultadoDeReporte
{
    /**
     * @param string[]           $columnas
     * @param array<int, array>  $filas
     * @param int[]              $columnasNumericas  posiciones de las columnas que son dinero
     * @param array{cantidad: int, suma: ?float, etiquetaSuma: ?string} $totales
     */
    public function __construct(
        public readonly string $titulo,
        public readonly array $columnas,
        public readonly array $filas,
        public readonly array $columnasNumericas,
        public readonly array $totales,
        public readonly bool $agrupado,
    ) {
    }

    public function estaVacio(): bool
    {
        return $this->totales['cantidad'] === 0;
    }

    /** Valor listo para mostrar: los números con 2 decimales y separador de miles. */
    public function formatear(int $columna, mixed $valor): string
    {
        if ($valor !== null && in_array($columna, $this->columnasNumericas, true)) {
            return number_format((float) $valor, 2, '.', ',');
        }

        return (string) ($valor ?? '');
    }
}