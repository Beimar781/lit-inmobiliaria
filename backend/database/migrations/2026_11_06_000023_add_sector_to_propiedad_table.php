<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Propiedad: nuevo atributo SECTOR (texto opcional, hasta 100 caracteres) para indicar el sector de la propiedad.
 * Las propiedades que ya existían quedan con el sector vacío.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('propiedad', 'sector')) {
            Schema::table('propiedad', function (Blueprint $table) {
                $table->string('sector', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('propiedad', 'sector')) {
            Schema::table('propiedad', function (Blueprint $table) {
                $table->dropColumn('sector');
            });
        }
    }
};
