<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Historial;
use App\Models\Imagen;
use App\Models\Interes;
use App\Models\Propiedad;
use App\Models\Propietario;
use App\Models\Rol;
use App\Models\Ubicacion;
use App\Models\Usuario;
use App\Modules\GestionPropiedades\Compartido\LimiteCuartoAnillo;
use App\Services\HistorialService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/*
 * Datos de PRUEBA para el Ciclo 2 (CRM): dos agentes más, una propiedad para cada uno y varios prospectos repartidos.
 * Sirve para probar lo que con un solo agente no se puede ver:
 *   - el administrador reasigna un prospecto a otro agente (CU13);
 *   - cada agente ve solo sus prospectos y recibe "403" en los de otros (CU13);
 *   - un agente no puede cambiar el estado de la propiedad de otro (CU8).
 *
 * NO borra ni cambia nada de lo que ya existe: solo agrega lo que falta, así que se puede ejecutar varias veces
 * (con tus usuarios, propiedades, fotos y bitácora del Ciclo 1 intactos):
 *   php artisan db:seed --class=DemoCrmSeeder
 * Contraseña de los agentes nuevos: Lit2026*
 */
class DemoCrmSeeder extends Seeder
{
    public function run(): void
    {
        $idRolAgente = Rol::where('nombre', Rol::AGENTE)->value('idrol');

        $carla = $this->agente('Carla Suárez', 'csuarez@inmobiliaria.com', '76011223', $idRolAgente);
        $diego = $this->agente('Diego Vaca', 'dvaca@inmobiliaria.com', '71500677', $idRolAgente);
        $maria = Usuario::where('email', 'mtorrez@inmobiliaria.com')->first();

        $casaCarla = $this->propiedad($carla, [
            'titulo' => 'Casa moderna de 3 dormitorios en Equipetrol Norte',
            'descripcion' => 'Casa de dos plantas con jardín, churrasquera y parqueo para 2 vehículos.',
            'categoria' => 'Casa', 'tipo' => 'Venta', 'precio' => 148000,
            'superficie' => 260, 'construida' => 190, 'hab' => 3, 'banos' => 3, 'antiguedad' => 3,
            'zona' => 'Equipetrol', 'direccion' => 'Calle 4 Este (referencial)', 'lat' => -17.7595, 'lng' => -63.1955,
            'imagen' => 'p4',
        ]);

        $deptoDiego = $this->propiedad($diego, [
            'titulo' => 'Departamento de 2 dormitorios en alquiler cerca del 3.er anillo',
            'descripcion' => 'Departamento luminoso con balcón, cocina equipada y seguridad.',
            'categoria' => 'Departamento', 'tipo' => 'Alquiler', 'precio' => 550,
            'superficie' => 80, 'construida' => 80, 'hab' => 2, 'banos' => 2, 'antiguedad' => 4,
            'zona' => 'Hamacas', 'direccion' => 'Av. Roca y Coronado (referencial)', 'lat' => -17.7745, 'lng' => -63.2010,
            'imagen' => 'p5',
        ]);

        // Una propiedad disponible de María Fernanda (la del Ciclo 1), para cruzar agentes.
        $deMaria = $maria
            ? Propiedad::disponibles()->where('idusuario', $maria->idusuario)->orderBy('idpropiedad')->first()
            : null;

        // [nombre, correo, teléfono, tipo, seguimiento, agente, propiedades de interés]
        $prospectos = [
            ['Marcelo Antelo', 'marcelo.antelo@correo.com', '70411223', 'Comprador', Cliente::CONTACTADO, $carla, [$casaCarla]],
            ['Rosa Ibáñez', 'rosa.ibanez@correo.com', '70522334', 'Inquilino', Cliente::NUEVO, $diego, [$deptoDiego]],
            ['Jorge Peña', 'jorge.pena@correo.com', '70633445', 'Inversionista', Cliente::EN_NEGOCIACION, $maria, [$deMaria]],
            ['Valeria Oroza', null, '70744556', 'Comprador', Cliente::NUEVO, $carla, [$casaCarla, $deMaria]],
            ['Hugo Salvatierra', 'hugo.salva@correo.com', null, 'Comprador', Cliente::NUEVO, null, [$deptoDiego]],
        ];

        foreach ($prospectos as [$nombre, $correo, $telefono, $tipo, $estado, $agente, $propiedades]) {
            $cliente = Cliente::firstOrCreate(
                ['nombre' => $nombre],
                [
                    'email' => $correo,
                    'telefono' => $telefono,
                    'tipocliente' => $tipo,
                    'estadoseguimiento' => $estado,
                    'idusuario' => $agente?->idusuario,
                ]
            );

            foreach (array_filter($propiedades) as $propiedad) {
                Interes::firstOrCreate(
                    ['idpropiedad' => $propiedad->idpropiedad, 'idcliente' => $cliente->idcliente],
                    ['mensaje' => 'Prospecto de prueba del Ciclo 2.', 'fecha' => now()->toDateString()]
                );
            }
        }

        $this->command?->info('Agentes de prueba: csuarez@inmobiliaria.com y dvaca@inmobiliaria.com (clave Lit2026*).');
    }

    private function agente(string $nombre, string $correo, string $telefono, int $idRol): Usuario
    {
        // El modelo guarda la contraseña con hash; si el usuario ya existe no se le cambia nada.
        return Usuario::firstOrCreate(
            ['email' => $correo],
            ['nombre' => $nombre, 'telefono' => $telefono, 'password' => 'Lit2026*', 'estado' => Usuario::ACTIVO, 'idrol' => $idRol]
        );
    }

    private function propiedad(Usuario $agente, array $d): Propiedad
    {
        $existente = Propiedad::where('titulo', $d['titulo'])->first();
        if ($existente) {
            return $existente;
        }

        if (! LimiteCuartoAnillo::contiene($d['lat'], $d['lng'])) {
            throw new \RuntimeException("«{$d['titulo']}» está fuera del 4.º anillo.");
        }

        $ubicacion = Ubicacion::create([
            'ciudad' => config('santacruz.ciudad'),
            'zona' => $d['zona'],
            'direccion' => $d['direccion'],
            'latitud' => $d['lat'],
            'longitud' => $d['lng'],
        ]);

        $propiedad = Propiedad::create([
            'idpropietario' => (Propietario::first() ?? Propietario::create(['nombre' => 'Propietario de ejemplo']))->idpropietario,
            'idcategoria' => (Categoria::where('nombre', $d['categoria'])->first() ?? Categoria::first())->idcategoria,
            'idubicacion' => $ubicacion->idubicacion,
            'idusuario' => $agente->idusuario,
            'titulo' => $d['titulo'],
            'descripcion' => $d['descripcion'],
            'precio' => $d['precio'],
            'tipopropiedad' => $d['tipo'],
            'estadopropiedad' => Propiedad::DISPONIBLE,
            'superficie' => $d['superficie'],
            'areaconstruida' => $d['construida'],
            'habitaciones' => $d['hab'],
            'banos' => $d['banos'],
            'antiguedad' => $d['antiguedad'],
        ]);

        $this->copiarImagen($propiedad, $d['imagen']);

        Historial::create([
            'tipo' => Historial::REGISTRO,
            'valoranterior' => null,
            'valoractual' => json_encode(HistorialService::instantanea($propiedad->fresh('ubicacion')), JSON_UNESCAPED_UNICODE),
            'motivo' => 'Carga de datos de prueba del Ciclo 2',
            'idusuario' => $agente->idusuario,
            'idpropiedad' => $propiedad->idpropiedad,
        ]);

        return $propiedad;
    }

    /** Copia la foto de ejemplo a public/uploads/propiedades/<id> y la registra como portada. */
    private function copiarImagen(Propiedad $propiedad, string $archivo): void
    {
        $origen = database_path("seeders/imagenes/{$archivo}.jpg");
        if (! File::exists($origen)) {
            return;
        }

        $carpeta = 'uploads/propiedades/' . $propiedad->idpropiedad;
        File::ensureDirectoryExists(public_path($carpeta));
        $nombre = Str::uuid() . '.jpg';
        File::copy($origen, public_path($carpeta . '/' . $nombre));

        Imagen::create([
            'idpropiedad' => $propiedad->idpropiedad,
            'nombre' => basename($origen),
            'ruta' => $carpeta . '/' . $nombre,
            'portada' => true,
        ]);
    }
}
