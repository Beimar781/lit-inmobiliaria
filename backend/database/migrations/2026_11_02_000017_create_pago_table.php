<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * PAGO (Ciclo 3 - CU28 a CU30): abono a un contrato.
 * monto/moneda son lo que se pagó (USD o BOB); montousd es el equivalente en dólares y es el que descuenta el saldo del contrato.
 * Estados: PAGADO / PENDIENTE / ANULADO. El comprobante (CP-AAAA-000001) es único.
 * Los datos de Stripe sirven para el pago con tarjeta y para no procesar dos veces la misma sesión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago', function (Blueprint $table) {
            $table->id('idpago');
            $table->unsignedBigInteger('idcontrato');
            $table->decimal('monto', 12, 2);
            $table->string('moneda', 10);                                             // USD / BOB
            $table->decimal('tipocambio', 10, 4)->nullable();                         // solo en pagos en BOB
            $table->dateTime('fechapago');
            $table->string('comprobante', 255)->nullable()->unique();
            $table->string('estado', 30)->default('PENDIENTE');
            $table->decimal('montousd', 12, 2);
            $table->string('metodo', 20)->default('EFECTIVO');                        // EFECTIVO / TARJETA
            $table->unsignedBigInteger('idusuario')->nullable();                      // quién lo registró
            $table->string('stripe_session_id', 255)->nullable()->unique();
            $table->string('stripe_payment_intent', 255)->nullable();

            $table->index(['idcontrato', 'estado'], 'idx_pago_contrato_estado');
            $table->index('fechapago');

            $table->foreign('idcontrato', 'fk_pago_contrato')
                ->references('idcontrato')->on('contrato')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('idusuario', 'fk_pago_usuario')
                ->references('idusuario')->on('usuario')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago');
    }
};
