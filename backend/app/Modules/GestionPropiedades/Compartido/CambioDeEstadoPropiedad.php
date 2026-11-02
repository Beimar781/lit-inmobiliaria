<?php

namespace App\Modules\GestionPropiedades\Compartido;

use App\Models\Historial;
use App\Models\Propiedad;
use App\Services\BitacoraService;
use App\Services\HistorialService;
use Illuminate\Support\Facades\DB;

/**
 * Cambio de estado de una propiedad (CU8). Lo usan el propio CU8 y los casos de uso que lo "incluyen":
 * CU26 (reservas: Reservado / Disponible) y CU27 (contratos: Vendido / Alquilado / Disponible).
 *
 * El cambio y su historial se guardan juntos, o ninguno. Se puede llamar dentro de otra transacción
 * (la reserva o el contrato y el cambio de estado se guardan también todos juntos).
 */
class CambioDeEstadoPropiedad
{
    /**
     * Cambia el estado de la propiedad y lo registra en el historial. La fila se bloquea mientras tanto para que dos
     * cambios casi simultáneos no dejen el historial con un "estado anterior" equivocado.
     * Una propiedad dada de baja no cambia de estado (404).
     *
     * @return string el estado anterior
     */
    public function cambiar(Propiedad $propiedad, string $nuevo, ?string $motivo = null): string
    {
        return DB::transaction(function () use ($propiedad, $nuevo, $motivo) {
            $actual = Propiedad::lockForUpdate()->findOrFail($propiedad->idpropiedad);
            abort_if($actual->estadopropiedad === Propiedad::BAJA, 404);

            $anterior = $actual->estadopropiedad;
            $actual->update(['estadopropiedad' => $nuevo]);
            $propiedad->estadopropiedad = $nuevo;

            HistorialService::registrar(
                Historial::CAMBIO_ESTADO,
                $propiedad->idpropiedad,
                ['estado' => $anterior],
                ['estado' => $nuevo],
                $motivo
            );

            return $anterior;
        });
    }

    /**
     * La bitácora va fuera de la transacción: si fallara, no debe deshacer el cambio ya hecho.
     * $origen explica por qué cambió (por ejemplo, la reserva o el contrato que lo provocó).
     */
    public function registrarEnBitacora(Propiedad $propiedad, string $anterior, string $nuevo, ?string $motivo = null, array $origen = []): void
    {
        BitacoraService::registrar('PROPIEDAD_ESTADO', 'Propiedades', "Cambió el estado de la propiedad «{$propiedad->titulo}» (ID {$propiedad->idpropiedad}) de {$anterior} a {$nuevo}", array_merge([
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'motivo' => $motivo,
        ], $origen));
    }
}
