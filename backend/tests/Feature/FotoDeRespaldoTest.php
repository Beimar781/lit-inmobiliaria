<?php

namespace Tests\Feature;

use App\Models\Imagen;

/** Una foto cuyo archivo falta no debe romper nada ni dejar un ícono roto: se muestra «Sin foto». */
class FotoDeRespaldoTest extends SistemaTestCase
{
    private function propiedadConFotoPerdida(): \App\Models\Propiedad
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad(['titulo' => 'Casa con foto perdida'], $agente);
        // La fila existe pero el archivo no está en el disco (lo que pasa tras perder el almacenamiento)
        Imagen::create(['idpropiedad' => $propiedad->idpropiedad, 'nombre' => 'perdida.jpg', 'ruta' => 'uploads/propiedades/' . $propiedad->idpropiedad . '/no-existe.jpg', 'portada' => true]);

        return $propiedad;
    }

    public function test_el_layout_incluye_la_foto_de_respaldo_y_excluye_los_mosaicos_del_mapa(): void
    {
        $html = $this->get(route('catalogo.index'))->assertOk()->getContent();

        $this->assertStringContainsString("document.addEventListener('error'", $html);
        $this->assertStringContainsString('Sin foto', $html);
        $this->assertStringContainsString("closest('.leaflet-container')", $html);
        $this->assertStringContainsString('dataset.sinFoto', $html);
    }

    public function test_con_la_foto_perdida_todas_las_pantallas_siguen_funcionando_y_traen_el_respaldo(): void
    {
        $propiedad = $this->propiedadConFotoPerdida();
        $ruta = 'uploads/propiedades/' . $propiedad->idpropiedad . '/no-existe.jpg';
        $this->assertFileDoesNotExist($this->rutaFisica($ruta));

        $this->get(route('catalogo.index'))->assertOk()->assertSee($ruta)->assertSee('dataset.sinFoto', false);
        $this->get(route('catalogo.detalle', $propiedad))->assertOk()->assertSee($ruta)->assertSee('dataset.sinFoto', false);

        $this->actingAs($this->admin());
        $this->get(route('propiedades.index'))->assertOk()->assertSee($ruta)->assertSee('dataset.sinFoto', false);
        $this->get(route('propiedades.imagenes', $propiedad))->assertOk()->assertSee($ruta)->assertSee('dataset.sinFoto', false);
        $this->get(route('propiedades.edit', $propiedad))->assertOk()->assertSee($ruta);
    }

    public function test_una_propiedad_sin_fotos_sigue_mostrando_sin_imagen(): void
    {
        $this->propiedad(['titulo' => 'Casa sin ninguna foto']);

        $this->get(route('catalogo.index'))->assertOk()->assertSee('Sin imagen');
    }
}