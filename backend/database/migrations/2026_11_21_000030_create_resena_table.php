<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * RESENA (Ciclo 4 - CU25): reseña de un cliente sobre una propiedad (puntuación de 1 a 5, una por cliente y propiedad).
 * Es aditiva e idempotente: si la tabla ya existe no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('resena')) {
            return;
        }

        Schema::create('resena', function (Blueprint $table) {
            $table->id('idresena');
            $table->unsignedBigInteger('idpropiedad');
            $table->unsignedBigInteger('idcliente');
            $table->smallInteger('puntuacion');
            $table->text('comentario')->nullable();
            $table->timestamp('fecha', 0);

            $table->unique(['idcliente', 'idpropiedad'], 'uq_resena_cliente_propiedad');

            $table->foreign('idpropiedad', 'fk_resena_propiedad')
                ->references('idpropiedad')->on('propiedad')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('idcliente', 'fk_resena_cliente')
                ->references('idcliente')->on('cliente')->cascadeOnDelete()->cascadeOnUpdate();
        });

        DB::statement('ALTER TABLE resena ADD CONSTRAINT ck_resena_puntuacion CHECK (puntuacion BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('resena');
    }
};
