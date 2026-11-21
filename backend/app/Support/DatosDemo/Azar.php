<?php

namespace App\Support\DatosDemo;

/** Generador de números al azar con semilla fija: la misma semilla da siempre los mismos datos (en cualquier computadora). */
final class Azar
{
    private int $estado;

    public function __construct(int $semilla)
    {
        $this->estado = $semilla & 0x7fffffff;
    }

    private function siguiente(): int
    {
        $this->estado = ($this->estado * 1103515245 + 12345) & 0x7fffffff;

        return $this->estado >> 8; // los bits bajos de este tipo de generador son pobres: se descartan
    }

    /** Entero entre $min y $max, ambos incluidos. */
    public function entero(int $min, int $max): int
    {
        return $min + (int) floor($this->siguiente() / (1 << 23) * ($max - $min + 1));
    }

    public function probabilidad(int $porcentaje): bool
    {
        return $this->entero(1, 100) <= $porcentaje;
    }

    public function elegir(array $lista): mixed
    {
        return $lista[$this->entero(0, count($lista) - 1)];
    }

    /** Devuelve una CLAVE de $pesos con probabilidad proporcional a su peso: ['A' => 70, 'B' => 30]. */
    public function ponderado(array $pesos): string|int
    {
        $punto = $this->entero(1, (int) array_sum($pesos));
        foreach ($pesos as $clave => $peso) {
            $punto -= $peso;
            if ($punto <= 0) {
                return $clave;
            }
        }

        return array_key_last($pesos);
    }
}