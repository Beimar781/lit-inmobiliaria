<?php

namespace App\Modules\AgendaVisitasPagos\Compartido;

use App\Models\MovimientoReserva;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\Usuario;

/**
 * El efectivo de una reserva (CU26):
 *  - al reservar, el cliente paga en dólares (USD) o bolivianos (BOB, con el tipo de cambio oficial del día escrito a mano);
 *  - si no formaliza la compra (la reserva vence o se cancela) se le devuelve, en efectivo y en la misma moneda, todo menos
 *    el porcentaje que se queda la empresa (config/reservas.php, 20 % por defecto);
 *  - si formaliza, la reserva se descuenta del contrato (no se devuelve nada).
 * Cada reserva tiene como máximo un cobro y una devolución. Los métodos se llaman dentro de una transacción.
 */
class DineroDeReservas
{
    public function __construct(private NumeradorDeComprobantes $numerador)
    {
    }

    /** Equivalente en dólares, redondeado a 2 decimales: en BOB es monto / tipo de cambio. */
    public static function aUsd(float|string $monto, string $moneda, float|string|null $tipoCambio): float
    {
        return $moneda === Pago::BOB && (float) $tipoCambio > 0
            ? round((float) $monto / (float) $tipoCambio, 2)
            : round((float) $monto, 2);
    }

    /** Lo mínimo que se debe pagar para reservar, en dólares (porcentaje del precio de la propiedad). */
    public static function minimoUsd(float|string $precio): float
    {
        return round((float) $precio * (float) config('reservas.minimo') / 100, 2);
    }

    public function cobrar(Reserva $reserva, float|string $monto, string $moneda, float|string|null $tipoCambio, ?Usuario $actor): MovimientoReserva
    {
        return MovimientoReserva::create([
            'idreserva' => $reserva->idreserva,
            'tipo' => MovimientoReserva::COBRO,
            'monto' => $monto,
            'moneda' => $moneda,
            'tipocambio' => $moneda === Pago::BOB ? $tipoCambio : null,
            'montousd' => self::aUsd($monto, $moneda, $tipoCambio),
            'comprobante' => $this->numerador->siguiente(MovimientoReserva::PREFIJOS[MovimientoReserva::COBRO], 'movimientoreserva'),
            'fecha' => AgendaDeVisitas::ahora()->toDateTimeString(),
            'idusuario' => $actor?->idusuario,
        ]);
    }

    /**
     * Cuánto se devuelve y cuánto se retiene. La devolución es en la misma moneda que el cobro.
     *
     * @return array{monto: float, montousd: float, retenido_usd: float, porcentaje: float, moneda: string}|null null si no hay cobro
     */
    public function calcularDevolucion(Reserva $reserva): ?array
    {
        $cobro = $reserva->cobro;

        if (! $cobro) {
            return null;
        }

        $porcentaje = (float) config('reservas.retencion');
        $factor = (100 - $porcentaje) / 100;
        $montoUsd = round((float) $cobro->montousd * $factor, 2);

        return [
            'monto' => round((float) $cobro->monto * $factor, 2),
            'montousd' => $montoUsd,
            'retenido_usd' => round((float) $cobro->montousd - $montoUsd, 2),
            'porcentaje' => $porcentaje,
            'moneda' => $cobro->moneda,
        ];
    }

    public function devolver(Reserva $reserva, ?Usuario $actor): MovimientoReserva
    {
        $cobro = $reserva->cobro;
        $calculo = $this->calcularDevolucion($reserva);

        return MovimientoReserva::create([
            'idreserva' => $reserva->idreserva,
            'tipo' => MovimientoReserva::DEVOLUCION,
            'monto' => $calculo['monto'],
            'moneda' => $calculo['moneda'],
            'tipocambio' => $cobro->tipocambio,
            'montousd' => $calculo['montousd'],
            'porcentajeretencion' => $calculo['porcentaje'],
            'comprobante' => $this->numerador->siguiente(MovimientoReserva::PREFIJOS[MovimientoReserva::DEVOLUCION], 'movimientoreserva'),
            'fecha' => AgendaDeVisitas::ahora()->toDateTimeString(),
            'idusuario' => $actor?->idusuario,
        ]);
    }

    /** El último tipo de cambio usado en una reserva en bolivianos, para sugerirlo (la persona debe confirmarlo). */
    public static function ultimoTipoCambio(): ?string
    {
        $valor = MovimientoReserva::whereNotNull('tipocambio')->orderByDesc('idmovimiento')->value('tipocambio');

        return $valor !== null ? rtrim(rtrim(number_format((float) $valor, 4, '.', ''), '0'), '.') : null;
    }
}
