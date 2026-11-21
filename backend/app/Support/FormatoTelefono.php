<?php

namespace App\Support;

/** Formato de teléfono que aceptan los formularios del sistema: de 7 a 20 dígitos, con espacios o guiones, y + inicial opcional. */
class FormatoTelefono
{
    public const REGEX = '/^\+?[0-9][0-9\s\-]{5,18}[0-9]$/';
}