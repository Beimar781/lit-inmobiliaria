<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/*
 * Usuarios de prueba (solo para desarrollo local).
 * Contraseña de todos: Lit2026*
 */
class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            ['Juan Carlos García', 'jgarcia@inmobiliaria.com', '77012345', Rol::ADMINISTRADOR],
            ['María Fernanda Torrez', 'mtorrez@inmobiliaria.com', '78543210', Rol::AGENTE],
            ['Roberto Luis Justiniano', 'rjustiniano@inmobiliaria.com', '70918273', Rol::ASISTENTE],
            ['Carlos Eduardo Roca', 'croca@gmail.com', '71029384', Rol::CLIENTE],
        ];

        foreach ($usuarios as [$nombre, $email, $telefono, $rol]) {
            Usuario::updateOrCreate(
                ['email' => $email],
                [
                    'nombre' => $nombre,
                    'telefono' => $telefono,
                    'password' => 'Lit2026*', // el modelo la guarda con hash
                    'estado' => Usuario::ACTIVO,
                    'idrol' => Rol::where('nombre', $rol)->value('idrol'),
                ]
            );
        }
    }
}
