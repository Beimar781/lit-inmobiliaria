<?php

namespace App\Services;

use App\Models\Historial;
use App\Models\Propiedad;

/** Registra en la tabla HISTORIAL lo que se hace con cada propiedad (CU5, CU6 y CU7). */
class HistorialService
{
    public static function registrar(string $tipo, int $idpropiedad, ?array $anterior, ?array $actual, ?string $motivo = null): void
    {
        Historial::create([
            'tipo' => $tipo,
            'valoranterior' => $anterior === null ? null : json_encode($anterior, JSON_UNESCAPED_UNICODE),
            'valoractual' => $actual === null ? null : json_encode($actual, JSON_UNESCAPED_UNICODE),
            'motivo' => $motivo,
            'idusuario' => auth()->id(),
            'idpropiedad' => $idpropiedad,
        ]);
    }

    /** Foto de los datos importantes de la propiedad, para comparar antes/después. */
    public static function instantanea(Propiedad $propiedad): array
    {
        $propiedad->loadMissing('ubicacion');

        return [
            'titulo' => $propiedad->titulo,
            'descripcion' => $propiedad->descripcion,
            'precio' => (string) $propiedad->precio,
            'tipo' => $propiedad->tipopropiedad,
            'estado' => $propiedad->estadopropiedad,
            'superficie' => $propiedad->superficie,
            'areaconstruida' => $propiedad->areaconstruida,
            'habitaciones' => $propiedad->habitaciones,
            'banos' => $propiedad->banos,
            'antiguedad' => $propiedad->antiguedad,
            'idpropietario' => $propiedad->idpropietario,
            'idcategoria' => $propiedad->idcategoria,
            'zona' => $propiedad->ubicacion?->zona,
            'direccion' => $propiedad->ubicacion?->direccion,
            'latitud' => $propiedad->ubicacion?->latitud,
            'longitud' => $propiedad->ubicacion?->longitud,
        ];
    }

    /** Devuelve [valores anteriores, valores actuales] solo de los campos que cambiaron. */
    public static function cambios(array $antes, array $despues): array
    {
        $anterior = [];
        $actual = [];

        foreach ($despues as $campo => $valor) {
            if ((string) ($antes[$campo] ?? '') !== (string) $valor) {
                $anterior[$campo] = $antes[$campo] ?? null;
                $actual[$campo] = $valor;
            }
        }

        return [$anterior, $actual];
    }
}
