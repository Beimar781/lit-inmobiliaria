<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Propiedad: nuevo atributo CONTACTO (nombre o teléfono de contacto, opcional, hasta 150 caracteres).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('propiedad', 'contacto')) {
            Schema::table('propiedad', function (Blueprint $table) {
                $table->string('contacto', 150)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('propiedad', 'contacto')) {
            Schema::table('propiedad', function (Blueprint $table) {
                $table->dropColumn('contacto');
            });
        }
    }
};
