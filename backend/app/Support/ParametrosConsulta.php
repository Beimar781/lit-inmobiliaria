<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Lee los parámetros de la URL (?q=...&estado=...) de los listados de forma segura.
 * Si alguien escribe a mano algo mal formado (?q[]=x, ?idusuario=abc) el valor se ignora en lugar de dar error 500.
 */
class ParametrosConsulta
{
    /** Texto limpio; vacío si no viene o no es un texto simple. */
    public static function texto(Request $request, string $nombre): string
    {
        $valor = $request->query($nombre);

        return is_string($valor) ? trim($valor) : '';
    }

    /** Número entero positivo; null si no viene o no es un número. */
    public static function entero(Request $request, string $nombre): ?int
    {
        $valor = self::texto($request, $nombre);

        return ctype_digit($valor) && strlen($valor) <= 9 ? (int) $valor : null;
    }

    /** El texto si es una de las opciones permitidas; null si no. */
    public static function opcion(Request $request, string $nombre, array $permitidas): ?string
    {
        $valor = self::texto($request, $nombre);

        return in_array($valor, $permitidas, true) ? $valor : null;
    }
}
