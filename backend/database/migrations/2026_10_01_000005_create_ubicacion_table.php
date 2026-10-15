<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ubicacion', function (Blueprint $table) {
            $table->id('idubicacion');
            $table->string('ciudad', 100)->nullable();
            $table->string('zona', 150)->nullable();
            $table->text('direccion')->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ubicacion');
    }
};
