<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Historial;
use App\Models\Imagen;
use App\Models\Propiedad;
use App\Models\Propietario;
use App\Models\Ubicacion;
use App\Models\Usuario;
use App\Modules\Propiedades\Compartido\LimiteCuartoAnillo;
use App\Services\HistorialService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/*
 * Propiedades de EJEMPLO para la presentación.
 * Todas están dentro del 4.º anillo de Santa Cruz de la Sierra (el seeder lo comprueba con la
 * misma regla que usa el sistema). Las coordenadas son referenciales y las imágenes son
 * ilustraciones de ejemplo: se pueden cambiar desde "Modificar propiedad".
 *
 * Se puede ejecutar varias veces: no repite las propiedades que ya existen.
 *   php artisan db:seed --class=PropiedadSeeder
 * (Si ya existen, vuelve a cargar el precio en USD y las imágenes de database/seeders/imagenes.)
 */
class PropiedadSeeder extends Seeder
{
    public function run(): void
    {
        $agente = Usuario::where('email', 'mtorrez@inmobiliaria.com')->first() ?? Usuario::first();

        $datos = [
            [
                'titulo' => 'Departamento amoblado de 2 dormitorios en Equipetrol',
                'descripcion' => 'Departamento con suite, balcón, cocina equipada y parqueo. Edificio con seguridad las 24 horas.',
                'categoria' => 'Departamento', 'tipo' => 'Venta', 'precio' => 85000, 'estado' => 'DISPONIBLE',
                'superficie' => 85.5, 'construida' => 85.5, 'hab' => 2, 'banos' => 2, 'antiguedad' => 2,
                'propietario' => 'Luis Alberto Parada',
                'zona' => 'Equipetrol', 'direccion' => 'Av. San Martín (referencial)', 'lat' => -17.7630, 'lng' => -63.1980,
                'imagenes' => ['p1a', 'p1b'],
            ],
            [
                'titulo' => 'Casa familiar de 4 dormitorios en Urbarí',
                'descripcion' => 'Casa espaciosa con churrasquera, patio amplio y piscina privada.',
                'categoria' => 'Casa', 'tipo' => 'Venta', 'precio' => 210000, 'estado' => 'DISPONIBLE',
                'superficie' => 450, 'construida' => 320, 'hab' => 4, 'banos' => 4, 'antiguedad' => 10,
                'propietario' => 'Patricia Soliz',
                'zona' => 'Urbarí', 'direccion' => 'Barrio Urbarí (referencial)', 'lat' => -17.7545, 'lng' => -63.1650,
                'imagenes' => ['p2a', 'p2b'],
            ],
            [
                'titulo' => 'Oficina empresarial en Sirari',
                'descripcion' => 'Oficina moderna con divisiones de vidrio, cocineta y aire acondicionado central.',
                'categoria' => 'Oficina', 'tipo' => 'Alquiler', 'precio' => 650, 'estado' => 'ALQUILADO',
                'superficie' => 110, 'construida' => 110, 'hab' => 0, 'banos' => 2, 'antiguedad' => 1,
                'propietario' => 'Luis Alberto Parada',
                'zona' => 'Sirari', 'direccion' => 'Zona Sirari (referencial)', 'lat' => -17.7680, 'lng' => -63.1860,
                'imagenes' => ['p3'],
            ],
            [
                'titulo' => 'Terreno en condominio, Zona Norte',
                'descripcion' => 'Lote totalmente plano, ubicado cerca de la avenida principal.',
                'categoria' => 'Terreno', 'tipo' => 'Venta', 'precio' => 55000, 'estado' => 'RESERVADO',
                'superficie' => 360, 'construida' => 0, 'hab' => 0, 'banos' => 0, 'antiguedad' => 0,
                'propietario' => 'Patricia Soliz',
                'zona' => 'Zona Norte', 'direccion' => 'Zona Norte (referencial)', 'lat' => -17.7480, 'lng' => -63.1830,
                'imagenes' => ['p4'],
            ],
            [
                'titulo' => 'Local comercial en Las Palmas',
                'descripcion' => 'Excelente vitrina comercial a la calle, en zona de alto tráfico.',
                'categoria' => 'Local Comercial', 'tipo' => 'Alquiler', 'precio' => 450, 'estado' => 'DISPONIBLE',
                'superficie' => 75, 'construida' => 75, 'hab' => 0, 'banos' => 1, 'antiguedad' => 5,
                'propietario' => 'Luis Alberto Parada',
                'zona' => 'Las Palmas', 'direccion' => 'Av. Grigotá (referencial)', 'lat' => -17.8040, 'lng' => -63.1760,
                'imagenes' => ['p5'],
            ],
            [
                'titulo' => 'Departamento de 3 dormitorios cerca de la Plaza Principal',
                'descripcion' => 'Departamento luminoso a pocas cuadras de la plaza 24 de Septiembre, con ascensor.',
                'categoria' => 'Departamento', 'tipo' => 'Venta', 'precio' => 120000, 'estado' => 'VENDIDO',
                'superficie' => 130, 'construida' => 130, 'hab' => 3, 'banos' => 2, 'antiguedad' => 6,
                'propietario' => 'Patricia Soliz',
                'zona' => 'Centro', 'direccion' => 'Casco viejo (referencial)', 'lat' => -17.7850, 'lng' => -63.1790,
                'imagenes' => ['p6'],
            ],
            [
                'titulo' => 'Casa con jardín en zona Av. Beni',
                'descripcion' => 'Casa de una planta con jardín, garaje para dos autos y cuarto de servicio.',
                'categoria' => 'Casa', 'tipo' => 'Venta', 'precio' => 175000, 'estado' => 'DISPONIBLE',
                'superficie' => 300, 'construida' => 210, 'hab' => 3, 'banos' => 3, 'antiguedad' => 8,
                'propietario' => 'Luis Alberto Parada',
                'zona' => 'Av. Beni', 'direccion' => 'Zona Av. Beni (referencial)', 'lat' => -17.7680, 'lng' => -63.1760,
                'imagenes' => ['p7'],
            ],
            [
                'titulo' => 'Departamento en anticrético, Av. Cañoto',
                'descripcion' => 'Departamento de 2 dormitorios, ideal para familia pequeña. Cerca de transporte y comercios.',
                'categoria' => 'Departamento', 'tipo' => 'Anticrético', 'precio' => 35000, 'estado' => 'DISPONIBLE',
                'superficie' => 90, 'construida' => 90, 'hab' => 2, 'banos' => 1, 'antiguedad' => 12,
                'propietario' => 'Patricia Soliz',
                'zona' => 'Centro - Cañoto', 'direccion' => 'Av. Cañoto (referencial)', 'lat' => -17.7880, 'lng' => -63.1760,
                'imagenes' => ['p8'],
            ],
        ];

        foreach ($datos as $d) {
            $existente = Propiedad::where('titulo', $d['titulo'])->first();
            if ($existente) {
                // Ya existe: solo actualiza el precio (USD) y vuelve a cargar las imágenes de la carpeta.
                $existente->update(['precio' => $d['precio']]);
                $this->copiarImagenes($existente, $d['imagenes'], true);
                continue;
            }

            // Seguridad: ningún dato de ejemplo puede quedar fuera del alcance del proyecto.
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
                'idpropietario' => Propietario::where('nombre', $d['propietario'])->value('idpropietario'),
                'idcategoria' => Categoria::where('nombre', $d['categoria'])->value('idcategoria'),
                'idubicacion' => $ubicacion->idubicacion,
                'idusuario' => $agente?->idusuario,
                'titulo' => $d['titulo'],
                'descripcion' => $d['descripcion'],
                'precio' => $d['precio'],
                'tipopropiedad' => $d['tipo'],
                'estadopropiedad' => $d['estado'],
                'superficie' => $d['superficie'],
                'areaconstruida' => $d['construida'],
                'habitaciones' => $d['hab'],
                'banos' => $d['banos'],
                'antiguedad' => $d['antiguedad'],
            ]);

            $this->copiarImagenes($propiedad, $d['imagenes']);

            Historial::create([
                'tipo' => Historial::REGISTRO,
                'valoranterior' => null,
                'valoractual' => json_encode(HistorialService::instantanea($propiedad->fresh('ubicacion')), JSON_UNESCAPED_UNICODE),
                'motivo' => 'Carga de datos de ejemplo',
                'idusuario' => $agente?->idusuario,
                'idpropiedad' => $propiedad->idpropiedad,
            ]);
        }
    }

    /** Copia las imágenes de ejemplo a public/uploads/propiedades/<id> y las registra en la tabla IMAGEN. */
    private function copiarImagenes(Propiedad $propiedad, array $archivos, bool $reemplazar = false): void
    {
        $carpeta = 'uploads/propiedades/' . $propiedad->idpropiedad;
        File::ensureDirectoryExists(public_path($carpeta));

        if ($reemplazar) {
            foreach (Imagen::where('idpropiedad', $propiedad->idpropiedad)->get() as $img) {
                File::delete(public_path($img->ruta));
                $img->delete();
            }
        }

        foreach ($archivos as $posicion => $archivo) {
            // Acepta la foto en jpg, jpeg, png o webp (el nombre base es el de la tabla del README).
            $origen = collect(['jpg', 'jpeg', 'png', 'webp'])
                ->map(fn ($ext) => database_path("seeders/imagenes/{$archivo}.{$ext}"))
                ->first(fn ($ruta) => File::exists($ruta));

            if (! $origen) {
                continue;
            }

            $ext = pathinfo($origen, PATHINFO_EXTENSION);
            $nombre = Str::uuid() . '.' . $ext;
            File::copy($origen, public_path($carpeta . '/' . $nombre));

            Imagen::create([
                'idpropiedad' => $propiedad->idpropiedad,
                'nombre' => basename($origen),
                'ruta' => $carpeta . '/' . $nombre,
                'portada' => $posicion === 0, // la primera imagen es la portada
            ]);
        }
    }
}
