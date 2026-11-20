<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Rol: nuevo atributo TIPO (texto opcional, hasta 50 caracteres).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rol', 'tipo')) {
            Schema::table('rol', function (Blueprint $table) {
                $table->string('tipo', 50)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rol', 'tipo')) {
            Schema::table('rol', function (Blueprint $table) {
                $table->dropColumn('tipo');
            });
        }
    }
};
