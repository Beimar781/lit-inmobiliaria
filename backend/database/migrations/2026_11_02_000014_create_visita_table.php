<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * VISITA (Ciclo 3 - CU14 a CU17): cita presencial de un cliente a una propiedad.
 * El agente responsable no se guarda aparte: es el usuario que registró la propiedad (propiedad.idusuario).
 * Estados: PROGRAMADA / REAGENDADA / CANCELADA / REALIZADA. Las canceladas no ocupan horario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visita', function (Blueprint $table) {
            $table->id('idvisita');
            $table->unsignedBigInteger('idpropiedad');
            $table->unsignedBigInteger('idcliente');
            $table->unsignedBigInteger('idhistorial')->nullable();
            $table->date('fecha');
            $table->time('hora');
            $table->string('estado', 50)->default('PROGRAMADA');
            $table->text('observacion')->nullable();
            $table->text('motivo')->nullable();                                      // motivo de reagendar o cancelar

            $table->index(['idpropiedad', 'fecha', 'hora'], 'idx_visita_propiedad_fecha_hora');
            $table->index('idcliente');
            $table->index('estado');

            $table->foreign('idpropiedad', 'fk_visita_propiedad')
                ->references('idpropiedad')->on('propiedad')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('idcliente', 'fk_visita_cliente')
                ->references('idcliente')->on('cliente')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('idhistorial', 'fk_visita_historial')
                ->references('idhistorial')->on('historial')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visita');
    }
};
