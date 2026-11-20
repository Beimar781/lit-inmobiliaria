<?php

namespace Tests\Feature;

use App\Models\Propiedad;

/** Atributo "contacto" de la propiedad: opcional, hasta 150 caracteres, visible en el detalle. */
class ContactoPropiedadTest extends SistemaTestCase
{
    private function registrar(array $datos = [])
    {
        return $this->post(route('propiedades.store'), array_merge(
            $this->datosPropiedad($datos),
            ['imagenes' => [$this->imagen('uno.jpg')]]
        ));
    }

    public function test_el_contacto_se_guarda_al_registrar_y_se_ve_en_el_detalle(): void
    {
        $this->actingAs($this->agente());

        $this->registrar(['contacto' => 'María Pérez 70123456'])->assertSessionHasNoErrors();

        $propiedad = Propiedad::firstOrFail();
        $this->assertSame('María Pérez 70123456', $propiedad->contacto);

        $this->get(route('catalogo.detalle', $propiedad))
            ->assertOk()
            ->assertSee('Contacto')
            ->assertSee('María Pérez 70123456');
    }

    public function test_el_contacto_es_opcional_y_el_detalle_muestra_un_guion(): void
    {
        $this->actingAs($this->agente());

        $this->registrar()->assertSessionHasNoErrors();

        $propiedad = Propiedad::firstOrFail();
        $this->assertNull($propiedad->contacto);
        $this->get(route('catalogo.detalle', $propiedad))->assertOk()->assertSee('Contacto');
    }

    public function test_el_contacto_se_puede_modificar_y_borrar(): void
    {
        $this->actingAs($this->agente());
        $propiedad = $this->propiedad(['contacto' => 'Ana 7000000']);

        $this->put(route('propiedades.update', $propiedad), $this->datosActuales($propiedad, ['contacto' => 'Luis 7111111']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Luis 7111111', $propiedad->fresh()->contacto);

        $this->put(route('propiedades.update', $propiedad), $this->datosActuales($propiedad, ['contacto' => '']))
            ->assertSessionHasNoErrors();
        $this->assertNull($propiedad->fresh()->contacto);
    }

    public function test_rechaza_un_contacto_de_mas_de_150_caracteres(): void
    {
        $this->actingAs($this->agente());

        $this->registrar(['contacto' => str_repeat('a', 151)])->assertSessionHasErrors('contacto');
        $this->assertSame(0, Propiedad::count());

        $this->registrar(['contacto' => str_repeat('a', 150)])->assertSessionHasNoErrors();
        $this->assertSame(1, Propiedad::count());
    }
}
