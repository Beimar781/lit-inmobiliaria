<?php

namespace Database\Seeders;

use App\Services\InstantaneaDatos;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Si existe el archivo del equipo (database/datos), todos parten de los mismos usuarios, propiedades,
        // fotos y bitácora. Las pruebas automáticas, y quien no tenga ese archivo, usan los datos de ejemplo.
        if (! app()->runningUnitTests() && (new InstantaneaDatos())->existe()) {
            $this->call(DatosCompartidosSeeder::class);

            return;
        }

        $this->call([
            RolSeeder::class,
            UsuarioSeeder::class,
            CatalogoSeeder::class,
            PropiedadSeeder::class,
        ]);
    }
}
