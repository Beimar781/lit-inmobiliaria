<?php

namespace App\Modules\AgendaVisitasPagos\Compartido;

use App\Models\Contrato;
use App\Models\Pago;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Cronograma de cuotas mensuales de un contrato de ALQUILER. Es un cálculo: no se guarda nada en la base de datos.
 *  - Solo hay cronograma para contratos de Alquiler con fecha de inicio y de fin (Venta y Anticrético no tienen).
 *  - Cuotas = meses completos entre el inicio y el fin. La cuota 1 vence el día de inicio y las demás el mismo día de cada mes
 *    (en meses cortos, el último día del mes).
 *  - Monto de la cuota = monto del contrato / cuotas, a 2 decimales; la última absorbe la diferencia del redondeo.
 *  - Los pagos PAGADOS (equivalente en USD; los pendientes y anulados no cuentan, igual que el saldo) se reparten en orden,
 *    de la cuota 1 hacia adelante. Lo pagado en el cronograma suma siempre monto - saldo del contrato.
 *
 * Cada cuota es un arreglo: numero, vencimiento (CarbonImmutable), monto, pagado, falta (USD) y estado.
 */
class CronogramaDeAlquiler
{
    public const PAGADA = 'PAGADA';
    public const PARCIAL = 'PARCIAL';
    public const PENDIENTE = 'PENDIENTE';
    public const VENCIDA = 'VENCIDA';

    public const ESTADOS = [
        self::PAGADA => 'Pagada',
        self::PARCIAL => 'Parcial',
        self::PENDIENTE => 'Pendiente',
        self::VENCIDA => 'Vencida',
    ];

    /** Tope de seguridad por si las fechas del contrato fueran absurdas. */
    private const MAX_CUOTAS = 600;

    /** ¿El contrato tiene cronograma? Solo el alquiler con fechas de inicio y fin. */
    public static function aplica(Contrato $contrato): bool
    {
        return $contrato->tipocontrato === 'Alquiler' && $contrato->fechainicio !== null && $contrato->fechafin !== null;
    }

    /**
     * @return array<int, array{numero: int, vencimiento: CarbonImmutable, monto: float, pagado: float, falta: float, estado: string}>
     *         vacío si el contrato no tiene cronograma
     */
    public static function cuotas(Contrato $contrato, ?CarbonInterface $hoy = null): array
    {
        if (! self::aplica($contrato)) {
            return [];
        }

        $inicio = CarbonImmutable::parse($contrato->fechainicio->format('Y-m-d'))->startOfDay();
        $fin = CarbonImmutable::parse($contrato->fechafin->format('Y-m-d'))->startOfDay();

        // Meses completos entre el inicio y el fin.
        $total = 0;
        while ($total < self::MAX_CUOTAS && $inicio->addMonthsNoOverflow($total + 1) <= $fin) {
            $total++;
        }

        if ($total === 0) {
            return [];
        }

        $hoy = ($hoy ?? AgendaDeVisitas::ahora())->format('Y-m-d');
        $marcarVencidas = $contrato->estado === Contrato::VIGENTE;

        // Todo en centavos, para que la suma sea exacta.
        $montoCentavos = (int) round(((float) $contrato->monto) * 100);
        $cuotaCentavos = (int) round($montoCentavos / $total);

        $pagos = $contrato->relationLoaded('pagos') ? $contrato->pagos : $contrato->pagos()->get();
        $porAplicar = (int) round(((float) $pagos->where('estado', Pago::PAGADO)->sum('montousd')) * 100);

        $cuotas = [];
        for ($i = 0; $i < $total; $i++) {
            $monto = $i === $total - 1 ? $montoCentavos - $cuotaCentavos * ($total - 1) : $cuotaCentavos;
            $pagado = max(0, min($monto, $porAplicar));
            $porAplicar -= $pagado;
            $vencimiento = $inicio->addMonthsNoOverflow($i);

            $estado = match (true) {
                $pagado >= $monto => self::PAGADA,
                $marcarVencidas && $vencimiento->format('Y-m-d') < $hoy => self::VENCIDA,
                $pagado > 0 => self::PARCIAL,
                default => self::PENDIENTE,
            };

            $cuotas[] = [
                'numero' => $i + 1,
                'vencimiento' => $vencimiento,
                'monto' => $monto / 100.0,
                'pagado' => $pagado / 100.0,
                'falta' => ($monto - $pagado) / 100.0,
                'estado' => $estado,
            ];
        }

        return $cuotas;
    }

    /** La próxima cuota a pagar: la primera que no está pagada (null si todas lo están o no hay cronograma). */
    public static function proxima(array $cuotas): ?array
    {
        foreach ($cuotas as $cuota) {
            if ($cuota['estado'] !== self::PAGADA) {
                return $cuota;
            }
        }

        return null;
    }
}
