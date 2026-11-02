<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * RESERVA (Ciclo 3 - CU26): aparta una propiedad para un cliente hasta una fecha plazo.
 * Estados: VIGENTE / CANCELADA / CONCRETADA (pasó a contrato) / VENCIDA (se acabó el plazo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserva', function (Blueprint $table) {
            $table->id('idreserva');
            $table->unsignedBigInteger('idcliente');
            $table->unsignedBigInteger('idpropiedad');
            $table->date('fechareserva')->default(DB::raw('CURRENT_DATE'));
            $table->date('fechaplazo');
            $table->string('estado', 30)->default('VIGENTE');
            $table->unsignedBigInteger('idusuario')->nullable();                      // agente que la registró

            $table->index(['idpropiedad', 'estado'], 'idx_reserva_propiedad_estado');
            $table->index('idcliente');

            $table->foreign('idcliente', 'fk_reserva_cliente')
                ->references('idcliente')->on('cliente')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('idpropiedad', 'fk_reserva_propiedad')
                ->references('idpropiedad')->on('propiedad')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('idusuario', 'fk_reserva_usuario')
                ->references('idusuario')->on('usuario')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva');
    }
};
