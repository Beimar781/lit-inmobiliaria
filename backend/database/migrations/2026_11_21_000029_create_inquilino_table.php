<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * INQUILINO (Ciclo 4 - CU24): datos de arrendamiento de un cliente (un inquilino por cliente).
 * Es aditiva e idempotente: si la tabla ya existe no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inquilino')) {
            return;
        }

        Schema::create('inquilino', function (Blueprint $table) {
            $table->id('idinquilino');
            $table->unsignedBigInteger('idcliente');
            $table->string('ocupacion', 100)->nullable();
            $table->string('tipogarantia', 50)->nullable();
            $table->text('detallegarantia')->nullable();
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamp('fecharegistro', 0);

            $table->unique('idcliente', 'uq_inquilino_cliente');

            $table->foreign('idcliente', 'fk_inquilino_cliente')
                ->references('idcliente')->on('cliente')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquilino');
    }
};
