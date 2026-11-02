<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * TIPOPAGO (Ciclo 3 - CU28): detalle 1 a 1 de un pago; guarda el tipo de cambio oficial usado ese día.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipopago', function (Blueprint $table) {
            $table->unsignedBigInteger('idpago')->primary();
            $table->decimal('cambiodemonedalocal', 10, 4);
            $table->date('fecha')->nullable();

            $table->foreign('idpago', 'fk_tipopago_pago')
                ->references('idpago')->on('pago')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipopago');
    }
};
