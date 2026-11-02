<?php

namespace App\Modules\AgendaVisitasPagos\Compartido;

use App\Models\Pago;
use App\Services\BitacoraService;
use Illuminate\Support\Facades\DB;

/**
 * Comprobante del pago formal de un contrato (CU30): número único y correlativo CP-AAAA-000001.
 * Lo usan el CU28 (al registrar un pago), el CU30 (al mostrarlo o reimprimirlo) y, más adelante, el pago con tarjeta.
 * Los recibos de la reserva (RV y DV) son otros documentos y no pasan por aquí.
 */
class ComprobanteDePago
{
    public function __construct(private NumeradorDeComprobantes $numerador)
    {
    }

    /** ¿Este pago puede tener comprobante? Solo los pagos PAGADOS; los pendientes y los anulados no. */
    public static function disponible(?Pago $pago): bool
    {
        return $pago !== null && $pago->estado === Pago::PAGADO;
    }

    /**
     * Asigna el número de comprobante si el pago todavía no lo tiene. Si ya lo tenía, lo conserva (reimpresión).
     * La asignación va dentro de una transacción con bloqueo, para que dos pagos nunca repitan número.
     *
     * @return Pago el pago, con su comprobante
     */
    public function asegurar(Pago $pago): Pago
    {
        if ($pago->comprobante) {
            return $pago;
        }

        $esNuevo = false;

        $asignado = DB::transaction(function () use ($pago, &$esNuevo) {
            $actual = Pago::lockForUpdate()->findOrFail($pago->idpago);

            if (! $actual->comprobante && self::disponible($actual)) {
                $actual->update(['comprobante' => $this->numerador->siguiente('CP', 'pago')]);
                $esNuevo = true;
            }

            return $actual;
        });

        if ($esNuevo) {
            BitacoraService::registrar('COMPROBANTE_GENERADO', 'Pagos', "Generó el comprobante {$asignado->comprobante} del pago #{$asignado->idpago}", [
                'idpago' => $asignado->idpago,
                'idcontrato' => $asignado->idcontrato,
                'comprobante' => $asignado->comprobante,
            ]);
        }

        return $asignado;
    }

    /** Dólares que le quedaban por pagar al contrato justo después de este pago (los pagos se ordenan por su número). */
    public static function saldoDespuesDelPagoUsd(Pago $pago): float
    {
        $pagado = (float) Pago::where('idcontrato', $pago->idcontrato)
            ->where('estado', Pago::PAGADO)
            ->where('idpago', '<=', $pago->idpago)
            ->sum('montousd');

        return (round(((float) $pago->contrato->monto) * 100) - round($pagado * 100)) / 100;
    }
}
