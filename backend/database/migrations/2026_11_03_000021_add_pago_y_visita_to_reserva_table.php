<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Ciclo 3 - CU26: la reserva se hace después de una visita realizada y se paga en efectivo.
 *  - idvisita: la visita realizada en la que se apoya la reserva.
 *  - montousd: lo que se pagó para reservar, en dólares (el detalle del cobro está en MOVIMIENTORESERVA).
 * Las reservas anteriores (sin pago) quedan con estos campos vacíos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reserva', function (Blueprint $table) {
            $table->unsignedBigInteger('idvisita')->nullable();
            $table->decimal('montousd', 12, 2)->nullable();
            $table->index('idvisita');

            $table->foreign('idvisita', 'fk_reserva_visita')
                ->references('idvisita')->on('visita')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('reserva', function (Blueprint $table) {
            // SQLite (las pruebas) no quita llaves foráneas por nombre, solo por columna.
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['idvisita'] : 'fk_reserva_visita');
            $table->dropIndex(['idvisita']);
            $table->dropColumn(['idvisita', 'montousd']);
        });
    }
};
