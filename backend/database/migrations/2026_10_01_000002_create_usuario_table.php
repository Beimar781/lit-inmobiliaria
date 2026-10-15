<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Cambios respecto al script del documento:
 *  + password        (CU1 y CU3 necesitan contraseña, guardada con hash)
 *  + remember_token  (opción "recordarme" del login)
 *  - idhistorial     (se elimina la referencia circular; HISTORIAL.idusuario ya dice quién hizo el cambio)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario', function (Blueprint $table) {
            $table->id('idusuario');
            $table->string('nombre', 255);
            $table->string('email', 255)->unique();
            $table->string('telefono', 20)->nullable();
            $table->string('password', 255);
            $table->string('estado', 20)->default('ACTIVO');
            $table->unsignedBigInteger('idrol');
            $table->rememberToken();

            $table->foreign('idrol', 'fk_usuario_rol')
                ->references('idrol')->on('rol')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario');
    }
};
