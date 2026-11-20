<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Reserva: nuevo atributo DETALLES (texto libre opcional, hasta 1000 caracteres).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reserva', 'detalles')) {
            Schema::table('reserva', function (Blueprint $table) {
                $table->text('detalles')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('reserva', 'detalles')) {
            Schema::table('reserva', function (Blueprint $table) {
                $table->dropColumn('detalles');
            });
        }
    }
};
