<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Propietario;

/** CU22: Gestionar propietarios (Agente y Administrador; eliminar solo el Administrador). Un test por paso del documento (6.2.4). */
class CU22_GestionarPropietariosTest extends SistemaTestCase
{
    private function datos(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'María Suárez',
            'telefono' => '71234567',
            'email' => 'maria@test.com',
            'direccion' => 'Calle Los Pinos #45',
        ], $cambios);
    }

    public function test_paso_1_registrar_con_nombre_telefono_correo_y_direccion_aparece_en_el_listado(): void
    {
        $this->actingAs($this->agente())
            ->post(route('propietarios.store'), $this->datos())
            ->assertRedirect(route('propietarios.index'));

        $this->assertDatabaseHas('propietario', ['nombre' => 'María Suárez', 'telefono' => '71234567', 'email' => 'maria@test.com', 'direccion' => 'Calle Los Pinos #45']);
        $this->get(route('propietarios.index'))->assertOk()
            ->assertSee('María Suárez')->assertSee('71234567')->assertSee('maria@test.com')
            ->assertSee('<span class="cu-etiqueta">(CU22)</span>', false);
        $this->assertSame(1, Bitacora::where('accion', 'PROPIETARIO_CREADO')->where('modulo', 'Propiedades')->count());
    }

    public function test_paso_2_sin_nombre_muestra_el_mensaje_de_nombre_obligatorio(): void
    {
        $this->actingAs($this->agente())
            ->post(route('propietarios.store'), $this->datos(['nombre' => '']))
            ->assertSessionHasErrors(['nombre' => 'El nombre del propietario es obligatorio.']);

        $this->assertSame(0, Propietario::where('email', 'maria@test.com')->count());
    }

    public function test_paso_3_correo_mal_escrito_es_rechazado_con_el_mensaje_de_correo_invalido(): void
    {
        $this->actingAs($this->agente())
            ->post(route('propietarios.store'), $this->datos(['email' => 'maria@@test']))
            ->assertSessionHasErrors(['email' => 'El correo no tiene un formato válido.']);

        $this->assertDatabaseCount('propietario', 0);
    }

    public function test_paso_4_buscar_por_parte_del_nombre_muestra_las_coincidencias(): void
    {
        Propietario::create(['nombre' => 'Roberto Antelo']);
        Propietario::create(['nombre' => 'Roberta Salvatierra', 'telefono' => '70099988']);
        Propietario::create(['nombre' => 'Carlos Vaca', 'email' => 'cvaca@test.com']);
        $this->actingAs($this->agente());

        $this->get(route('propietarios.index', ['q' => 'Rober']))->assertOk()
            ->assertSee('Roberto Antelo')->assertSee('Roberta Salvatierra')->assertDontSee('Carlos Vaca');
        $this->get(route('propietarios.index', ['q' => '70099']))->assertSee('Roberta Salvatierra')->assertDontSee('Roberto Antelo');
        $this->get(route('propietarios.index', ['q' => 'cvaca@']))->assertSee('Carlos Vaca')->assertDontSee('Roberto Antelo');
    }

    public function test_paso_5_editar_el_telefono_se_actualiza_en_el_listado(): void
    {
        $propietario = Propietario::create(['nombre' => 'Luis Parada', 'telefono' => '72345678']);
        $this->actingAs($this->agente());

        $this->get(route('propietarios.edit', $propietario))->assertOk()->assertSee('72345678');
        $this->put(route('propietarios.update', $propietario), ['nombre' => 'Luis Parada', 'telefono' => '78889999'])
            ->assertRedirect(route('propietarios.index'));

        $this->assertSame('78889999', $propietario->fresh()->telefono);
        $this->get(route('propietarios.index'))->assertSee('78889999');
        $this->assertSame(1, Bitacora::where('accion', 'PROPIETARIO_MODIFICADO')->count());
    }

    public function test_paso_6_el_administrador_elimina_un_propietario_sin_propiedades_y_desaparece(): void
    {
        $propietario = Propietario::create(['nombre' => 'Sin Propiedades']);

        $this->actingAs($this->admin())
            ->delete(route('propietarios.destroy', $propietario))
            ->assertRedirect(route('propietarios.index'));

        $this->assertDatabaseMissing('propietario', ['idpropietario' => $propietario->idpropietario]);
        $this->assertSame(1, Bitacora::where('accion', 'PROPIETARIO_ELIMINADO')->count());
    }

    public function test_paso_7_el_administrador_elimina_un_propietario_con_propiedades_y_es_rechazado(): void
    {
        $propietario = $this->propietario('Con Propiedades');
        $this->propiedad(['idpropietario' => $propietario->idpropietario]);

        $this->actingAs($this->admin())
            ->delete(route('propietarios.destroy', $propietario))
            ->assertRedirect(route('propietarios.index'))
            ->assertSessionHas('error', 'No se puede eliminar: el propietario tiene propiedades asociadas.');

        $this->assertDatabaseHas('propietario', ['idpropietario' => $propietario->idpropietario]);
        $this->get(route('propietarios.index'))->assertSee('tiene propiedades asociadas');
    }

    public function test_paso_8_un_agente_que_intenta_eliminar_recibe_403_y_el_propietario_sigue_existiendo(): void
    {
        $propietario = Propietario::create(['nombre' => 'Intocable']);

        $this->actingAs($this->agente())
            ->delete(route('propietarios.destroy', $propietario))
            ->assertForbidden();

        $this->assertDatabaseHas('propietario', ['idpropietario' => $propietario->idpropietario]);
    }

    public function test_el_agente_ve_el_listado_sin_boton_eliminar_y_el_administrador_si(): void
    {
        Propietario::create(['nombre' => 'Visible']);

        $this->actingAs($this->agente())->get(route('propietarios.index'))->assertOk()->assertDontSee('Eliminar</button>', false);
        $this->actingAs($this->admin())->get(route('propietarios.index'))->assertOk()->assertSee('Eliminar</button>', false);
    }

    public function test_un_cliente_y_un_asistente_reciben_403_en_el_listado(): void
    {
        $this->actingAs($this->cliente())->get(route('propietarios.index'))->assertForbidden();
        $this->actingAs($this->asistente())->get(route('propietarios.index'))->assertForbidden();
    }

    public function test_telefono_con_formato_invalido_y_listado_con_cantidad_de_propiedades(): void
    {
        $this->actingAs($this->agente())
            ->post(route('propietarios.store'), $this->datos(['telefono' => 'abc']))
            ->assertSessionHasErrors(['telefono' => 'El teléfono no tiene un formato válido.']);

        $propietario = $this->propietario('Dueño Dos');
        $this->propiedad(['idpropietario' => $propietario->idpropietario]);
        $this->propiedad(['idpropietario' => $propietario->idpropietario, 'titulo' => 'Otra']);

        $this->assertSame(2, Propietario::withCount('propiedades')->find($propietario->idpropietario)->propiedades_count);
    }

    public function test_los_propietarios_nuevos_aparecen_en_el_formulario_de_propiedades(): void
    {
        $this->actingAs($this->agente())->post(route('propietarios.store'), $this->datos(['nombre' => 'Zulema Ortiz']));

        $this->get(route('propiedades.create'))->assertOk()->assertSee('Zulema Ortiz');
    }
}