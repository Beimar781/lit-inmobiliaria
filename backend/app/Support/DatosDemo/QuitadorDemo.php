<?php

namespace App\Support\DatosDemo;

use App\Models\Imagen;
use App\Modules\GestionPropiedades\Compartido\GestorImagenes;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Borra TODO lo ficticio y solo lo ficticio, en el orden que piden las llaves foráneas.
 * Lo demo se reconoce por: los propietarios "DEMO - ...", sus propiedades y los clientes con correo @demo.lit.test.
 * También se borra lo que cuelgue de esas propiedades o de esos clientes (visitas, reservas, contratos, pagos...).
 */
final class QuitadorDemo
{
    /** @return array<string, int> filas borradas por tabla */
    public function quitar(): array
    {
        return DB::transaction(function () {
            $conteo = [];

            $props = DB::table('propiedad')
                ->whereIn('idpropietario', DB::table('propietario')->where('nombre', 'like', MarcasDemo::PREFIJO_PROPIETARIO . '%')->select('idpropietario'))
                ->pluck('idubicacion', 'idpropiedad')->all();
            $idsProps = array_map('intval', array_keys($props));
            $ubicaciones = array_values(array_filter(array_map('intval', $props)));
            $clientes = DB::table('cliente')->where('email', 'like', '%' . MarcasDemo::DOMINIO_CLIENTE)->pluck('idcliente')->map(fn ($id) => (int) $id)->all();

            $deDemo = fn (Builder $q, string $columnaPropiedad = 'idpropiedad', string $columnaCliente = 'idcliente') => $q
                ->whereIn($columnaPropiedad, $idsProps)->orWhereIn($columnaCliente, $clientes);

            $contratos = DB::table('contrato')->where(fn ($q) => $deDemo($q))->pluck('idcontrato')->map(fn ($id) => (int) $id)->all();
            $reservas = DB::table('reserva')->where(fn ($q) => $deDemo($q))->pluck('idreserva')->map(fn ($id) => (int) $id)->all();

            $borrar = function (string $tabla, Builder $consulta) use (&$conteo): void {
                $conteo[$tabla] = ($conteo[$tabla] ?? 0) + $consulta->delete();
            };

            $borrar('tipopago', DB::table('tipopago')->whereIn('idpago', DB::table('pago')->whereIn('idcontrato', $contratos)->select('idpago')));
            $borrar('pago', DB::table('pago')->whereIn('idcontrato', $contratos));
            $borrar('resena', DB::table('resena')->where(fn ($q) => $deDemo($q)));
            $borrar('contrato', DB::table('contrato')->whereIn('idcontrato', $contratos));
            $borrar('movimientoreserva', DB::table('movimientoreserva')->whereIn('idreserva', $reservas));
            $borrar('reserva', DB::table('reserva')->whereIn('idreserva', $reservas));
            $borrar('visita', DB::table('visita')->where(fn ($q) => $deDemo($q)));
            $borrar('seguimiento', DB::table('seguimiento')->whereIn('idcliente', $clientes));
            $borrar('inquilino', DB::table('inquilino')->whereIn('idcliente', $clientes));
            $borrar('interes', DB::table('interes')->where(fn ($q) => $deDemo($q)));
            $borrar('historial', DB::table('historial')->whereIn('idpropiedad', $idsProps));

            // Si alguien subió fotos a una propiedad demo, también se borran los archivos.
            Imagen::whereIn('idpropiedad', $idsProps)->get()->each(function (Imagen $imagen) use (&$conteo) {
                GestorImagenes::eliminar($imagen);
                $conteo['imagen'] = ($conteo['imagen'] ?? 0) + 1;
            });

            $borrar('propiedad', DB::table('propiedad')->whereIn('idpropiedad', $idsProps));
            $borrar('ubicacion', DB::table('ubicacion')->whereIn('idubicacion', $ubicaciones));
            $borrar('cliente', DB::table('cliente')->whereIn('idcliente', $clientes));
            $borrar('propietario', DB::table('propietario')->where('nombre', 'like', MarcasDemo::PREFIJO_PROPIETARIO . '%'));

            return $conteo;
        });
    }
}
