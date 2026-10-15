<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imagen', function (Blueprint $table) {
            // 1 = imagen de portada (la que se muestra en el listado). Solo una por propiedad.
            $table->boolean('portada')->default(false)->after('ruta');
        });

        // Las propiedades que ya existen: su primera imagen pasa a ser la portada.
        $primeras = DB::table('imagen')->select(DB::raw('MIN(idimagen) as id'))->groupBy('idpropiedad')->pluck('id');
        DB::table('imagen')->whereIn('idimagen', $primeras)->update(['portada' => true]);
    }

    public function down(): void
    {
        Schema::table('imagen', function (Blueprint $table) {
            $table->dropColumn('portada');
        });
    }
};
