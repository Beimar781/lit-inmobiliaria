<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Propiedad;
use App\Models\Propietario;
use App\Models\Rol;
use App\Models\Ubicacion;
use App\Models\Usuario;
use App\Modules\Propiedades\Compartido\GestorImagenes;
use Database\Seeders\RolSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Base de las pruebas del sistema: base de datos limpia en cada prueba, los 4 roles ya creados
 * y una carpeta "public" temporal para que las imágenes de prueba no ensucien public/uploads.
 */
abstract class SistemaTestCase extends TestCase
{
    use RefreshDatabase;

    public const CLAVE = 'Clave123';

    // Punto dentro del 4.º anillo (Equipetrol) y punto fuera (Warnes)
    public const LAT_DENTRO = '-17.7630000';
    public const LNG_DENTRO = '-63.1980000';
    public const LAT_FUERA = '-17.5000000';
    public const LNG_FUERA = '-63.1700000';

    private string $carpetaPublica;

    protected function setUp(): void
    {
        parent::setUp();

        self::registrarLimpiezaFinal();

        $this->seed(RolSeeder::class);

        $this->carpetaPublica = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lit_test_' . uniqid();
        File::ensureDirectoryExists($this->carpetaPublica);
        $this->app->usePublicPath($this->carpetaPublica);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Va DESPUÉS de parent::tearDown() y usa Filesystem directamente (la fachada File ya no existe).
        // En Windows un archivo subido no se puede borrar mientras siga abierto su recurso temporal;
        // si no se pudo ahora, se borra al terminar toda la ejecución (ver registrarLimpiezaFinal).
        gc_collect_cycles();
        (new Filesystem())->deleteDirectory($this->carpetaPublica);
    }

    /** Borra al terminar la ejecución las carpetas temporales que no se pudieron borrar antes, y los restos de ejecuciones anteriores. */
    private static function registrarLimpiezaFinal(): void
    {
        static $registrada = false;

        if ($registrada) {
            return;
        }
        $registrada = true;

        $limpiar = function (): void {
            $archivos = new Filesystem();
            foreach (glob(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lit_test_*', GLOB_ONLYDIR) ?: [] as $carpeta) {
                $archivos->deleteDirectory($carpeta);
            }
        };

        $limpiar(); // restos de ejecuciones anteriores
        register_shutdown_function($limpiar);
    }

    // ------------------------------------------------------------------ usuarios

    protected function usuario(string $rol, array $datos = []): Usuario
    {
        static $contador = 0;
        $contador++;

        return Usuario::create(array_merge([
            'nombre' => "Usuario {$contador}",
            'email' => "usuario{$contador}@test.com",
            'telefono' => '70000000',
            'password' => self::CLAVE,
            'estado' => Usuario::ACTIVO,
            'idrol' => $this->idRol($rol),
        ], $datos));
    }

    protected function admin(array $datos = []): Usuario
    {
        return $this->usuario(Rol::ADMINISTRADOR, $datos);
    }

    protected function agente(array $datos = []): Usuario
    {
        return $this->usuario(Rol::AGENTE, $datos);
    }

    protected function asistente(array $datos = []): Usuario
    {
        return $this->usuario(Rol::ASISTENTE, $datos);
    }

    protected function cliente(array $datos = []): Usuario
    {
        return $this->usuario(Rol::CLIENTE, $datos);
    }

    protected function idRol(string $nombre): int
    {
        return (int) Rol::where('nombre', $nombre)->value('idrol');
    }

    // --------------------------------------------------------------- propiedades

    protected function categoria(string $nombre = 'Casa'): Categoria
    {
        return Categoria::firstOrCreate(['nombre' => $nombre], ['descripcion' => "Categoría {$nombre}"]);
    }

    protected function propietario(string $nombre = 'Luis Parada'): Propietario
    {
        return Propietario::firstOrCreate(['nombre' => $nombre], ['telefono' => '72345678', 'email' => 'lp@test.com']);
    }

    /** Crea una propiedad ya guardada (sin pasar por el CU5), con su ubicación dentro del 4.º anillo. */
    protected function propiedad(array $datos = [], ?Usuario $agente = null): Propiedad
    {
        $ubicacion = Ubicacion::create([
            'ciudad' => config('santacruz.ciudad'),
            'zona' => 'Equipetrol',
            'direccion' => 'Av. San Martín',
            'latitud' => self::LAT_DENTRO,
            'longitud' => self::LNG_DENTRO,
        ]);

        return Propiedad::create(array_merge([
            'idpropietario' => $this->propietario()->idpropietario,
            'idcategoria' => $this->categoria()->idcategoria,
            'idubicacion' => $ubicacion->idubicacion,
            'idusuario' => $agente?->idusuario,
            'titulo' => 'Casa de prueba',
            'descripcion' => 'Descripción de prueba',
            'precio' => 85000,
            'tipopropiedad' => 'Venta',
            'estadopropiedad' => Propiedad::DISPONIBLE,
            'superficie' => 200,
            'areaconstruida' => 150,
            'habitaciones' => 3,
            'banos' => 2,
            'antiguedad' => 5,
        ], $datos));
    }

    /** Agrega $cantidad imágenes guardadas (archivo real en la carpeta temporal) a una propiedad. */
    protected function conImagenes(Propiedad $propiedad, int $cantidad = 2): Propiedad
    {
        $archivos = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $archivos[] = $this->imagen("foto{$i}.jpg");
        }

        GestorImagenes::guardar($propiedad, $archivos);
        GestorImagenes::definirPortada($propiedad, null);

        return $propiedad->fresh(['imagenes']);
    }

    protected function imagen(string $nombre = 'foto.jpg', int $kb = 100): UploadedFile
    {
        return UploadedFile::fake()->image($nombre, 600, 400)->size($kb);
    }

    protected function rutaFisica(string $rutaRelativa): string
    {
        return public_path($rutaRelativa);
    }

    /** Datos válidos del formulario de propiedades (sin imágenes), con propietario nuevo. */
    protected function datosPropiedad(array $cambios = []): array
    {
        return array_merge([
            'titulo' => 'Casa familiar en Equipetrol',
            'descripcion' => 'Casa con jardín y garaje.',
            'precio' => '95000.50',
            'tipopropiedad' => 'Venta',
            'superficie' => '200',
            'areaconstruida' => '150',
            'habitaciones' => '3',
            'banos' => '2',
            'antiguedad' => '5',
            'idcategoria' => $this->categoria()->idcategoria,
            'propietario_nombre' => 'Nuevo Propietario',
            'propietario_telefono' => '70011223',
            'propietario_email' => 'nuevo@test.com',
            'propietario_direccion' => 'Calle 1 #23',
            'zona' => 'Equipetrol',
            'direccion' => 'Av. San Martín (referencial)',
            'latitud' => self::LAT_DENTRO,
            'longitud' => self::LNG_DENTRO,
        ], $cambios);
    }

    /** Formulario de modificación con exactamente los valores actuales de la propiedad (más $cambios). */
    protected function datosActuales(Propiedad $propiedad, array $cambios = []): array
    {
        $propiedad = $propiedad->fresh('ubicacion');

        return array_merge([
            'titulo' => $propiedad->titulo,
            'descripcion' => $propiedad->descripcion,
            'precio' => (string) $propiedad->precio,
            'tipopropiedad' => $propiedad->tipopropiedad,
            'estadopropiedad' => $propiedad->estadopropiedad,
            'superficie' => (string) $propiedad->superficie,
            'areaconstruida' => (string) $propiedad->areaconstruida,
            'habitaciones' => (string) $propiedad->habitaciones,
            'banos' => (string) $propiedad->banos,
            'antiguedad' => (string) $propiedad->antiguedad,
            'idcategoria' => $propiedad->idcategoria,
            'idpropietario' => $propiedad->idpropietario,
            'zona' => $propiedad->ubicacion->zona,
            'direccion' => $propiedad->ubicacion->direccion,
            'latitud' => (string) $propiedad->ubicacion->latitud,
            'longitud' => (string) $propiedad->ubicacion->longitud,
        ], $cambios);
    }
}
