<?php

namespace App\Modules\CrmGestionClientes\Compartido;

use App\Models\Cliente;
use App\Models\Rol;

/** Regla de acceso a un prospecto (CU13, CU18 y CU24): el Agente solo accede a los suyos; el Administrador, a todos. */
class AccesoAProspecto
{
    public static function autorizar(Cliente $cliente): void
    {
        $usuario = auth()->user();

        abort_unless(
            $usuario->tieneRol(Rol::ADMINISTRADOR) || $cliente->idusuario === $usuario->idusuario,
            403,
            'Este prospecto no está asignado a ti.'
        );
    }
}