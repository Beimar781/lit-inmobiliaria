<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * MOVIMIENTORESERVA (Ciclo 3 - CU26): el dinero en efectivo de una reserva.
 *  - COBRO: lo que el cliente paga para reservar (se registra al crear la reserva).
 *  - DEVOLUCION: lo que se le devuelve si no formaliza la compra (todo menos el porcentaje retenido por la empresa).
 * Cada reserva tiene como máximo un cobro y una devolución. El comprobante (RV-AAAA-000001 para cobros, DV-AAAA-000001 para
 * devoluciones) es único. monto/moneda es lo que se entregó (USD o BOB); montousd es su equivalente en dólares.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientoreserva', function (Blueprint $table) {
            $table->id('idmovimiento');
            $table->unsignedBigInteger('idreserva');
            $table->string('tipo', 20);                                               // COBRO / DEVOLUCION
            $table->decimal('monto', 12, 2);
            $table->string('moneda', 10);                                             // USD / BOB
            $table->decimal('tipocambio', 10, 4)->nullable();                         // solo en BOB
            $table->decimal('montousd', 12, 2);
            $table->decimal('porcentajeretencion', 5, 2)->nullable();                 // solo en la devolución: lo que se quedó la empresa
            $table->string('comprobante', 30)->unique();
            $table->dateTime('fecha');
            $table->unsignedBigInteger('idusuario')->nullable();                      // quién manejó el efectivo

            $table->unique(['idreserva', 'tipo'], 'uq_movimientoreserva_reserva_tipo');

            $table->foreign('idreserva', 'fk_movimientoreserva_reserva')
                ->references('idreserva')->on('reserva')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('idusuario', 'fk_movimientoreserva_usuario')
                ->references('idusuario')->on('usuario')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientoreserva');
    }
};
