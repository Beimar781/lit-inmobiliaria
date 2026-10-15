<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Interes;
use App\Models\Propiedad;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/*
 * Prospectos de EJEMPLO del CRM (CU12 y CU13), para la presentación.
 * Se ejecuta después de PropiedadSeeder y se puede repetir: no duplica los prospectos.
 *   php artisan db:seed --class=ClienteSeeder
 */
class ClienteSeeder extends Seeder
{
    public function run(): void
    {
        $agente = Usuario::where('email', 'mtorrez@inmobiliaria.com')->first();
        $propiedades = Propiedad::disponibles()->orderBy('idpropiedad')->take(3)->get();

        if ($propiedades->isEmpty()) {
            $this->command?->warn('No hay propiedades disponibles: se omitieron los prospectos de ejemplo.');

            return;
        }

        $prospectos = [
            ['Lucía Méndez', 'lucia.mendez@gmail.com', '76543210', 'Comprador', Cliente::NUEVO, true, 0, 'Quisiera visitarla este fin de semana.'],
            ['Pedro Soliz', 'psoliz@hotmail.com', '71112222', 'Inversionista', Cliente::CONTACTADO, true, 1, 'Me interesa conocer la rentabilidad.'],
            ['Ana Rojas', null, '70033445', 'Inquilino', Cliente::EN_NEGOCIACION, true, 2, null],
            ['Gabriel Vaca', 'gvaca@correo.com', null, 'Comprador', Cliente::NUEVO, false, 0, 'Escribió desde la página y aún no tiene agente.'],
        ];

        foreach ($prospectos as [$nombre, $email, $telefono, $tipo, $estado, $conAgente, $posicion, $mensaje]) {
            $cliente = Cliente::firstOrCreate(
                ['nombre' => $nombre],
                [
                    'email' => $email,
                    'telefono' => $telefono,
                    'tipocliente' => $tipo,
                    'estadoseguimiento' => $estado,
                    'idusuario' => $conAgente ? $agente?->idusuario : null,
                ]
            );

            $propiedad = $propiedades[$posicion % $propiedades->count()];

            Interes::firstOrCreate(
                ['idpropiedad' => $propiedad->idpropiedad, 'idcliente' => $cliente->idcliente],
                ['mensaje' => $mensaje, 'fecha' => now()->toDateString()]
            );
        }
    }
}
