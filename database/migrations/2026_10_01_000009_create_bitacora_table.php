<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * BITACORA: registro de todo lo que ocurre en el sistema, para que el Administrador lo consulte.
 * (Tabla nueva, no estaba en el diagrama de clases original.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora', function (Blueprint $table) {
            $table->id('idbitacora');
            $table->unsignedBigInteger('idusuario')->nullable();   // quién lo hizo (null = visitante o sistema)
            $table->string('accion', 50);                          // INICIO_SESION, PROPIEDAD_BAJA ...
            $table->string('modulo', 50);                          // Autenticación, Usuarios, Propiedades
            $table->text('descripcion');
            $table->text('detalle')->nullable();                   // JSON con valores anteriores/actuales, motivo, etc.
            $table->string('ip', 45)->nullable();
            $table->dateTime('fecha');

            $table->index('fecha');
            $table->index('accion');

            $table->foreign('idusuario', 'fk_bitacora_usuario')
                ->references('idusuario')->on('usuario')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
    }
};
