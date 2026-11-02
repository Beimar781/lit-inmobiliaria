<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * CONTRATO (Ciclo 3 - CU27): formaliza la venta, el alquiler o el anticrético de una propiedad.
 * El monto está en dólares (USD). Estados: VIGENTE / FINALIZADO / RESCINDIDO.
 * idreserva guarda la reserva de la que viene el contrato, si la hubo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato', function (Blueprint $table) {
            $table->id('idcontrato');
            $table->unsignedBigInteger('idpropiedad');
            $table->unsignedBigInteger('idcliente');
            $table->unsignedBigInteger('idusuario')->nullable();                      // agente que gestionó el contrato
            $table->unsignedBigInteger('idreserva')->nullable();
            $table->string('tipocontrato', 50);                                       // Venta / Alquiler / Anticrético
            $table->decimal('monto', 12, 2);                                          // en dólares
            $table->date('fechainicio');
            $table->date('fechafin')->nullable();
            $table->string('estado', 30)->nullable()->default('VIGENTE');

            $table->index(['idpropiedad', 'estado'], 'idx_contrato_propiedad_estado');
            $table->index('idcliente');
            $table->index('estado');

            $table->foreign('idpropiedad', 'fk_contrato_propiedad')
                ->references('idpropiedad')->on('propiedad')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('idcliente', 'fk_contrato_cliente')
                ->references('idcliente')->on('cliente')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('idusuario', 'fk_contrato_usuario')
                ->references('idusuario')->on('usuario')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('idreserva', 'fk_contrato_reserva')
                ->references('idreserva')->on('reserva')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato');
    }
};
