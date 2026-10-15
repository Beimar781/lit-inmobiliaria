<?php

namespace Tests\Feature;

use App\Models\Ubicacion;

/** CU10: Filtrar propiedades (filtros avanzados del catálogo público). */
class CU10_FiltrarPropiedadesTest extends SistemaTestCase
{
    private function catalogo(array $filtros)
    {
        return $this->get(route('catalogo.index', $filtros));
    }

    public function test_filtra_por_tipo_de_operacion(): void
    {
        $this->propiedad(['titulo' => 'En venta', 'tipopropiedad' => 'Venta']);
        $this->propiedad(['titulo' => 'En alquiler', 'tipopropiedad' => 'Alquiler']);
        $this->propiedad(['titulo' => 'En anticrético', 'tipopropiedad' => 'Anticrético']);

        $this->catalogo(['tipo' => 'Alquiler'])->assertSee('En alquiler')->assertDontSee('En venta')->assertDontSee('En anticr');
        $this->catalogo(['tipo' => 'Anticrético'])->assertSee('En anticr')->assertDontSee('En venta');
    }

    public function test_filtra_por_categoria(): void
    {
        $casa = $this->categoria('Casa');
        $depto = $this->categoria('Departamento');
        $this->propiedad(['titulo' => 'Una casa', 'idcategoria' => $casa->idcategoria]);
        $this->propiedad(['titulo' => 'Un depto', 'idcategoria' => $depto->idcategoria]);

        $this->catalogo(['categoria' => $depto->idcategoria])->assertSee('Un depto')->assertDontSee('Una casa');
    }

    public function test_filtra_por_rango_de_precio(): void
    {
        $this->propiedad(['titulo' => 'Barata', 'precio' => 50000]);
        $this->propiedad(['titulo' => 'Media', 'precio' => 100000]);
        $this->propiedad(['titulo' => 'Cara', 'precio' => 300000]);

        $this->catalogo(['precio_min' => 80000, 'precio_max' => 150000])
            ->assertSee('Media')->assertDontSee('Barata')->assertDontSee('Cara');
        $this->catalogo(['precio_min' => 100000])->assertSee('Media')->assertSee('Cara')->assertDontSee('Barata');
        $this->catalogo(['precio_max' => 100000])->assertSee('Media')->assertSee('Barata')->assertDontSee('Cara');
    }

    public function test_filtra_por_zona(): void
    {
        $urbari = Ubicacion::create(['ciudad' => 'Santa Cruz de la Sierra', 'zona' => 'Urbarí', 'direccion' => 'Calle 1', 'latitud' => self::LAT_DENTRO, 'longitud' => self::LNG_DENTRO]);
        $this->propiedad(['titulo' => 'En Urbarí', 'idubicacion' => $urbari->idubicacion]);
        $this->propiedad(['titulo' => 'En Equipetrol']);

        $this->catalogo(['zona' => 'Urbar'])->assertSee('En Urbarí')->assertDontSee('En Equipetrol');
    }

    public function test_filtra_por_superficie_habitaciones_y_banos(): void
    {
        $this->propiedad(['titulo' => 'Chica', 'superficie' => 60, 'habitaciones' => 1, 'banos' => 1]);
        $this->propiedad(['titulo' => 'Grande', 'superficie' => 400, 'habitaciones' => 5, 'banos' => 4]);

        $this->catalogo(['superficie_min' => 100])->assertSee('Grande')->assertDontSee('Chica');
        $this->catalogo(['superficie_max' => 100])->assertSee('Chica')->assertDontSee('Grande');
        $this->catalogo(['habitaciones' => 3])->assertSee('Grande')->assertDontSee('Chica');
        $this->catalogo(['banos' => 2])->assertSee('Grande')->assertDontSee('Chica');
    }

    public function test_los_filtros_se_combinan_entre_si_y_con_la_palabra_clave(): void
    {
        $this->propiedad(['titulo' => 'Casa ideal', 'tipopropiedad' => 'Venta', 'precio' => 90000, 'habitaciones' => 3]);
        $this->propiedad(['titulo' => 'Casa cara', 'tipopropiedad' => 'Venta', 'precio' => 400000, 'habitaciones' => 3]);
        $this->propiedad(['titulo' => 'Casa alquiler', 'tipopropiedad' => 'Alquiler', 'precio' => 90000, 'habitaciones' => 3]);

        $this->catalogo(['q' => 'Casa', 'tipo' => 'Venta', 'precio_max' => 100000, 'habitaciones' => 3])
            ->assertSee('Casa ideal')->assertDontSee('Casa cara')->assertDontSee('Casa alquiler');
    }

    public function test_los_filtros_nunca_muestran_propiedades_no_disponibles(): void
    {
        $this->propiedad(['titulo' => 'Ya reservada', 'estadopropiedad' => 'RESERVADO', 'precio' => 100]);

        $this->catalogo(['precio_max' => 1000])->assertDontSee('Ya reservada');
    }

    public function test_sin_resultados_avisa_y_ofrece_ver_todo(): void
    {
        $this->propiedad(['precio' => 80000]);

        $this->catalogo(['precio_min' => 900000])
            ->assertOk()
            ->assertSee('No encontramos propiedades que coincidan')
            ->assertSee('Ver todo el catálogo');
    }

    public function test_rechaza_rangos_invertidos(): void
    {
        $this->catalogo(['precio_min' => 500, 'precio_max' => 100])
            ->assertRedirect(route('catalogo.index'))->assertSessionHasErrors('precio_min');
        $this->catalogo(['superficie_min' => 500, 'superficie_max' => 100])
            ->assertRedirect(route('catalogo.index'))->assertSessionHasErrors('superficie_min');
    }

    public function test_rechaza_valores_no_validos(): void
    {
        $this->propiedad();

        foreach ([
            ['precio_min' => 'abc'], ['precio_max' => '-5'], ['superficie_min' => 'xx'],
            ['habitaciones' => 'dos'], ['banos' => '-1'], ['tipo' => 'Permuta'], ['categoria' => 9999],
        ] as $filtros) {
            $campo = array_key_first($filtros);
            $this->catalogo($filtros)->assertSessionHasErrors($campo);
        }
    }

    public function test_la_pagina_conserva_los_filtros_al_paginar(): void
    {
        foreach (range(1, 10) as $i) {
            $this->propiedad(['titulo' => "Casa {$i}", 'precio' => 70000]);
        }

        $this->catalogo(['precio_min' => 1000])
            ->assertSee('precio_min=1000', false);
    }

    public function test_el_formulario_recuerda_lo_que_se_filtro(): void
    {
        $this->propiedad();

        $this->catalogo(['q' => 'Casa', 'precio_min' => 1234])
            ->assertSee('value="1234"', false)
            ->assertSee('value="Casa"', false);
    }
}
