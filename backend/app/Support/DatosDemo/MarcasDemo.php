<?php

namespace App\Support\DatosDemo;

use Illuminate\Support\Facades\DB;

/** Cómo se reconocen los datos ficticios (demo) para poder borrarlos sin tocar los reales. */
final class MarcasDemo
{
    /** Los propietarios demo empiezan con este texto; de ellos cuelgan las propiedades demo. */
    public const PREFIJO_PROPIETARIO = 'DEMO - ';

    /** Los clientes demo tienen un correo de este dominio (y nunca una cuenta de acceso). */
    public const DOMINIO_CLIENTE = '@demo.lit.test';

    public static function existen(): bool
    {
        return DB::table('propietario')->where('nombre', 'like', self::PREFIJO_PROPIETARIO . '%')->exists()
            || DB::table('cliente')->where('email', 'like', '%' . self::DOMINIO_CLIENTE)->exists();
    }
}