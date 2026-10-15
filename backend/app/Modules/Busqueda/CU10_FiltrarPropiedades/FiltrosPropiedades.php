<?php

namespace App\Modules\Busqueda\CU10_FiltrarPropiedades;

use App\Models\Categoria;

/** Los filtros que eligió la persona en el catálogo (CU10), ya validados. */
class FiltrosPropiedades
{
    public function __construct(
        public readonly ?string $tipo = null,
        public readonly ?int $categoria = null,
        public readonly ?float $precioMin = null,
        public readonly ?float $precioMax = null,
        public readonly ?string $zona = null,
        public readonly ?float $superficieMin = null,
        public readonly ?float $superficieMax = null,
        public readonly ?int $habitaciones = null,
        public readonly ?int $banos = null,
    ) {
    }

    public static function desde(array $datos): self
    {
        $numero = fn (string $campo) => isset($datos[$campo]) && $datos[$campo] !== '' ? (float) $datos[$campo] : null;
        $entero = fn (string $campo) => isset($datos[$campo]) && $datos[$campo] !== '' ? (int) $datos[$campo] : null;
        $texto = fn (string $campo) => isset($datos[$campo]) && trim((string) $datos[$campo]) !== '' ? trim((string) $datos[$campo]) : null;

        return new self(
            tipo: $texto('tipo'),
            categoria: $entero('categoria'),
            precioMin: $numero('precio_min'),
            precioMax: $numero('precio_max'),
            zona: $texto('zona'),
            superficieMin: $numero('superficie_min'),
            superficieMax: $numero('superficie_max'),
            habitaciones: $entero('habitaciones'),
            banos: $entero('banos'),
        );
    }

    /** Etiquetas de los filtros activos, para mostrarlos sobre los resultados. */
    public function activos(): array
    {
        $formato = fn (float $n) => rtrim(rtrim(number_format($n, 2, '.', ','), '0'), '.');
        $activos = [];

        if ($this->tipo) {
            $activos[] = "Operación: {$this->tipo}";
        }
        if ($this->categoria) {
            $activos[] = 'Categoría: ' . (Categoria::find($this->categoria)?->nombre ?? $this->categoria);
        }
        if ($this->precioMin !== null) {
            $activos[] = 'Precio desde USD ' . $formato($this->precioMin);
        }
        if ($this->precioMax !== null) {
            $activos[] = 'Precio hasta USD ' . $formato($this->precioMax);
        }
        if ($this->zona) {
            $activos[] = "Zona: {$this->zona}";
        }
        if ($this->superficieMin !== null) {
            $activos[] = 'Superficie desde ' . $formato($this->superficieMin) . ' m²';
        }
        if ($this->superficieMax !== null) {
            $activos[] = 'Superficie hasta ' . $formato($this->superficieMax) . ' m²';
        }
        if ($this->habitaciones !== null) {
            $activos[] = "Habitaciones: {$this->habitaciones} o más";
        }
        if ($this->banos !== null) {
            $activos[] = "Baños: {$this->banos} o más";
        }

        return $activos;
    }

    public function hay(): bool
    {
        return $this->activos() !== [];
    }
}
