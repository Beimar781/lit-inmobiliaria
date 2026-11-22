<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** F-015: la migración de índices existe, es idempotente y se puede revertir. */
class IndicesDeRendimientoTest extends TestCase
{
    use RefreshDatabase;

    private const ESPERADOS = [
        'imagen' => [['idpropiedad']],
        'historial' => [['idpropiedad', 'fecha'], ['idusuario']],
        'resena' => [['idpropiedad']],
        'cliente' => [['idusuario'], ['fecharegistro']],
        'interes' => [['idcliente']],
        'propiedad' => [['idpropietario'], ['idubicacion'], ['fecharegistro']],
        'contrato' => [['idusuario'], ['idreserva'], ['fechainicio']],
        'visita' => [['fecha']],
        'seguimiento' => [['idusuario'], ['idpropiedad']],
        'usuario' => [['idrol']],
        'bitacora' => [['idusuario']],
        'reserva' => [['idusuario']],
        'pago' => [['idusuario']],
        'movimientoreserva' => [['idusuario']],
    ];

    public function test_las_tablas_tienen_los_indices_de_rendimiento(): void
    {
        foreach (self::ESPERADOS as $tabla => $indices) {
            foreach ($indices as $columnas) {
                $this->assertTrue(Schema::hasIndex($tabla, $columnas), "Falta el índice de $tabla(" . implode(',', $columnas) . ')');
            }
        }
    }

    public function test_la_migracion_se_puede_ejecutar_de_nuevo_sin_error(): void
    {
        $migracion = require database_path('migrations/2026_11_21_000031_add_indices_de_rendimiento.php');

        $migracion->up();
        $migracion->up();

        $this->assertTrue(Schema::hasIndex('imagen', ['idpropiedad']));
    }

    public function test_el_down_quita_solo_los_indices_creados_por_la_migracion(): void
    {
        $migracion = require database_path('migrations/2026_11_21_000031_add_indices_de_rendimiento.php');

        $migracion->down();

        $this->assertFalse(Schema::hasIndex('imagen', ['idpropiedad']));
        $this->assertTrue(Schema::hasIndex('propiedad', ['idcategoria']), 'los índices anteriores no se tocan');
        $this->assertTrue(Schema::hasTable('imagen'));
    }
}
