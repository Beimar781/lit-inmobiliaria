<?php

namespace App\Support\DatosDemo;

/**
 * Referencia a algo que no tiene un valor fijo: un agente, una categoría, otra fila ya insertada o el siguiente comprobante.
 * El plan de datos demo la usa en lugar de ids; cada escritor la resuelve a su manera (con el id real o con una subconsulta SQL).
 */
final class Ref
{
    public const AGENTE = 'agente';
    public const ASISTENTE = 'asistente';
    public const CATEGORIA = 'categoria';
    public const FILA = 'fila';
    public const COMPROBANTE = 'comprobante';

    private function __construct(public readonly string $tipo, public readonly array $datos)
    {
    }

    /** El agente número $posicion (1, 2, 3...) de los agentes activos, ordenados por id; si hay menos, se reparte en círculo. */
    public static function agente(int $posicion): self
    {
        return new self(self::AGENTE, ['posicion' => $posicion]);
    }

    /** El primer asistente administrativo activo (o, si no hay, el primer administrador). */
    public static function asistente(): self
    {
        return new self(self::ASISTENTE, []);
    }

    public static function categoria(string $nombre): self
    {
        return new self(self::CATEGORIA, ['nombre' => $nombre]);
    }

    /** Otra fila del plan, por su clave natural (correo, título...). */
    public static function fila(string $tabla, array $clave): self
    {
        return new self(self::FILA, ['tabla' => $tabla, 'clave' => $clave]);
    }

    /** El siguiente comprobante correlativo del año: CP-2026-000001, RV-..., DV-... */
    public static function comprobante(string $prefijo, string $tabla): self
    {
        return new self(self::COMPROBANTE, ['prefijo' => $prefijo, 'tabla' => $tabla]);
    }
}