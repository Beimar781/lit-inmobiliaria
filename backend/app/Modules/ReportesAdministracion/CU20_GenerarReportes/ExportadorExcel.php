<?php

namespace App\Modules\ReportesAdministracion\CU20_GenerarReportes;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/** Exporta un reporte a Excel (.xlsx): título, filtros, quién lo generó, encabezados, filas y los totales al final. */
class ExportadorExcel
{
    /** Escribe el archivo en $ruta. Los textos se guardan siempre como texto (un valor que empiece con "=" no se vuelve fórmula). */
    public function generar(ResultadoDeReporte $resultado, array $filtros, string $generadoPor, string $fechaHora, string $ruta): void
    {
        $escritor = new Writer();
        $escritor->openToFile($ruta);

        $negrita = (new Style())->withFontBold(true);

        $escritor->addRow(Row::fromValuesWithStyle([$resultado->titulo], $negrita));
        foreach ($filtros as $linea) {
            $escritor->addRow($this->fila([$linea]));
        }
        $escritor->addRow($this->fila(["Generado por: {$generadoPor}"]));
        $escritor->addRow($this->fila(["Fecha y hora: {$fechaHora}"]));
        $escritor->addRow($this->fila([]));

        $escritor->addRow(Row::fromValuesWithStyle($resultado->columnas, $negrita));
        foreach ($resultado->filas as $fila) {
            $escritor->addRow($this->fila($fila));
        }

        $escritor->addRow($this->fila([]));
        $totales = $resultado->totales;
        $escritor->addRow(new Row([
            new StringCell('Total de filas', $negrita),
            Cell::fromValue($totales['cantidad'], $negrita),
        ]));
        if ($totales['suma'] !== null) {
            $escritor->addRow(new Row([
                new StringCell($totales['etiquetaSuma'], $negrita),
                Cell::fromValue(round($totales['suma'], 2), $negrita),
            ]));
        }

        $escritor->close();
    }

    private function fila(array $valores): Row
    {
        return new Row(array_map(function ($valor) {
            return match (true) {
                $valor === null || $valor === '' => Cell::fromValue(''),
                is_int($valor) || is_float($valor) => Cell::fromValue($valor),
                default => new StringCell((string) $valor, null),
            };
        }, $valores));
    }
}