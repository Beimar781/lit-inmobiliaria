<?php

namespace Tests\Feature;

use App\Models\Imagen;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\InstantaneaDatos;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DatosCompartidosSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/** Los datos compartidos del equipo (database/datos): exportar, cargar y no pisar datos existentes. */
class DatosCompartidosTest extends SistemaTestCase
{
    public function test_exportar_y_volver_a_cargar_devuelve_los_mismos_datos(): void
    {
        $this->seed(DatabaseSeeder::class); // en las pruebas son los datos de ejemplo

        $temporal = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lit_datos_' . uniqid();
        $servicio = new InstantaneaDatos($temporal . '/datos.json', $temporal . '/uploads');

        $antes = [];
        foreach ($servicio->tablas() as $tabla) {
            $antes[$tabla] = DB::table($tabla)->count();
        }
        $totalFotos = Imagen::count();

        $resultado = $servicio->exportar();

        $this->assertFileExists($servicio->archivo());
        $this->assertSame(4, $resultado['tablas']['usuario']);
        $this->assertSame(8, $resultado['tablas']['propiedad']);
        $this->assertSame($totalFotos, $resultado['fotos']);
        $this->assertTrue($servicio->baseConDatos());

        // Se vacía todo, como en una computadora nueva.
        foreach (array_reverse($servicio->tablas()) as $tabla) {
            DB::table($tabla)->delete();
        }
        File::deleteDirectory(public_path('uploads'));
        $this->assertFalse($servicio->baseConDatos());

        $servicio->importar();

        foreach ($antes as $tabla => $cantidad) {
            $this->assertSame($cantidad, DB::table($tabla)->count(), $tabla);
        }
        foreach (Imagen::all() as $imagen) {
            $this->assertFileExists($this->rutaFisica($imagen->ruta));
        }
        $this->assertNotNull(Usuario::where('email', 'jgarcia@inmobiliaria.com')->first());

        File::deleteDirectory($temporal);
    }

    public function test_no_se_carga_encima_de_una_base_con_datos(): void
    {
        $this->seed(DatabaseSeeder::class);
        $usuarios = Usuario::count();

        $this->seed(DatosCompartidosSeeder::class);

        $this->assertSame($usuarios, Usuario::count());
    }

    public function test_el_archivo_del_equipo_se_carga_completo_en_una_base_vacia(): void
    {
        $servicio = new InstantaneaDatos();

        // Sin archivo del equipo (todavía no se exportó) no hay nada que cargar: el seeder solo avisa.
        if (! $servicio->existe()) {
            $this->seed(DatosCompartidosSeeder::class);
            $this->assertSame(0, Usuario::count());

            return;
        }

        Rol::query()->delete(); // las pruebas siembran los roles; el archivo del equipo trae los suyos

        $this->seed(DatosCompartidosSeeder::class);

        $esperado = json_decode(File::get($servicio->archivo()), true);
        foreach ($esperado as $tabla => $filas) {
            $this->assertSame(count($filas), DB::table($tabla)->count(), $tabla);
        }
        foreach (Imagen::all() as $imagen) {
            $this->assertFileExists($this->rutaFisica($imagen->ruta), $imagen->ruta);
        }
    }
}
