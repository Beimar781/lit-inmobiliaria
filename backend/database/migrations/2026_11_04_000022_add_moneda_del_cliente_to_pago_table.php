<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Ciclo 3 - CU28: pago con tarjeta con Adaptive Pricing de Stripe.
 * El pago con tarjeta siempre se registra en dólares (monto/montousd). Si en la página de Stripe el cliente eligió pagar en
 * otra moneda (por ejemplo bolivianos), se guarda además lo que realmente pagó el cliente y el tipo de cambio que aplicó Stripe
 * (distinto del tipo de cambio manual de la empresa, que es el de la columna tipocambio y solo se usa en el efectivo):
 *  - moneda_cliente: moneda en que pagó el cliente (BOB). Vacía si pagó en dólares.
 *  - monto_cliente: monto que pagó el cliente en esa moneda.
 *  - tipocambio_stripe: unidades de esa moneda por dólar que aplicó Stripe.
 * Los pagos anteriores quedan con estos campos vacíos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago', function (Blueprint $table) {
            $table->string('moneda_cliente', 3)->nullable();
            $table->decimal('monto_cliente', 14, 2)->nullable();
            $table->decimal('tipocambio_stripe', 12, 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pago', function (Blueprint $table) {
            $table->dropColumn(['moneda_cliente', 'monto_cliente', 'tipocambio_stripe']);
        });
    }
};
