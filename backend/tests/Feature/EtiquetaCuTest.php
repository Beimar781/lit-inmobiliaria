<?php

namespace Tests\Feature;

/** El número del caso de uso se ve, en gris suave, junto al título de su pantalla. */
class EtiquetaCuTest extends SistemaTestCase
{
    public function test_las_pantallas_del_personal_muestran_el_numero_de_su_caso_de_uso(): void
    {
        $this->actingAs($this->admin());

        $pantallas = [
            'usuarios.index' => 'CU4',
            'propiedades.create' => 'CU5',
            'prospectos.index' => 'CU13',
            'visitas.calendario' => 'CU17',
            'reservas.index' => 'CU26',
            'contratos.index' => 'CU27',
            'pagos.create' => 'CU28',
            'pagos.index' => 'CU29',
        ];

        foreach ($pantallas as $ruta => $cu) {
            $this->get(route($ruta))->assertOk()
                ->assertSee('<span class="cu-etiqueta">('.$cu.')</span>', false);
        }
    }

    public function test_el_catalogo_y_el_inicio_de_sesion_muestran_su_numero_sin_iniciar_sesion(): void
    {
        $this->get(route('catalogo.index'))->assertOk()->assertSee('(CU9)');
        $this->get(route('login'))->assertOk()->assertSee('(CU1)');
    }

    public function test_el_panel_y_el_inventario_no_llevan_numero_de_caso_de_uso(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('panel'))->assertOk()->assertDontSee('cu-etiqueta"', false);
        $this->get(route('propiedades.index'))->assertOk()->assertDontSee('<span class="cu-etiqueta">', false);
    }
}
