<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * INTERES (Ciclo 2 - CU12): vincula a un cliente con una propiedad por la que preguntó.
 * Un cliente solo puede tener un interés registrado por propiedad (no se duplica).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interes', function (Blueprint $table) {
            $table->id('idinteres');
            $table->unsignedBigInteger('idpropiedad');
            $table->unsignedBigInteger('idcliente');
            $table->text('mensaje')->nullable();
            $table->date('fecha')->nullable()->default(DB::raw('CURRENT_DATE'));

            $table->unique(['idpropiedad', 'idcliente'], 'uq_interes_propiedad_cliente');

            $table->foreign('idpropiedad', 'fk_interes_propiedad')
                ->references('idpropiedad')->on('propiedad')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('idcliente', 'fk_interes_cliente')
                ->references('idcliente')->on('cliente')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interes');
    }
};
