<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Propietario;
use Illuminate\Database\Seeder;

/*
 * Datos base para poder registrar propiedades (CU5).
 * Las ubicaciones NO se siembran: se crean al registrar cada propiedad, eligiendo
 * el punto en el mapa (debe estar dentro del 4.º anillo de Santa Cruz de la Sierra).
 */
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
    }
}
