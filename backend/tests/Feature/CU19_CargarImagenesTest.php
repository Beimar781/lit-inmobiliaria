<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Historial;
use App\Models\Imagen;
use App\Models\Propiedad;
use Illuminate\Http\UploadedFile;

/** CU19: Cargar imágenes de una propiedad (galería). Un test por paso del documento (6.2.4). */
class CU19_CargarImagenesTest extends SistemaTestCase
{
    private function subir(Propiedad $propiedad, array $archivos)
    {
        return $this->post(route('propiedades.imagenes.subir', $propiedad), ['imagenes' => $archivos]);
    }

    private function imagenes(int $cantidad, string $extension = 'jpg'): array
    {
        return array_map(fn ($i) => $this->imagen("foto{$i}.{$extension}"), range(1, $cantidad));
    }

    public function test_paso_1_subir_una_imagen_jpg_de_menos_de_4_mb_se_guarda_y_aparece_en_la_galeria(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->actingAs($agente);

        $this->subir($propiedad, [$this->imagen('fachada.jpg', 3000)])->assertRedirect(route('propiedades.imagenes', $propiedad));

        $imagen = Imagen::where('idpropiedad', $propiedad->idpropiedad)->firstOrFail();
        $this->assertTrue($imagen->portada, 'La primera imagen queda como portada.');
        $this->assertFileExists($this->rutaFisica($imagen->ruta));
        $this->get(route('propiedades.imagenes', $propiedad))->assertOk()
            ->assertSee('<span class="cu-etiqueta">(CU19)</span>', false)
            ->assertSee($imagen->ruta)->assertSee('1 de 8 imágenes');
        $this->assertSame(1, Bitacora::where('accion', 'IMAGENES_CARGADAS')->where('modulo', 'Propiedades')->count());
    }

    public function test_paso_2_subir_varias_a_la_vez_hasta_completar_8_se_guardan_todas(): void
    {
        $propiedad = $this->conImagenes($this->propiedad([], $agente = $this->agente()), 2);
        $this->actingAs($agente);

        $this->subir($propiedad, [...$this->imagenes(3, 'png'), ...$this->imagenes(3, 'webp')])->assertSessionDoesntHaveErrors();

        $this->assertSame(8, Imagen::where('idpropiedad', $propiedad->idpropiedad)->count());
        $this->assertSame(1, Imagen::where('idpropiedad', $propiedad->idpropiedad)->where('portada', true)->count());
        $this->get(route('propiedades.imagenes', $propiedad))->assertSee('8 de 8 imágenes');
    }

    public function test_paso_3_subir_una_novena_es_rechazada_y_no_se_guarda_ninguna_de_la_tanda(): void
    {
        $propiedad = $this->conImagenes($this->propiedad([], $agente = $this->agente()), 7);
        $this->actingAs($agente);

        $this->subir($propiedad, $this->imagenes(2))
            ->assertSessionHasErrors(['imagenes' => 'Puedes tener como máximo 8 imágenes por propiedad.']);

        $this->assertSame(7, Imagen::where('idpropiedad', $propiedad->idpropiedad)->count());
        // Con 7 guardadas, una sola sí cabe (límite exacto).
        $this->subir($propiedad, $this->imagenes(1))->assertSessionDoesntHaveErrors();
        $this->assertSame(8, Imagen::where('idpropiedad', $propiedad->idpropiedad)->count());
    }

    public function test_paso_4_subir_un_pdf_es_rechazado_por_formato(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->actingAs($agente);

        $this->subir($propiedad, [UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors(['imagenes.0' => 'El archivo debe ser una imagen JPG, PNG o WebP.']);

        $this->assertSame(0, Imagen::count());
    }

    public function test_paso_5_subir_una_imagen_de_mas_de_4_mb_es_rechazada_por_tamano(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->actingAs($agente);

        $this->subir($propiedad, [$this->imagen('grande.jpg', 4097)])
            ->assertSessionHasErrors(['imagenes.0' => 'Cada imagen debe pesar como máximo 4 MB.']);
        $this->assertSame(0, Imagen::count());

        $this->subir($propiedad, [$this->imagen('justa.jpg', 4096)])->assertSessionDoesntHaveErrors(); // valor límite
        $this->assertSame(1, Imagen::count());
    }

    public function test_paso_6_elegir_otra_portada_deja_solo_esa_como_portada(): void
    {
        $propiedad = $this->conImagenes($this->propiedad([], $agente = $this->agente()), 3);
        $otra = $propiedad->imagenes->where('portada', false)->last();
        $this->actingAs($agente);

        $this->patch(route('propiedades.imagenes.portada', [$propiedad, $otra]))->assertRedirect(route('propiedades.imagenes', $propiedad));

        $portadas = Imagen::where('idpropiedad', $propiedad->idpropiedad)->where('portada', true)->pluck('idimagen');
        $this->assertSame([$otra->idimagen], $portadas->all());
        $this->assertSame($otra->idimagen, $propiedad->fresh('imagenes')->imagenPrincipal->idimagen);
        $this->assertSame(1, Bitacora::where('accion', 'PORTADA_CAMBIADA')->count());
    }

    public function test_paso_7_eliminar_una_imagen_la_borra_y_si_era_la_portada_otra_pasa_a_serlo(): void
    {
        $propiedad = $this->conImagenes($this->propiedad([], $agente = $this->agente()), 2);
        $portada = $propiedad->imagenes->firstWhere('portada', true);
        $this->actingAs($agente);
        $archivo = $this->rutaFisica($portada->ruta);
        $this->assertFileExists($archivo);

        $this->delete(route('propiedades.imagenes.eliminar', [$propiedad, $portada]))->assertRedirect(route('propiedades.imagenes', $propiedad));

        $this->assertDatabaseMissing('imagen', ['idimagen' => $portada->idimagen]);
        $this->assertFileDoesNotExist($archivo);
        $restante = Imagen::where('idpropiedad', $propiedad->idpropiedad)->firstOrFail();
        $this->assertTrue($restante->portada);
        $this->assertSame(1, Bitacora::where('accion', 'IMAGEN_ELIMINADA')->count());
    }

    public function test_paso_8_un_agente_que_carga_imagenes_a_una_propiedad_de_otro_agente_recibe_403(): void
    {
        $propiedad = $this->conImagenes($this->propiedad([], $this->agente()), 2);
        $imagen = $propiedad->imagenes->first();
        $this->actingAs($this->agente());

        $this->get(route('propiedades.imagenes', $propiedad))->assertForbidden();
        $this->subir($propiedad, $this->imagenes(1))->assertForbidden();
        $this->patch(route('propiedades.imagenes.portada', [$propiedad, $imagen]))->assertForbidden();
        $this->delete(route('propiedades.imagenes.eliminar', [$propiedad, $imagen]))->assertForbidden();
        $this->assertSame(2, Imagen::where('idpropiedad', $propiedad->idpropiedad)->count());
    }

    public function test_no_se_puede_eliminar_la_ultima_imagen(): void
    {
        $propiedad = $this->conImagenes($this->propiedad([], $agente = $this->agente()), 1);
        $this->actingAs($agente);

        $this->delete(route('propiedades.imagenes.eliminar', [$propiedad, $propiedad->imagenes->first()]))
            ->assertRedirect(route('propiedades.imagenes', $propiedad))
            ->assertSessionHas('error', 'La propiedad debe conservar al menos una imagen.');

        $this->assertSame(1, Imagen::where('idpropiedad', $propiedad->idpropiedad)->count());
    }

    public function test_una_imagen_de_otra_propiedad_en_la_url_da_404(): void
    {
        $admin = $this->admin();
        $propiedad = $this->conImagenes($this->propiedad(), 2);
        $ajena = $this->conImagenes($this->propiedad(), 2)->imagenes->first();
        $this->actingAs($admin);

        $this->patch(route('propiedades.imagenes.portada', [$propiedad, $ajena]))->assertNotFound();
        $this->delete(route('propiedades.imagenes.eliminar', [$propiedad, $ajena]))->assertNotFound();
        $this->assertDatabaseHas('imagen', ['idimagen' => $ajena->idimagen]);
    }

    public function test_cada_cambio_queda_en_el_historial_de_la_propiedad(): void
    {
        $propiedad = $this->conImagenes($this->propiedad([], $agente = $this->agente()), 1);
        $this->actingAs($agente);

        $this->subir($propiedad, $this->imagenes(2));
        $nueva = Imagen::where('idpropiedad', $propiedad->idpropiedad)->where('portada', false)->firstOrFail();
        $this->patch(route('propiedades.imagenes.portada', [$propiedad, $nueva]));
        $this->delete(route('propiedades.imagenes.eliminar', [$propiedad, $nueva]));

        $motivos = Historial::where('idpropiedad', $propiedad->idpropiedad)->where('tipo', Historial::MODIFICACION)->pluck('motivo')->all();
        $this->assertContains('Imágenes: se cargaron 2', $motivos);
        $this->assertContains('Imágenes: portada cambiada', $motivos);
        $this->assertContains('Imágenes: se eliminó una', $motivos);
        $this->get(route('propiedades.historial', $propiedad))->assertOk()->assertSee('Imágenes: se cargaron 2');
    }

    public function test_propiedad_dada_de_baja_o_inexistente_da_404_y_otros_roles_403(): void
    {
        $baja = $this->propiedad(['estadopropiedad' => Propiedad::BAJA]);
        $this->actingAs($this->admin());
        $this->get(route('propiedades.imagenes', $baja))->assertNotFound();
        $this->get('/propiedades/99999/imagenes')->assertNotFound();

        $propiedad = $this->propiedad();
        $this->actingAs($this->cliente())->get(route('propiedades.imagenes', $propiedad))->assertForbidden();
        $this->actingAs($this->asistente())->get(route('propiedades.imagenes', $propiedad))->assertForbidden();
    }

    public function test_si_el_envio_excede_el_limite_de_php_se_muestra_un_error_claro(): void
    {
        $this->assertStringContainsString('demasiado grandes', view('errors.413')->render());
    }

    public function test_el_inventario_muestra_el_boton_imagenes_solo_en_las_propiedades_permitidas(): void
    {
        $agente = $this->agente();
        $propia = $this->propiedad(['titulo' => 'Mia'], $agente);
        $this->propiedad(['titulo' => 'Ajena'], $this->agente());

        $this->actingAs($agente)->get(route('propiedades.index'))->assertSee(route('propiedades.imagenes', $propia), false);
        $this->assertSame(1, substr_count($this->get(route('propiedades.index'))->getContent(), '>Imágenes</a>'));
    }
}