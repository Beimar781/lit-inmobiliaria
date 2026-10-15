<?php

namespace Tests\Feature;

use App\Models\Propiedad;
use App\Models\Ubicacion;

/** CU9: Buscar propiedades (catálogo público; solo se muestran las disponibles). */
class CU9_BuscarPropiedadesTest extends SistemaTestCase
{
    public function test_el_catalogo_es_publico_y_muestra_solo_las_disponibles(): void
    {
        $this->propiedad(['titulo' => 'Casa visible']);
        foreach ([Propiedad::RESERVADO, Propiedad::VENDIDO, Propiedad::ALQUILADO, Propiedad::BAJA] as $estado) {
            $this->propiedad(['titulo' => "Casa {$estado}", 'estadopropiedad' => $estado]);
        }

        $this->get(route('catalogo.index'))
            ->assertOk()
            ->assertSee('Casa visible')
            ->assertDontSee('Casa RESERVADO')
            ->assertDontSee('Casa VENDIDO')
            ->assertDontSee('Casa ALQUILADO')
            ->assertDontSee('Casa BAJA');
    }

    public function test_cualquier_rol_puede_ver_el_catalogo(): void
    {
        $this->propiedad(['titulo' => 'Casa visible']);

        foreach ([$this->admin(), $this->agente(), $this->asistente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario)->get(route('catalogo.index'))->assertOk()->assertSee('Casa visible');
        }
    }

    public function test_sin_propiedades_muestra_un_aviso(): void
    {
        $this->get(route('catalogo.index'))->assertOk()->assertSee('no hay propiedades disponibles');
    }

    public function test_busca_por_titulo(): void
    {
        $this->propiedad(['titulo' => 'Departamento con vista']);
        $this->propiedad(['titulo' => 'Casa con piscina']);

        $this->get(route('catalogo.index', ['q' => 'piscina']))
            ->assertSee('Casa con piscina')
            ->assertDontSee('Departamento con vista');
    }

    public function test_busca_por_zona_y_por_direccion(): void
    {
        $sirari = Ubicacion::create(['ciudad' => 'Santa Cruz de la Sierra', 'zona' => 'Sirari', 'direccion' => 'Calle Los Pinos', 'latitud' => self::LAT_DENTRO, 'longitud' => self::LNG_DENTRO]);
        $this->propiedad(['titulo' => 'En Sirari', 'idubicacion' => $sirari->idubicacion]);
        $this->propiedad(['titulo' => 'En Equipetrol']);

        $this->get(route('catalogo.index', ['q' => 'Sirari']))->assertSee('En Sirari')->assertDontSee('En Equipetrol');
        $this->get(route('catalogo.index', ['q' => 'Los Pinos']))->assertSee('En Sirari')->assertDontSee('En Equipetrol');
    }

    public function test_busca_por_codigo_de_propiedad(): void
    {
        $uno = $this->propiedad(['titulo' => 'Primera']);
        $dos = $this->propiedad(['titulo' => 'Segunda']);

        foreach ([$dos->codigo, strtolower($dos->codigo), 'LIT ' . $dos->idpropiedad, (string) $dos->idpropiedad] as $texto) {
            $this->get(route('catalogo.index', ['q' => $texto]))
                ->assertSee('Segunda')
                ->assertDontSee('Primera');
        }
        $this->assertNotSame($uno->codigo, $dos->codigo);
    }

    public function test_un_codigo_de_una_propiedad_no_disponible_no_la_revela(): void
    {
        $vendida = $this->propiedad(['titulo' => 'Ya vendida', 'estadopropiedad' => Propiedad::VENDIDO]);

        $this->get(route('catalogo.index', ['q' => $vendida->codigo]))
            ->assertOk()
            ->assertDontSee('Ya vendida')
            ->assertSee('No encontramos propiedades que coincidan');
    }

    public function test_la_busqueda_sin_resultados_avisa(): void
    {
        $this->propiedad();

        $this->get(route('catalogo.index', ['q' => 'zzzzzz']))->assertOk()->assertSee('No encontramos propiedades que coincidan');
    }

    public function test_un_texto_demasiado_largo_es_rechazado(): void
    {
        $this->get(route('catalogo.index', ['q' => str_repeat('a', 101)]))
            ->assertRedirect(route('catalogo.index'))
            ->assertSessionHasErrors('q');
    }

    public function test_caracteres_especiales_no_rompen_la_busqueda(): void
    {
        $this->propiedad();

        foreach (["%", "_", "'; DROP TABLE propiedad;--", "<script>alert(1)</script>"] as $texto) {
            $this->get(route('catalogo.index', ['q' => $texto]))->assertOk();
        }
        $this->assertSame(1, Propiedad::count());
    }

    public function test_el_listado_se_pagina_de_9_en_9_de_la_mas_reciente_a_la_mas_antigua(): void
    {
        foreach (range(1, 11) as $i) {
            $this->propiedad(['titulo' => "Propiedad número {$i}"]);
        }

        $this->get(route('catalogo.index'))
            ->assertSee('Propiedad número 11')
            ->assertSee('Propiedad número 3')
            ->assertDontSee('Propiedad número 2<', false);

        $this->get(route('catalogo.index', ['page' => 2]))
            ->assertSee('Propiedad número 2<', false)
            ->assertSee('Propiedad número 1<', false)
            ->assertDontSee('Propiedad número 11');
    }

    public function test_cada_tarjeta_enlaza_a_la_ficha_y_no_muestra_el_codigo(): void
    {
        $propiedad = $this->propiedad();

        // El código se ve solo dentro de la ficha (CU11), no en el listado.
        $this->get(route('catalogo.index'))
            ->assertSee(route('catalogo.detalle', $propiedad), false)
            ->assertDontSee($propiedad->codigo);
    }

    public function test_el_inventario_interno_usa_la_misma_busqueda(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad(['titulo' => 'Casa interna'], $agente);

        $this->actingAs($agente)->get(route('propiedades.index', ['q' => $propiedad->codigo]))
            ->assertOk()->assertSee('Casa interna');
    }
}
