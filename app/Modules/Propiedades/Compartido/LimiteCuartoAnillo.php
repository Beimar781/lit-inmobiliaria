<?php

namespace App\Modules\Propiedades\Compartido;

/** Regla de alcance: solo Santa Cruz de la Sierra, dentro del 4.º anillo (ver config/santacruz.php). */
class LimiteCuartoAnillo
{
    public static function contiene(float $latitud, float $longitud): bool
    {
        $centro = config('santacruz.centro');

        return self::distanciaKm($latitud, $longitud, $centro['lat'], $centro['lng']) <= config('santacruz.radio_km');
    }

    /** Distancia en km entre dos puntos (fórmula de Haversine). */
    public static function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $radioTierra = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $radioTierra * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
