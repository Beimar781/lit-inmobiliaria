<?php

namespace App\Support\DatosDemo;

use App\Models\Propiedad;
use Illuminate\Support\Facades\DB;

/**
 * Elige en qué sectores se crean las propiedades demo: los 8 con MENOS propiedades reales, una por sector,
 * y nunca en Equipetrol ni en Zona Norte (que es donde ya está la mayoría de las reales).
 */
final class SelectorDeSectores
{
    public const CANTIDAD = 8;

    /** Zonas donde NO se crea nada demo (Equipetrol Norte cuenta como Equipetrol). */
    public const EXCLUIDOS = ['Equipetrol', 'Equipetrol Norte', 'Zona Norte'];

    /** Sectores posibles (los del sistema, sin los excluidos) con un punto de referencia dentro del 4.º anillo: sector => [latitud, longitud]. */
    public const ANCLAS = [
        'Centro' => [-17.7850, -63.1790], 'Zona Sur' => [-17.8150, -63.1750], 'Zona Este' => [-17.7830, -63.1500],
        'Zona Oeste' => [-17.7850, -63.2150], 'Urbarí' => [-17.7976, -63.1973], 'Sirari' => [-17.7680, -63.1860],
        'Las Palmas' => [-17.8009, -63.1962], 'Hamacas' => [-17.8100, -63.1900], 'Barrio Lindo' => [-17.7790, -63.1650],
        'Villa 1.º de Mayo' => [-17.7700, -63.1520],
    ];

    /** Los candidatos, en el orden del sistema (sirve para desempatar siempre igual). @return string[] */
    public static function candidatos(): array
    {
        return array_values(array_filter(
            Propiedad::SECTORES,
            fn (string $sector) => isset(self::ANCLAS[$sector]) && ! in_array($sector, self::EXCLUIDOS, true)
        ));
    }

    /** @return string[] los 8 sectores con menos propiedades reales, de menos a más (los empates se resuelven por el orden del sistema) */
    public function elegir(): array
    {
        $candidatos = self::candidatos();
        $conteo = $this->propiedadesRealesPorSector();

        usort($candidatos, function (string $a, string $b) use ($conteo, $candidatos) {
            return [$conteo[$a] ?? 0, array_search($a, Propiedad::SECTORES, true)] <=> [$conteo[$b] ?? 0, array_search($b, Propiedad::SECTORES, true)];
        });

        return array_slice($candidatos, 0, self::CANTIDAD);
    }

    /** Propiedades reales (no demo) por sector, contando el sector y la zona de la ubicación. @return array<string, int> */
    public function propiedadesRealesPorSector(): array
    {
        $filas = DB::table('propiedad')
            ->leftJoin('ubicacion', 'ubicacion.idubicacion', '=', 'propiedad.idubicacion')
            ->whereNotIn('propiedad.idpropietario', DB::table('propietario')->where('nombre', 'like', MarcasDemo::PREFIJO_PROPIETARIO . '%')->select('idpropietario'))
            ->get(['propiedad.sector', 'ubicacion.zona']);

        $conteo = [];
        foreach ($filas as $fila) {
            // Una propiedad cuenta una sola vez en su sector (o, si no tiene, en su zona).
            $lugar = trim((string) ($fila->sector ?: $fila->zona));
            if ($lugar !== '') {
                $conteo[$lugar] = ($conteo[$lugar] ?? 0) + 1;
            }
        }

        return $conteo;
    }

    /** Mensaje de error si la lista de sectores indicada a mano no sirve; null si es válida. */
    public static function errorDe(array $sectores): ?string
    {
        if (count($sectores) !== self::CANTIDAD || count(array_unique($sectores)) !== self::CANTIDAD) {
            return 'Indica exactamente ' . self::CANTIDAD . ' sectores distintos.';
        }

        foreach ($sectores as $sector) {
            if (in_array($sector, self::EXCLUIDOS, true)) {
                return "No se crean propiedades demo en {$sector}.";
            }
            if (! isset(self::ANCLAS[$sector])) {
                return "Sector desconocido: {$sector}. Los posibles son: " . implode(', ', self::candidatos()) . '.';
            }
        }

        return null;
    }
}
