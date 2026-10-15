<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * CLIENTE (Ciclo 2 - CU12/CU13): prospecto del CRM, es decir, una persona que mostró interés en una propiedad.
 * No tiene cuenta en el sistema (no es un USUARIO). Se le asigna un agente y un estado de seguimiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente', function (Blueprint $table) {
            $table->id('idcliente');
            $table->string('nombre', 255);
            $table->string('email', 255)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('tipocliente', 50)->nullable();                          // Comprador / Inversionista / Inquilino
            $table->string('estadoseguimiento', 30)->default('NUEVO');              // NUEVO / CONTACTADO / EN_NEGOCIACION / CERRADO / DESCARTADO
            $table->unsignedBigInteger('idusuario')->nullable();                    // agente responsable (null = sin asignar)
            $table->dateTime('fecharegistro')->nullable()->useCurrent();

            $table->index('email');
            $table->index('telefono');
            $table->index('estadoseguimiento');

            $table->foreign('idusuario', 'fk_cliente_usuario')
                ->references('idusuario')->on('usuario')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente');
    }
};
