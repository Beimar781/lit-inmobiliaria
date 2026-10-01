<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Propietario;
use App\Models\Ubicacion;
use Illuminate\Database\Seeder;

/* Datos base para poder registrar propiedades (CU5). */
class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            ['Departamento', 'Unidades habitacionales en edificios'],
            ['Casa', 'Viviendas independientes'],
            ['Oficina', 'Espacios corporativos'],
            ['Terreno', 'Lotes y terrenos'],
            ['Local Comercial', 'Espacios para tiendas o negocios'],
        ];
        foreach ($categorias as [$nombre, $descripcion]) {
            Categoria::firstOrCreate(['nombre' => $nombre], ['descripcion' => $descripcion]);
        }

        $propietarios = [
            ['Luis Alberto Parada', '72345678', 'lparada@correo.com', 'Av. San Martín #120'],
            ['Patricia Soliz', '73456789', 'psoliz@correo.com', 'Calle Los Pinos #45'],
        ];
        foreach ($propietarios as [$nombre, $telefono, $email, $direccion]) {
            Propietario::firstOrCreate(
                ['nombre' => $nombre],
                ['telefono' => $telefono, 'email' => $email, 'direccion' => $direccion]
            );
        }

        $ubicaciones = [
            ['Santa Cruz', 'Equipetrol', 'Av. San Martín', -17.7630000, -63.1980000],
            ['Santa Cruz', 'Urubó', 'Calle 5, Urubó', -17.7770000, -63.2370000],
            ['Santa Cruz', 'Sirari', 'Av. Busch', -17.7680000, -63.1850000],
            ['Santa Cruz', 'Las Palmas', 'Av. Grigotá', -17.8040000, -63.1760000],
        ];
        foreach ($ubicaciones as [$ciudad, $zona, $direccion, $lat, $lng]) {
            Ubicacion::firstOrCreate(
                ['zona' => $zona],
                ['ciudad' => $ciudad, 'direccion' => $direccion, 'latitud' => $lat, 'longitud' => $lng]
            );
        }
    }
}
