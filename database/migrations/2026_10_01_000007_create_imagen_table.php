<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imagen', function (Blueprint $table) {
            $table->id('idimagen');
            $table->unsignedBigInteger('idpropiedad');
            $table->string('nombre', 255);
            $table->string('ruta', 500);
            $table->dateTime('fechacarga')->nullable()->useCurrent();

            $table->foreign('idpropiedad', 'fk_imagen_propiedad')
                ->references('idpropiedad')->on('propiedad')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imagen');
    }
};
