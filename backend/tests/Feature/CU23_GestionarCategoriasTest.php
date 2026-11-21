<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Categoria;

/** CU23: Gestionar categorías de propiedad (solo Administrador). Un test por paso del documento (6.2.4). */
class CU23_GestionarCategoriasTest extends SistemaTestCase
{
    public function test_paso_1_registrar_categoria_con_nombre_y_descripcion_se_guarda_y_aparece_en_el_listado(): void
    {
        $this->actingAs($this->admin())
            ->post(route('categorias.store'), ['nombre' => 'Galpón', 'descripcion' => 'Naves industriales'])
            ->assertRedirect(route('categorias.index'));

        $this->assertDatabaseHas('categoria', ['nombre' => 'Galpón', 'descripcion' => 'Naves industriales']);
        $this->get(route('categorias.index'))->assertOk()
            ->assertSee('Galpón')->assertSee('Naves industriales')
            ->assertSee('<span class="cu-etiqueta">(CU23)</span>', false);
        $this->assertSame(1, Bitacora::where('accion', 'CATEGORIA_CREADA')->where('modulo', 'Propiedades')->count());
    }

    public function test_paso_2_nombre_ya_existente_es_rechazado(): void
    {
        $this->categoria('Casa');

        $this->actingAs($this->admin())
            ->from(route('categorias.index'))
            ->post(route('categorias.store'), ['nombre' => 'Casa'])
            ->assertRedirect(route('categorias.index'))
            ->assertSessionHasErrors(['nombre' => 'Ya existe una categoría con ese nombre.']);

        $this->assertSame(1, Categoria::where('nombre', 'Casa')->count());
    }

    public function test_paso_3_sin_nombre_es_rechazado(): void
    {
        $this->actingAs($this->admin())
            ->post(route('categorias.store'), ['nombre' => '', 'descripcion' => 'x'])
            ->assertSessionHasErrors(['nombre' => 'El nombre de la categoría es obligatorio.']);

        $this->assertDatabaseCount('categoria', 0);
    }

    public function test_paso_4_editar_la_descripcion_se_guarda_y_se_ve_en_el_listado(): void
    {
        $categoria = $this->categoria('Casa');
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('categorias.edit', $categoria))->assertOk()->assertSee('Editar categoría');
        $this->put(route('categorias.update', $categoria), ['nombre' => 'Casa', 'descripcion' => 'Viviendas familiares'])
            ->assertRedirect(route('categorias.index'));

        $this->assertSame('Viviendas familiares', $categoria->fresh()->descripcion);
        $this->get(route('categorias.index'))->assertSee('Viviendas familiares');
        $this->assertSame(1, Bitacora::where('accion', 'CATEGORIA_MODIFICADA')->count());
    }

    public function test_paso_5_eliminar_categoria_sin_propiedades_desaparece(): void
    {
        $categoria = $this->categoria('Bodega');

        $this->actingAs($this->admin())
            ->delete(route('categorias.destroy', $categoria))
            ->assertRedirect(route('categorias.index'));

        $this->assertDatabaseMissing('categoria', ['idcategoria' => $categoria->idcategoria]);
        $this->assertSame(1, Bitacora::where('accion', 'CATEGORIA_ELIMINADA')->count());
    }

    public function test_paso_6_eliminar_categoria_con_propiedades_es_rechazado_con_mensaje_en_uso(): void
    {
        $categoria = $this->categoria('Casa');
        $this->propiedad(['idcategoria' => $categoria->idcategoria, 'estadopropiedad' => 'Disponible']);

        $this->actingAs($this->admin())
            ->delete(route('categorias.destroy', $categoria))
            ->assertRedirect(route('categorias.index'))
            ->assertSessionHas('error', 'No se puede eliminar: la categoría está en uso por propiedades.');

        $this->assertDatabaseHas('categoria', ['idcategoria' => $categoria->idcategoria]);
        $this->get(route('categorias.index'))->assertSee('No se puede eliminar: la categoría está en uso por propiedades.');
    }

    public function test_paso_7_un_agente_que_abre_la_gestion_de_categorias_recibe_403(): void
    {
        $categoria = $this->categoria('Casa');
        $this->actingAs($this->agente());

        $this->get(route('categorias.index'))->assertForbidden();
        $this->post(route('categorias.store'), ['nombre' => 'Nueva'])->assertForbidden();
        $this->get(route('categorias.edit', $categoria))->assertForbidden();
        $this->put(route('categorias.update', $categoria), ['nombre' => 'Otra'])->assertForbidden();
        $this->delete(route('categorias.destroy', $categoria))->assertForbidden();
        $this->assertDatabaseMissing('categoria', ['nombre' => 'Nueva']);
    }

    public function test_un_nombre_de_101_caracteres_es_rechazado(): void
    {
        $this->actingAs($this->admin())
            ->post(route('categorias.store'), ['nombre' => str_repeat('a', 101)])
            ->assertSessionHasErrors('nombre');

        $this->assertDatabaseCount('categoria', 0);
    }

    public function test_el_nombre_es_unico_sin_distinguir_mayusculas_ni_espacios_sobrantes(): void
    {
        $categoria = $this->categoria('Casa');
        $this->actingAs($this->admin());

        $this->post(route('categorias.store'), ['nombre' => 'casa'])->assertSessionHasErrors('nombre');
        $this->post(route('categorias.store'), ['nombre' => '  CASA  '])->assertSessionHasErrors('nombre');
        // Al editar, conservar el propio nombre (aunque cambie la mayúscula) sí es válido.
        $this->put(route('categorias.update', $categoria), ['nombre' => 'CASA'])->assertSessionDoesntHaveErrors();
        $this->assertSame('CASA', $categoria->fresh()->nombre);
    }

    public function test_las_categorias_nuevas_aparecen_en_el_formulario_de_propiedades_y_el_panel_solo_las_ve_el_administrador(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('categorias.store'), ['nombre' => 'Galpón']);

        $this->get(route('propiedades.create'))->assertOk()->assertSee('Galpón');
        $this->get(route('panel'))->assertSee('Categorías');

        $this->actingAs($this->agente())->get(route('panel'))->assertDontSee('Administrar las categorías de las propiedades');
    }
}