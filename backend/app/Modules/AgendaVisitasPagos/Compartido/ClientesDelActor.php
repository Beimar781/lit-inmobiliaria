<?php

namespace App\Modules\AgendaVisitasPagos\Compartido;

use App\Models\Cliente;
use App\Models\Rol;
use App\Models\Usuario;

/**
 * Clientes del CRM que cada rol puede elegir en los formularios de reservas y contratos:
 * el Agente solo entre sus prospectos; el Administrador, todos.
 */
class ClientesDelActor
{
    public static function permitido(Usuario $actor, Cliente $cliente): bool
    {
        return ! $actor->tieneRol(Rol::AGENTE) || $cliente->idusuario === $actor->idusuario;
    }

    /** @return array<int,string> id => "Nombre · contacto" */
    public static function opciones(Usuario $actor): array
    {
        return Cliente::query()
            ->when($actor->tieneRol(Rol::AGENTE), fn ($c) => $c->where('idusuario', $actor->idusuario))
            ->orderBy('nombre')
            ->get()
            ->mapWithKeys(fn (Cliente $c) => [$c->idcliente => $c->nombre . ($c->email ? " · {$c->email}" : ($c->telefono ? " · {$c->telefono}" : ''))])
            ->all();
    }
}
