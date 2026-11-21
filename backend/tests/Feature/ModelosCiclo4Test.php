<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Inquilino;
use App\Models\Propiedad;
use App\Models\Resena;
use App\Models\Seguimiento;
use App\Models\Usuario;
use App\Services\BitacoraService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Tablas y modelos del Ciclo 4: seguimiento (CU18), inquilino (CU24) y reseña (CU25). */
class ModelosCiclo4Test extends SistemaTestCase
{
    private function cliente4(array $datos = []): Cliente
    {
        static $n = 0;
        $n++;

        return Cliente::create(array_merge(['nombre' => "Cliente {$n}", 'email' => "cliente4_{$n}@test.com", 'telefono' => '70011122'], $datos));
    }

    private function seguimiento(Cliente $cliente, Usuario $usuario, array $datos = []): Seguimiento
    {
        return Seguimiento::create(array_merge([
            'idcliente' => $cliente->idcliente,
            'idusuario' => $usuario->idusuario,
            'medio' => 'LLAMADA',
            'nota' => 'Se llamó al prospecto.',
            'fechacontacto' => now(),
            'fecharegistro' => now(),
        ], $datos));
    }

    private function resena(Propiedad $propiedad, Cliente $cliente, int $puntuacion): Resena
    {
        return Resena::create([
            'idpropiedad' => $propiedad->idpropiedad,
            'idcliente' => $cliente->idcliente,
            'puntuacion' => $puntuacion,
            'fecha' => now(),
        ]);
    }

    /** Intenta la operación dentro de un savepoint (en PostgreSQL un error aborta toda la transacción si no) y exige que falle. */
    private function debeFallar(callable $operacion): void
    {
        try {
            DB::transaction($operacion);
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('La base de datos debía rechazar la operación.');
    }

    public function test_las_tres_tablas_existen_y_migrar_dos_veces_no_falla(): void
    {
        foreach (['seguimiento', 'inquilino', 'resena'] as $tabla) {
            $this->assertTrue(Schema::hasTable($tabla), "Falta la tabla {$tabla}");
        }

        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertTrue(Schema::hasTable('resena'));
    }

    // ------------------------------------------------------------- seguimiento

    public function test_un_seguimiento_se_guarda_con_propiedad_nula_y_proximo_contacto_opcional(): void
    {
        $seguimiento = $this->seguimiento($this->cliente4(), $this->agente())->fresh();

        $this->assertNull($seguimiento->idpropiedad);
        $this->assertNull($seguimiento->proximocontacto);
        $this->assertSame('LLAMADA', $seguimiento->medio);
        $this->assertSame('Llamada', Seguimiento::MEDIOS[$seguimiento->medio]);
    }

    public function test_al_borrar_el_cliente_se_borran_sus_seguimientos(): void
    {
        $cliente = $this->cliente4();
        $this->seguimiento($cliente, $this->agente());
        $this->seguimiento($cliente, $this->agente());

        $this->assertSame(2, $cliente->seguimientos()->count());
        $cliente->delete();

        $this->assertSame(0, Seguimiento::count());
    }

    public function test_no_se_puede_borrar_un_usuario_que_tiene_seguimientos(): void
    {
        $agente = $this->agente();
        $this->seguimiento($this->cliente4(), $agente);

        $this->debeFallar(fn () => Usuario::whereKey($agente->idusuario)->delete());
        $this->assertSame(1, Seguimiento::count());
    }

    public function test_al_borrar_la_propiedad_el_seguimiento_queda_con_propiedad_nula(): void
    {
        $propiedad = $this->propiedad();
        $seguimiento = $this->seguimiento($this->cliente4(), $this->agente(), ['idpropiedad' => $propiedad->idpropiedad]);

        $this->assertSame($propiedad->idpropiedad, $seguimiento->fresh()->propiedad->idpropiedad);
        Propiedad::whereKey($propiedad->idpropiedad)->delete();

        $this->assertNull($seguimiento->fresh()->idpropiedad);
    }

    // --------------------------------------------------------------- inquilino

    public function test_el_inquilino_queda_activo_por_defecto_y_se_relaciona_con_su_cliente(): void
    {
        $cliente = $this->cliente4();
        Inquilino::create(['idcliente' => $cliente->idcliente, 'fecharegistro' => now()]);

        $inquilino = $cliente->fresh()->inquilino;
        $this->assertSame('ACTIVO', $inquilino->estado);
        $this->assertSame($cliente->idcliente, $inquilino->cliente->idcliente);
    }

    public function test_un_cliente_no_puede_tener_dos_inquilinos(): void
    {
        $cliente = $this->cliente4();
        Inquilino::create(['idcliente' => $cliente->idcliente, 'fecharegistro' => now()]);

        $this->debeFallar(fn () => Inquilino::create(['idcliente' => $cliente->idcliente, 'fecharegistro' => now()]));
        $this->assertSame(1, Inquilino::count());
    }

    // ------------------------------------------------------------------ reseña

    public function test_la_puntuacion_de_la_resena_debe_estar_entre_1_y_5(): void
    {
        $propiedad = $this->propiedad();

        foreach ([0, 6] as $puntuacion) {
            $this->debeFallar(fn () => $this->resena($propiedad, $this->cliente4(), $puntuacion));
        }
        foreach ([1, 5] as $puntuacion) {
            $this->resena($propiedad, $this->cliente4(), $puntuacion);
        }

        $this->assertSame(2, Resena::count());
    }

    public function test_un_cliente_solo_puede_resenar_una_vez_la_misma_propiedad(): void
    {
        $propiedad = $this->propiedad();
        $cliente = $this->cliente4();
        $this->resena($propiedad, $cliente, 4);

        $this->debeFallar(fn () => $this->resena($propiedad, $cliente, 5));
        $this->resena($this->propiedad(), $cliente, 5); // en otra propiedad sí puede
        $this->assertSame(2, Resena::count());
    }

    public function test_al_borrar_la_propiedad_se_borran_sus_resenas(): void
    {
        $propiedad = $this->propiedad();
        $this->resena($propiedad, $this->cliente4(), 4);
        $this->resena($propiedad, $this->cliente4(), 2);

        Propiedad::whereKey($propiedad->idpropiedad)->delete();

        $this->assertSame(0, Resena::count());
    }

    public function test_la_puntuacion_promedio_de_la_propiedad(): void
    {
        $propiedad = $this->propiedad();
        $this->assertNull($propiedad->puntuacionPromedio());
        $this->assertSame(0, $propiedad->totalResenas());

        $this->resena($propiedad, $this->cliente4(), 5);
        $this->resena($propiedad, $this->cliente4(), 3);

        $this->assertSame(4.0, $propiedad->puntuacionPromedio());
        $this->assertSame(2, $propiedad->totalResenas());

        $this->resena($propiedad, $this->cliente4(), 4); // 12 / 3 = 4.0
        $this->resena($propiedad, $this->cliente4(), 5); // 17 / 4 = 4.25 -> 4.3
        $this->assertSame(4.3, $propiedad->puntuacionPromedio());
    }

    // ---------------------------------------------------------------- bitácora

    public function test_las_etiquetas_nuevas_de_bitacora_existen(): void
    {
        $nuevas = [
            'SEGUIMIENTO_REGISTRADO', 'INQUILINO_REGISTRADO', 'INQUILINO_MODIFICADO', 'RESENA_REGISTRADA',
            'CATEGORIA_CREADA', 'CATEGORIA_MODIFICADA', 'CATEGORIA_ELIMINADA',
            'PROPIETARIO_CREADO', 'PROPIETARIO_MODIFICADO', 'PROPIETARIO_ELIMINADO',
            'IMAGENES_CARGADAS', 'PORTADA_CAMBIADA', 'IMAGEN_ELIMINADA', 'UBICACION_MODIFICADA',
            'REPORTE_GENERADO', 'REPORTE_EXPORTADO',
        ];

        foreach ($nuevas as $accion) {
            $this->assertArrayHasKey($accion, BitacoraService::ETIQUETAS);
            $this->assertNotSame('', trim(BitacoraService::ETIQUETAS[$accion]));
        }
        $this->assertContains('Reportes', BitacoraService::MODULOS);
    }

    public function test_la_pantalla_de_bitacora_filtra_por_las_acciones_y_el_modulo_nuevos(): void
    {
        $admin = $this->admin();
        BitacoraService::registrar('REPORTE_GENERADO', 'Reportes', 'Generó un reporte', null, $admin->idusuario);

        $respuesta = $this->actingAs($admin)->get(route('bitacora.index', ['modulo' => 'Reportes', 'accion' => 'REPORTE_GENERADO']));

        $respuesta->assertOk()->assertSee('Reporte generado')->assertSee('Generó un reporte');
    }
}
