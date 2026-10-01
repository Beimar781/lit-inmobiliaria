<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial', function (Blueprint $table) {
            $table->id('idhistorial');
            $table->string('tipo', 50);                 // REGISTRO / MODIFICACION / BAJA ...
            $table->text('valoranterior')->nullable();
            $table->text('valoractual')->nullable();
            $table->text('motivo')->nullable();
            $table->dateTime('fecha')->useCurrent();
            $table->unsignedBigInteger('idusuario')->nullable();
            $table->unsignedBigInteger('idpropiedad')->nullable();

            $table->foreign('idusuario', 'fk_historial_usuario')
                ->references('idusuario')->on('usuario')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('idpropiedad', 'fk_historial_propiedad')
                ->references('idpropiedad')->on('propiedad')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial');
    }
};
