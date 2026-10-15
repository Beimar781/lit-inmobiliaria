<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Índices de la tabla PROPIEDAD para el catálogo (CU9, CU10) y el inventario: filtran por estado, tipo, precio,
 * categoría y agente. Solo agrega índices; no cambia ni borra datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('propiedad', function (Blueprint $table) {
            $table->index(['estadopropiedad', 'tipopropiedad'], 'idx_propiedad_estado_tipo');
            $table->index('precio', 'idx_propiedad_precio');
            $table->index('idcategoria', 'idx_propiedad_categoria');
            $table->index('idusuario', 'idx_propiedad_usuario');
        });
    }

    public function down(): void
    {
        Schema::table('propiedad', function (Blueprint $table) {
            $table->dropIndex('idx_propiedad_estado_tipo');
            $table->dropIndex('idx_propiedad_precio');
            $table->dropIndex('idx_propiedad_categoria');
            $table->dropIndex('idx_propiedad_usuario');
        });
    }
};
