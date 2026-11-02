<?php

namespace App\Modules\AgendaVisitasPagos\Compartido;

use App\Models\Propiedad;
use App\Models\Reserva;
use App\Modules\GestionPropiedades\Compartido\CambioDeEstadoPropiedad;
use App\Services\BitacoraService;
use Illuminate\Support\Facades\DB;

/**
 * Las reservas cuyo plazo ya pasó se marcan VENCIDAS y la propiedad vuelve a DISPONIBLE (con su historial).
 * El plazo vale hasta el final del día indicado: una reserva con plazo "hoy" todavía está vigente.
 *
 * Se aplica "al consultarlas o al intentar usarlas": CU26 (listar, reservar, cancelar) y CU27 (contratar).
 */
class VencimientoDeReservas
{
    public function __construct(private CambioDeEstadoPropiedad $cambio)
    {
    }

    /** @return int cuántas reservas se marcaron como vencidas */
    public function vencer(): int
    {
        $hoy = AgendaDeVisitas::ahora()->toDateString();
        $vencidas = Reserva::vigentes()->where('fechaplazo', '<', $hoy)->pluck('idreserva');
        $total = 0;

        foreach ($vencidas as $id) {
            $resultado = DB::transaction(function () use ($id) {
                $reserva = Reserva::lockForUpdate()->find($id);

                if (! $reserva || $reserva->estado !== Reserva::VIGENTE) {
                    return null; // otro proceso ya la resolvió
                }

                $reserva->update(['estado' => Reserva::VENCIDA]);

                // La propiedad vuelve a estar disponible, salvo que ya haya cambiado de estado por otro camino.
                $propiedad = Propiedad::find($reserva->idpropiedad);
                $anterior = null;
                if ($propiedad && $propiedad->estadopropiedad === Propiedad::RESERVADO) {
                    $anterior = $this->cambio->cambiar($propiedad, Propiedad::DISPONIBLE, "Venció el plazo de la reserva #{$reserva->idreserva}");
                }

                return [$reserva, $propiedad, $anterior];
            });

            if ($resultado === null) {
                continue;
            }

            [$reserva, $propiedad, $anterior] = $resultado;
            $total++;

            BitacoraService::registrar('RESERVA_VENCIDA', 'Reservas', "Venció la reserva #{$reserva->idreserva} de «{$propiedad?->titulo}»", [
                'idreserva' => $reserva->idreserva,
                'idpropiedad' => $reserva->idpropiedad,
                'idcliente' => $reserva->idcliente,
                'fechaplazo' => $reserva->fechaplazo->format('Y-m-d'),
            ]);

            if ($anterior !== null) {
                $this->cambio->registrarEnBitacora($propiedad, $anterior, Propiedad::DISPONIBLE, "Venció el plazo de la reserva #{$reserva->idreserva}", ['idreserva' => $reserva->idreserva]);
            }
        }

        return $total;
    }
}
