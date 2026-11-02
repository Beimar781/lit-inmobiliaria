<?php

namespace App\Modules\AgendaVisitasPagos\Compartido;

use Illuminate\Support\Facades\DB;

/**
 * Números de comprobante correlativos por año: PREFIJO-AAAA-000001 (por ejemplo RV-2026-000001).
 * Se debe llamar DENTRO de una transacción: en PostgreSQL se bloquea el prefijo hasta que termine, para que dos
 * operaciones al mismo tiempo nunca reciban el mismo número (además la columna del comprobante es única).
 */
class NumeradorDeComprobantes
{
    public function siguiente(string $prefijo, string $tabla, string $columna = 'comprobante'): string
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('select pg_advisory_xact_lock(?)', [crc32("comprobante:{$tabla}:{$prefijo}")]);
        }

        $anio = AgendaDeVisitas::ahora()->year;
        $inicio = "{$prefijo}-{$anio}-";

        $ultimo = DB::table($tabla)
            ->where($columna, 'like', $inicio . '%')
            ->orderByDesc($columna)
            ->value($columna);

        $numero = $ultimo ? ((int) substr($ultimo, strlen($inicio))) + 1 : 1;

        return $inicio . str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }
}
