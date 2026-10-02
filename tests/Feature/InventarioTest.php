<?php

namespace Tests\Feature;

use App\Models\Propiedad;

/** Listado de propiedades (punto de partida de CU6 y CU7): filtros, portada y paginación. */
class InventarioTest extends SistemaTestCase
{
    public function test_solo_administrador_y_agente_ven_el_inventario(): void
    {
        $this->get(route('propiedades.index'))->assertRedirect(route('login'));
        $this->actingAs($this->asistente())->get(route('propiedades.index'))->assertForbidden();
        $this->actingAs($this->cliente())->get(route('propiedades.index'))->assertForbidden();
        $this->actingAs($this->agente())->get(route('propiedades.index'))->assertOk();
        $this->actingAs($this->admin())->get(route('propiedades.index'))->assertOk();
    }

    public function test_sin_propiedades_muestra_un_aviso(): void
    {
        $this->actingAs($this->agente())->get(route('propiedades.index'))
            ->assertSee('No hay propiedades para mostrar.');
    }

    public function test_cada_tarjeta_muestra_titulo_categoria_tipo_zona_estado_y_precio(): void
    {
        $this->propiedad(['titulo' => 'Depto Equipetrol', 'precio' => 85000, 'tipopropiedad' => 'Venta']);

        $this->actingAs($this->agente())->get(route('propiedades.index'))
            ->assertSee('Depto Equipetrol')
            ->assertSee('Casa')
            ->assertSee('Venta')
            ->assertSee('Equipetrol')
            ->assertSee('Disponible')
            ->assertSee('$ 85,000', false);
    }

    public function test_el_alquiler_muestra_el_precio_por_mes(): void
    {
        $this->propiedad(['titulo' => 'Oficina', 'tipopropiedad' => 'Alquiler', 'precio' => 650]);
        $this->propiedad(['titulo' => 'Terreno', 'tipopropiedad' => 'Venta', 'precio' => 55000]);

        $html = $this->actingAs($this->agente())->get(route('propiedades.index'))->getContent();

        $this->assertSame(1, substr_count($html, '/ mes'));
    }

    public function test_muestra_la_imagen_de_portada_o_sin_imagen(): void
    {
        $con = $this->conImagenes($this->propiedad(['titulo' => 'Con fotos']), 2);
        $this->propiedad(['titulo' => 'Sin fotos']);
        $portada = $con->imagenes->firstWhere('portada', true);
        $otra = $con->imagenes->firstWhere('portada', false);

        $this->actingAs($this->agente())->get(route('propiedades.index'))
            ->assertSee($portada->ruta)
            ->assertDontSee($otra->ruta)
            ->assertSee('Sin imagen');
    }

    public function test_se_busca_por_titulo(): void
    {
        $this->propiedad(['titulo' => 'Casa en Urbarí']);
        $this->propiedad(['titulo' => 'Oficina en Sirari']);
        $this->actingAs($this->agente());

        $this->get(route('propiedades.index', ['q' => 'Urbarí']))->assertSee('Casa en Urbarí')->assertDontSee('Oficina en Sirari');
    }

    public function test_se_filtra_por_tipo_y_por_estado(): void
    {
        $this->propiedad(['titulo' => 'En venta', 'tipopropiedad' => 'Venta']);
        $this->propiedad(['titulo' => 'En alquiler', 'tipopropiedad' => 'Alquiler']);
        $this->propiedad(['titulo' => 'Reservada una', 'estadopropiedad' => Propiedad::RESERVADO]);
        $this->actingAs($this->agente());

        $this->get(route('propiedades.index', ['tipo' => 'Alquiler']))->assertSee('En alquiler')->assertDontSee('En venta');
        $this->get(route('propiedades.index', ['estado' => 'RESERVADO']))->assertSee('Reservada una')->assertDontSee('En venta');
        $this->get(route('propiedades.index', ['tipo' => 'Alquiler', 'estado' => 'RESERVADO']))
            ->assertDontSee('En alquiler')->assertDontSee('Reservada una');
    }

    public function test_las_propiedades_dadas_de_baja_no_aparecen_ni_filtrando_por_estado_baja(): void
    {
        $this->propiedad(['titulo' => 'Visible']);
        $this->propiedad(['titulo' => 'Retirada', 'estadopropiedad' => Propiedad::BAJA]);
        $this->actingAs($this->admin());

        $this->get(route('propiedades.index'))->assertSee('Visible')->assertDontSee('Retirada');
        $this->get(route('propiedades.index', ['estado' => 'BAJA']))->assertDontSee('Retirada');
    }

    public function test_pagina_de_9_en_9_con_las_mas_nuevas_primero(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $this->propiedad(['titulo' => "Propiedad {$i}"]);
        }
        $this->actingAs($this->agente());

        $this->get(route('propiedades.index'))->assertViewHas('propiedades', function ($p) {
            return $p->count() === 9 && $p->total() === 11 && $p->first()->titulo === 'Propiedad 11';
        });
        $this->get(route('propiedades.index', ['page' => 2]))->assertViewHas('propiedades', fn ($p) => $p->count() === 2);
    }

    public function test_la_paginacion_conserva_los_filtros(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->propiedad(['titulo' => "Casa {$i}", 'tipopropiedad' => 'Venta']);
        }
        $this->actingAs($this->agente());

        $this->get(route('propiedades.index', ['tipo' => 'Venta']))->assertSee('tipo=Venta', false);
    }
}
