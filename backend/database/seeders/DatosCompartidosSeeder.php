<?php

namespace Database\Seeders;

use App\Services\InstantaneaDatos;
use Illuminate\Database\Seeder;

/*
 * Carga los datos compartidos del equipo (database/datos/datos.json y database/datos/uploads):
 * usuarios, propiedades con sus fotos, historial y bitácora.
 *
 * Solo carga en una base VACÍA: si ya hay datos no toca nada, así no se pierde trabajo por error.
 * Se ejecuta sola con `php artisan migrate:fresh --seed`.
 */
class DatosCompartidosSeeder extends Seeder
{
    public function run(): void
    {
        $datos = new InstantaneaDatos();

        if (! $datos->existe()) {
            $this->command?->warn('No existe database/datos/datos.json: no hay datos compartidos que cargar.');

            return;
        }

        if ($datos->baseConDatos()) {
            $this->command?->warn('La base ya tiene datos: no se cargó el archivo compartido. Para empezar de cero usa php artisan migrate:fresh --seed');

            return;
        }

        foreach ($datos->importar() as $tabla => $total) {
            $this->command?->info(sprintf('  %-14s %d registros', $tabla, $total));
        }
    }
}
