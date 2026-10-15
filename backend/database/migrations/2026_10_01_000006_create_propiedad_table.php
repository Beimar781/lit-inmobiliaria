<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propiedad', function (Blueprint $table) {
            $table->id('idpropiedad');
            $table->unsignedBigInteger('idpropietario')->nullable();
            $table->unsignedBigInteger('idcategoria')->nullable();
            $table->unsignedBigInteger('idubicacion')->nullable();
            $table->unsignedBigInteger('idusuario')->nullable(); // agente que la registró
            $table->string('titulo', 255);
            $table->text('descripcion')->nullable();
            $table->decimal('precio', 12, 2);
            $table->string('tipopropiedad', 50)->nullable();      // Venta / Alquiler / Anticrético
            $table->string('estadopropiedad', 50)->default('DISPONIBLE');
            $table->decimal('superficie', 8, 2)->nullable();
            $table->decimal('areaconstruida', 8, 2)->nullable();
            $table->integer('habitaciones')->nullable();
            $table->integer('banos')->nullable();
            $table->integer('antiguedad')->nullable();
            $table->dateTime('fecharegistro')->nullable()->useCurrent();

            $table->foreign('idpropietario', 'fk_propiedad_propietario')
                ->references('idpropietario')->on('propietario')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('idcategoria', 'fk_propiedad_categoria')
                ->references('idcategoria')->on('categoria')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('idubicacion', 'fk_propiedad_ubicacion')
                ->references('idubicacion')->on('ubicacion')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('idusuario', 'fk_propiedad_usuario')
                ->references('idusuario')->on('usuario')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propiedad');
    }
};
