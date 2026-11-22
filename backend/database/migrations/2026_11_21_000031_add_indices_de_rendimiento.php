<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Índices de rendimiento (Ciclo 4, auditoría F-015): columnas de llaves foráneas y de filtros de los reportes que no tenían índice
 * (PostgreSQL no indexa solas las llaves foráneas). Es aditiva e idempotente: si la tabla, la columna o el índice ya existen, no hace nada.
 */
return new class extends Migration
{
    /** tabla => lista de índices (cada uno, una lista de columnas). */
    private const INDICES = [
        'imagen' => [['idpropiedad']],
        'historial' => [['idpropiedad', 'fecha'], ['idusuario']],
        'resena' => [['idpropiedad']],
        'cliente' => [['idusuario'], ['fecharegistro']],
        'interes' => [['idcliente']],
        'propiedad' => [['idpropietario'], ['idubicacion'], ['fecharegistro']],
        'contrato' => [['idusuario'], ['idreserva'], ['fechainicio']],
        'visita' => [['fecha']],
        'seguimiento' => [['idusuario'], ['idpropiedad']],
        'usuario' => [['idrol']],
        'bitacora' => [['idusuario']],
        'reserva' => [['idusuario']],
        'pago' => [['idusuario']],
        'movimientoreserva' => [['idusuario']],
    ];

    public function up(): void
    {
        foreach (self::INDICES as $tabla => $indices) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            foreach ($indices as $columnas) {
                if (! Schema::hasColumns($tabla, $columnas) || Schema::hasIndex($tabla, $columnas)) {
                    continue;
                }

                Schema::table($tabla, function (Blueprint $table) use ($tabla, $columnas) {
                    $table->index($columnas, self::nombre($tabla, $columnas));
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDICES as $tabla => $indices) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            foreach ($indices as $columnas) {
                $nombre = self::nombre($tabla, $columnas);

                // Solo se borran los índices creados por esta migración (por su nombre).
                if (Schema::hasIndex($tabla, $nombre)) {
                    Schema::table($tabla, fn (Blueprint $table) => $table->dropIndex($nombre));
                }
            }
        }
    }

    private static function nombre(string $tabla, array $columnas): string
    {
        return 'idx_' . $tabla . '_' . implode('_', $columnas);
    }
};
