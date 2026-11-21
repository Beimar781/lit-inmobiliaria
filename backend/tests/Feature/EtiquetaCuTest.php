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
            'categorias.index' => 'CU23',
            'propietarios.index' => 'CU22',
            'propiedades.mapa' => 'CU21',
            'reportes.index' => 'CU20',
        ];

        foreach ($pantallas as $ruta => $cu) {
            $this->get(route($ruta))->assertOk()
                ->assertSee('<span class="cu-etiqueta">('.$cu.')</span>', false);
        }
    }

    public function test_el_historial_de_una_propiedad_muestra_su_numero_de_caso_de_uso(): void
    {
        $this->actingAs($this->admin())
            ->get(route('propiedades.historial', $this->propiedad()))
            ->assertOk()
            ->assertSee('<span class="cu-etiqueta">(CU31)</span>', false);
    }

    public function test_la_pantalla_de_ubicacion_muestra_su_numero_de_caso_de_uso(): void
    {
        $this->actingAs($this->admin())
            ->get(route('propiedades.ubicacion', $this->propiedad()))
            ->assertOk()
            ->assertSee('<span class="cu-etiqueta">(CU21)</span>', false);
    }

    public function test_el_seguimiento_de_un_prospecto_muestra_su_numero_de_caso_de_uso(): void
    {
        $cliente = \App\Models\Cliente::create(['nombre' => 'Pedro', 'email' => 'pedro@test.com']);

        $this->actingAs($this->admin())
            ->get(route('prospectos.seguimientos', $cliente))
            ->assertOk()
            ->assertSee('<span class="cu-etiqueta">(CU18)</span>', false);
    }

    public function test_el_inquilino_de_un_prospecto_muestra_su_numero_de_caso_de_uso(): void
    {
        $cliente = \App\Models\Cliente::create(['nombre' => 'Pedro', 'email' => 'pedro@test.com']);

        $this->actingAs($this->admin())
            ->get(route('prospectos.inquilino', $cliente))
            ->assertOk()
            ->assertSee('<span class="cu-etiqueta">(CU24)</span>', false);
    }

    public function test_la_resena_de_una_propiedad_muestra_su_numero_de_caso_de_uso_al_cliente(): void
    {
        $cuenta = $this->cliente();
        $cliente = \App\Models\Cliente::create(['nombre' => 'Clara', 'email' => $cuenta->email, 'idusuariocuenta' => $cuenta->idusuario]);
        $propiedad = $this->propiedad();
        \App\Models\Contrato::create(['idpropiedad' => $propiedad->idpropiedad, 'idcliente' => $cliente->idcliente, 'tipocontrato' => 'Venta', 'monto' => 1000, 'fechainicio' => '2026-12-01']);

        $this->actingAs($cuenta)
            ->get(route('resenas.crear', $propiedad))
            ->assertOk()
            ->assertSee('<span class="cu-etiqueta">(CU25)</span>', false);
    }

    public function test_la_galeria_de_imagenes_muestra_su_numero_de_caso_de_uso(): void
    {
        $this->actingAs($this->admin())
            ->get(route('propiedades.imagenes', $this->propiedad()))
            ->assertOk()
            ->assertSee('<span class="cu-etiqueta">(CU19)</span>', false);
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
