<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propietario', function (Blueprint $table) {
            $table->id('idpropietario');
            $table->string('nombre', 255);
            $table->string('telefono', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('direccion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propietario');
    }
};
