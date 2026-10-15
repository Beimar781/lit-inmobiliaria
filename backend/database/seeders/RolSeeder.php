<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [Rol::ADMINISTRADOR, 'Control total del sistema'],
            [Rol::AGENTE, 'Gestiona propiedades, clientes y visitas'],
            [Rol::ASISTENTE, 'Apoyo operativo: clientes, agenda y reportes'],
            [Rol::CLIENTE, 'Usuario externo interesado en inmuebles'],
        ];

        foreach ($roles as [$nombre, $descripcion]) {
            Rol::firstOrCreate(['nombre' => $nombre], ['descripcion' => $descripcion]);
        }
    }
}
