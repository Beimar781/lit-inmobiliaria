<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Ciclo 3: la cuenta de acceso del cliente. Se crea después de su primer pago; mientras tanto queda vacía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cliente', function (Blueprint $table) {
            $table->unsignedBigInteger('idusuariocuenta')->nullable();
            $table->index('idusuariocuenta');

            $table->foreign('idusuariocuenta', 'fk_cliente_usuariocuenta')
                ->references('idusuario')->on('usuario')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('cliente', function (Blueprint $table) {
            // SQLite (las pruebas) no quita llaves foráneas por nombre, solo por columna.
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['idusuariocuenta'] : 'fk_cliente_usuariocuenta');
            $table->dropIndex(['idusuariocuenta']);
            $table->dropColumn('idusuariocuenta');
        });
    }
};
