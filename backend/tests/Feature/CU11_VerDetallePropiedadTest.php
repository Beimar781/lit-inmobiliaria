<?php

namespace Tests\Feature;

use App\Models\Propiedad;

/** CU11: Ver detalle de propiedad (ficha pública). */
class CU11_VerDetallePropiedadTest extends SistemaTestCase
{
    public function test_la_ficha_es_publica_y_muestra_los_datos(): void
    {
        $agente = $this->agente(['nombre' => 'Marcela Torrez', 'telefono' => '70012345']);
        $propiedad = $this->propiedad([
            'titulo' => 'Casa en Equipetrol', 'descripcion' => 'Con jardín y piscina', 'precio' => 120000,
            'superficie' => 250, 'habitaciones' => 4, 'banos' => 3,
        ], $agente);

        $this->get(route('catalogo.detalle', $propiedad))
            ->assertOk()
            ->assertSee('Casa en Equipetrol')
            ->assertSee('Con jardín y piscina')
            ->assertSee($propiedad->codigo)
            ->assertSee('120,000')
            ->assertSee('Equipetrol')
            ->assertSee('Marcela Torrez')
            ->assertSee('Me interesa');
    }

    public function test_cualquier_rol_puede_ver_la_ficha(): void
    {
        $propiedad = $this->propiedad(['titulo' => 'Casa visible']);

        foreach ([$this->admin(), $this->agente(), $this->asistente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario)->get(route('catalogo.detalle', $propiedad))->assertOk()->assertSee('Casa visible');
        }
    }

    public function test_muestra_las_imagenes_con_la_portada_primero(): void
    {
        $propiedad = $this->conImagenes($this->propiedad(), 3);

        $respuesta = $this->get(route('catalogo.detalle', $propiedad))->assertOk();

        foreach ($propiedad->imagenes as $imagen) {
            $respuesta->assertSee(asset($imagen->ruta), false);
        }
    }

    public function test_la_foto_principal_se_muestra_completa_sin_recortar(): void
    {
        $propiedad = $this->conImagenes($this->propiedad(), 1);

        $this->get(route('catalogo.detalle', $propiedad))
            ->assertOk()
            ->assertSee('id="foto-principal"', false)
            ->assertDontSee('object-fit: cover; height: 380px', false)
            ->assertDontSee('height: 380px; object-fit: cover', false);
    }

    public function test_funciona_sin_imagenes_ni_agente(): void
    {
        $propiedad = $this->propiedad(['titulo' => 'Sin fotos']);

        $this->get(route('catalogo.detalle', $propiedad))->assertOk()->assertSee('Sin fotos');
    }

    public function test_muestra_el_mapa_con_las_coordenadas(): void
    {
        $propiedad = $this->propiedad();

        $this->get(route('catalogo.detalle', $propiedad))
            ->assertOk()
            ->assertSee('leaflet', false)
            ->assertSee('-17.763', false);
    }

    public function test_el_boton_de_whatsapp_aparece_si_el_agente_tiene_telefono(): void
    {
        $agente = $this->agente(['telefono' => '70012345']);
        $propiedad = $this->propiedad([], $agente);

        $this->get(route('catalogo.detalle', $propiedad))->assertSee('wa.me/', false);
    }

    public function test_una_propiedad_no_disponible_se_ve_pero_sin_formulario_de_interes(): void
    {
        foreach ([Propiedad::RESERVADO, Propiedad::VENDIDO, Propiedad::ALQUILADO] as $estado) {
            $propiedad = $this->propiedad(['titulo' => "Casa {$estado}", 'estadopropiedad' => $estado]);

            $this->get(route('catalogo.detalle', $propiedad))
                ->assertOk()
                ->assertSee('ya no está disponible')
                ->assertDontSee(route('catalogo.interes', $propiedad), false);
        }
    }

    public function test_una_propiedad_dada_de_baja_o_inexistente_da_404(): void
    {
        $baja = $this->propiedad(['estadopropiedad' => Propiedad::BAJA]);

        $this->get(route('catalogo.detalle', $baja))->assertNotFound();
        $this->get('/catalogo/9999')->assertNotFound();
        $this->get('/catalogo/abc')->assertNotFound();
    }
}
