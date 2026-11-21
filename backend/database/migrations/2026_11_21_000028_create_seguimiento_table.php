<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * SEGUIMIENTO (Ciclo 4 - CU18): historial de contactos con un cliente prospecto.
 * Es aditiva e idempotente: si la tabla ya existe no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seguimiento')) {
            return;
        }

        Schema::create('seguimiento', function (Blueprint $table) {
            $table->id('idseguimiento');
            $table->unsignedBigInteger('idcliente');
            $table->unsignedBigInteger('idusuario');
            $table->unsignedBigInteger('idpropiedad')->nullable();
            $table->string('medio', 20);
            $table->text('nota');
            $table->timestamp('fechacontacto', 0);
            $table->date('proximocontacto')->nullable();
            $table->timestamp('fecharegistro', 0);

            $table->index(['idcliente', 'fechacontacto'], 'idx_seguimiento_cliente_fecha');

            $table->foreign('idcliente', 'fk_seguimiento_cliente')
                ->references('idcliente')->on('cliente')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('idusuario', 'fk_seguimiento_usuario')
                ->references('idusuario')->on('usuario')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('idpropiedad', 'fk_seguimiento_propiedad')
                ->references('idpropiedad')->on('propiedad')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimiento');
    }
};
