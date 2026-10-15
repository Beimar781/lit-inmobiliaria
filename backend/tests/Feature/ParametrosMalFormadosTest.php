<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Las pantallas con listados no se caen (error 500) si alguien escribe a mano parámetros raros en la URL:
 * ?q[]=x, ?idrol=abc, ?agente[]=1 ... El valor mal formado simplemente se ignora.
 */
class ParametrosMalFormadosTest extends SistemaTestCase
{
    public static function casos(): array
    {
        return [
            'bitácora: texto en arreglo' => ['bitacora.index', ['q' => ['x']]],
            'bitácora: fechas en arreglo' => ['bitacora.index', ['desde' => ['x'], 'hasta' => ['y']]],
            'bitácora: filtros en arreglo' => ['bitacora.index', ['modulo' => ['x'], 'accion' => ['y'], 'idusuario' => ['1']]],
            'bitácora: usuario no numérico' => ['bitacora.index', ['idusuario' => 'abc']],
            'usuarios: texto en arreglo' => ['usuarios.index', ['q' => ['x']]],
            'usuarios: rol en arreglo' => ['usuarios.index', ['idrol' => ['x']]],
            'usuarios: rol no numérico' => ['usuarios.index', ['idrol' => 'abc']],
            'prospectos: texto en arreglo' => ['prospectos.index', ['q' => ['x']]],
            'prospectos: estado en arreglo' => ['prospectos.index', ['estado' => ['x']]],
            'prospectos: agente en arreglo' => ['prospectos.index', ['agente' => ['1']]],
            'prospectos: agente no numérico' => ['prospectos.index', ['agente' => 'abc']],
            'inventario: texto en arreglo' => ['propiedades.index', ['q' => ['x']]],
            'inventario: filtros en arreglo' => ['propiedades.index', ['estado' => ['x'], 'tipo' => ['y']]],
        ];
    }

    #[DataProvider('casos')]
    public function test_un_parametro_mal_formado_se_ignora_y_la_pantalla_carga(string $ruta, array $parametros): void
    {
        $this->actingAs($this->admin());

        $this->get(route($ruta, $parametros))->assertOk();
    }

    public function test_el_agente_tampoco_ve_errores_en_sus_pantallas(): void
    {
        $this->actingAs($this->agente());

        $this->get(route('prospectos.index', ['q' => ['x'], 'estado' => ['y'], 'agente' => ['1']]))->assertOk();
        $this->get(route('propiedades.index', ['q' => ['x']]))->assertOk();
    }

    public function test_un_filtro_numerico_valido_sigue_funcionando(): void
    {
        $admin = $this->admin();
        $agente = $this->agente();
        $this->actingAs($admin);

        $this->get(route('usuarios.index', ['idrol' => $this->idRol('Agente Inmobiliario')]))
            ->assertOk()->assertSee($agente->email)->assertDontSee($admin->email);
    }
}
