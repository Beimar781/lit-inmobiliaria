<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Cliente: nuevo atributo ESTADO ('activo' o 'inactivo'). Los clientes que ya existían quedan 'activo'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cliente', 'estado')) {
            Schema::table('cliente', function (Blueprint $table) {
                $table->string('estado', 20)->default('activo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cliente', 'estado')) {
            Schema::table('cliente', function (Blueprint $table) {
                $table->dropColumn('estado');
            });
        }
    }
};
