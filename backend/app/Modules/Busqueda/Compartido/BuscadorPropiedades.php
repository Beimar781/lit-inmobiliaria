<?php

namespace App\Modules\Busqueda\Compartido;

use App\Modules\Busqueda\CU10_FiltrarPropiedades\FiltrosPropiedades;
use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda de propiedades que comparten el catálogo público (CU9/CU10) y el inventario interno del agente.
 * Aquí solo se agregan condiciones a una consulta; qué propiedades entran (disponibles o vigentes) lo decide quien la llama.
 */
class BuscadorPropiedades
{
    /** Palabra clave (título, zona o dirección) o código de propiedad: "LIT-0007", "lit 7" o "7". */
    public static function texto(Builder $consulta, ?string $texto): Builder
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return $consulta;
        }

        return $consulta->where(function (Builder $donde) use ($texto) {
            $donde->whereLike('titulo', "%{$texto}%")
                ->orWhereHas('ubicacion', fn (Builder $u) => $u
                    ->whereLike('zona', "%{$texto}%")
                    ->orWhereLike('direccion', "%{$texto}%"));

            if (preg_match('/^(?:lit[-\s]?)?0*(\d{1,9})$/i', $texto, $coincidencia)) {
                $donde->orWhere('idpropiedad', (int) $coincidencia[1]);
            }
        });
    }

    /** Filtros avanzados del CU10. Se combinan entre sí (todos deben cumplirse). */
    public static function filtros(Builder $consulta, FiltrosPropiedades $filtros): Builder
    {
        return $consulta
            ->when($filtros->tipo, fn (Builder $c, $tipo) => $c->where('tipopropiedad', $tipo))
            ->when($filtros->categoria, fn (Builder $c, $categoria) => $c->where('idcategoria', $categoria))
            ->when($filtros->precioMin !== null, fn (Builder $c) => $c->where('precio', '>=', $filtros->precioMin))
            ->when($filtros->precioMax !== null, fn (Builder $c) => $c->where('precio', '<=', $filtros->precioMax))
            ->when($filtros->zona, fn (Builder $c, $zona) => $c->whereHas('ubicacion', fn (Builder $u) => $u->whereLike('zona', "%{$zona}%")))
            ->when($filtros->superficieMin !== null, fn (Builder $c) => $c->where('superficie', '>=', $filtros->superficieMin))
            ->when($filtros->superficieMax !== null, fn (Builder $c) => $c->where('superficie', '<=', $filtros->superficieMax))
            ->when($filtros->habitaciones !== null, fn (Builder $c) => $c->where('habitaciones', '>=', $filtros->habitaciones))
            ->when($filtros->banos !== null, fn (Builder $c) => $c->where('banos', '>=', $filtros->banos));
    }
}
