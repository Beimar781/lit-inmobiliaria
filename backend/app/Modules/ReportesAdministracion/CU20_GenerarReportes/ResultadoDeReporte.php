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

    /**
     * Texto para leer el resultado en voz alta: título, cantidad, suma, el resumen de la IA (si hay) y los primeros grupos.
     * Los números van sin separador de miles y con coma decimal, que es como los lee bien la voz en español.
     */
    public function textoParaVoz(array $filtros = [], ?string $resumen = null): string
    {
        $frases = [$this->titulo . '.'];

        // La primera línea de los filtros es el tipo, que ya está en el título.
        if ($resto = array_slice($filtros, 1)) {
            $frases[] = implode('. ', $resto) . '.';
        }

        $cantidad = $this->totales['cantidad'];
        if ($cantidad === 0) {
            return implode(' ', array_filter($frases)) . ' El reporte no tiene resultados.';
        }

        $frases[] = $cantidad === 1 ? 'Hay 1 fila.' : "Hay {$cantidad} filas.";

        if ($this->totales['suma'] !== null) {
            $frases[] = $this->totales['etiquetaSuma'] . ': ' . $this->numeroHablado($this->totales['suma']) . '.';
        }

        if ($resumen) {
            $frases[] = $resumen;
        } elseif ($this->agrupado) {
            foreach (array_slice($this->filas, 0, 8) as $fila) {
                $frases[] = "{$fila[0]}: {$fila[1]}" . (isset($fila[2]) ? ', por ' . $this->numeroHablado($fila[2]) . ' dólares' : '') . '.';
            }
        }

        return trim(implode(' ', array_filter($frases)));
    }

    private function numeroHablado(float $valor): string
    {
        return fmod($valor, 1.0) == 0.0 ? (string) (int) $valor : number_format($valor, 2, ',', '');
    }
}