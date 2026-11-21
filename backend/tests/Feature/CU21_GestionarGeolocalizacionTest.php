<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Historial;
use App\Models\Propiedad;
use App\Models\Ubicacion;

/** CU21: Gestionar geolocalización y mapa de la cartera. Un test por paso del documento (6.2.4). */
class CU21_GestionarGeolocalizacionTest extends SistemaTestCase
{
    private function guardar(Propiedad $propiedad, array $cambios = [])
    {
        return $this->put(route('propiedades.ubicacion.guardar', $propiedad), array_merge([
            'zona' => 'Equipetrol Norte',
            'direccion' => 'Calle 5 Oeste #120',
            'latitud' => self::LAT_DENTRO,
            'longitud' => self::LNG_DENTRO,
        ], $cambios));
    }

    public function test_paso_1_punto_dentro_del_4_anillo_con_zona_y_direccion_se_guarda_y_el_mapa_muestra_la_propiedad(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad(['titulo' => 'Casa del Mapa'], $agente);
        $this->actingAs($agente);

        $this->get(route('propiedades.ubicacion', $propiedad))->assertOk()
            ->assertSee('<span class="cu-etiqueta">(CU21)</span>', false);
        $this->guardar($propiedad, ['latitud' => '-17.7700000', 'longitud' => '-63.1900000'])
            ->assertRedirect(route('propiedades.ubicacion', $propiedad));

        $ubicacion = $propiedad->fresh('ubicacion')->ubicacion;
        $this->assertSame('Equipetrol Norte', $ubicacion->zona);
        $this->assertSame('Calle 5 Oeste #120', $ubicacion->direccion);
        $this->assertEqualsWithDelta(-17.77, (float) $ubicacion->latitud, 0.0000001);
        $this->get(route('propiedades.mapa'))->assertOk()
            ->assertSee('<span class="cu-etiqueta">(CU21)</span>', false)
            ->assertSee('Casa del Mapa')->assertSee('-17.77')->assertSee('Equipetrol Norte');
    }

    public function test_paso_2_punto_fuera_del_4_anillo_es_rechazado_con_el_mensaje_de_limite(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->actingAs($agente);

        $this->guardar($propiedad, ['latitud' => self::LAT_FUERA, 'longitud' => self::LNG_FUERA])
            ->assertSessionHasErrors(['latitud' => 'La ubicación debe estar dentro del 4.º anillo de Santa Cruz de la Sierra.']);

        $this->assertEqualsWithDelta((float) self::LAT_DENTRO, (float) $propiedad->fresh('ubicacion')->ubicacion->latitud, 0.0000001);
    }

    public function test_paso_3_guardar_sin_zona_o_sin_direccion_muestra_los_mensajes_de_campos_obligatorios(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->actingAs($agente);

        $this->guardar($propiedad, ['zona' => ''])->assertSessionHasErrors(['zona' => 'La zona es obligatoria.']);
        $this->guardar($propiedad, ['direccion' => ''])->assertSessionHasErrors(['direccion' => 'La dirección es obligatoria.']);
        $this->guardar($propiedad, ['latitud' => '', 'longitud' => ''])
            ->assertSessionHasErrors(['latitud' => 'Marca la ubicación de la propiedad en el mapa.', 'longitud' => 'Marca la ubicación de la propiedad en el mapa.']);
    }

    public function test_paso_4_mover_el_punto_de_una_propiedad_ya_ubicada_se_actualiza_y_queda_en_el_historial(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->actingAs($agente);

        $this->guardar($propiedad, ['zona' => 'Equipetrol', 'direccion' => 'Av. San Martín', 'latitud' => '-17.7750000', 'longitud' => '-63.1850000'])
            ->assertSessionDoesntHaveErrors();

        $this->assertEqualsWithDelta(-17.775, (float) $propiedad->fresh('ubicacion')->ubicacion->latitud, 0.0000001);
        $cambio = Historial::where('idpropiedad', $propiedad->idpropiedad)->firstOrFail();
        $this->assertSame(Historial::MODIFICACION, $cambio->tipo);
        $this->assertSame('Ubicación actualizada', $cambio->motivo);
        $this->assertSame(['latitud' => '-17.7630000', 'longitud' => '-63.1980000'], json_decode($cambio->valoranterior, true));
        $this->assertSame(['latitud' => '-17.7750000', 'longitud' => '-63.1850000'], json_decode($cambio->valoractual, true));
        $this->assertSame(1, Bitacora::where('accion', 'UBICACION_MODIFICADA')->where('modulo', 'Propiedades')->count());
        $this->get(route('propiedades.historial', $propiedad))->assertSee('Ubicación actualizada');
    }

    public function test_paso_5_mapa_de_la_cartera_filtrando_por_zona_muestra_solo_las_de_esa_zona(): void
    {
        $this->actingAs($this->admin());
        $norte = $this->propiedad(['titulo' => 'Casa Norte']);
        $norte->ubicacion->update(['zona' => 'Zona Norte']);
        $sur = $this->propiedad(['titulo' => 'Casa Sur']);
        $sur->ubicacion->update(['zona' => 'Zona Sur']);

        $this->get(route('propiedades.mapa'))->assertOk()->assertSee('Casa Norte')->assertSee('Casa Sur');
        $this->get(route('propiedades.mapa', ['zona' => 'Zona Norte']))->assertOk()
            ->assertSee('Casa Norte')->assertDontSee('Casa Sur')
            ->assertSee('<option value="Zona Sur"', false); // la lista conserva todas las zonas
        $this->get(route('propiedades.mapa', ['zona' => 'Inexistente']))->assertOk()->assertSee('No hay propiedades en esa zona.');
    }

    public function test_paso_6_un_agente_que_cambia_la_ubicacion_de_una_propiedad_de_otro_agente_recibe_403(): void
    {
        $propiedad = $this->propiedad([], $this->agente());
        $this->actingAs($this->agente());

        $this->get(route('propiedades.ubicacion', $propiedad))->assertForbidden();
        $this->guardar($propiedad, ['zona' => 'Hackeada'])->assertForbidden();
        $this->assertNotSame('Hackeada', $propiedad->fresh('ubicacion')->ubicacion->zona);
    }

    public function test_guardar_sin_cambios_no_crea_historial_pero_cambiar_solo_la_zona_si(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->actingAs($agente);

        // Mismos valores (la latitud escrita con menos decimales es el mismo punto)
        $this->guardar($propiedad, ['zona' => 'Equipetrol', 'direccion' => 'Av. San Martín', 'latitud' => '-17.763', 'longitud' => '-63.198'])
            ->assertSessionDoesntHaveErrors();
        $this->assertSame(0, Historial::count());
        $this->assertSame(0, Bitacora::where('accion', 'UBICACION_MODIFICADA')->count());

        $this->guardar($propiedad, ['zona' => 'Zona Nueva', 'direccion' => 'Av. San Martín', 'latitud' => '-17.763', 'longitud' => '-63.198']);
        $this->assertSame('Zona Nueva', $propiedad->fresh('ubicacion')->ubicacion->zona);
        $this->assertSame(1, Historial::count());
    }

    public function test_una_propiedad_sin_ubicacion_previa_la_crea_y_la_enlaza(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $propiedad->update(['idubicacion' => null]);
        $this->actingAs($agente);

        $this->get(route('propiedades.ubicacion', $propiedad))->assertOk();
        $this->guardar($propiedad)->assertSessionDoesntHaveErrors();

        $ubicacion = $propiedad->fresh('ubicacion')->ubicacion;
        $this->assertNotNull($ubicacion);
        $this->assertSame('Santa Cruz de la Sierra', $ubicacion->ciudad);
        $this->assertSame('Equipetrol Norte', $ubicacion->zona);
    }

    public function test_coordenadas_no_numericas_dan_error_de_validacion_y_no_500(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->actingAs($agente);

        $this->guardar($propiedad, ['latitud' => 'abc', 'longitud' => '12x'])
            ->assertSessionHasErrors(['latitud' => 'La latitud debe ser un número.', 'longitud' => 'La longitud debe ser un número.']);
        $this->guardar($propiedad, ['latitud' => '999', 'longitud' => '-63.19'])->assertSessionHasErrors('latitud');
        $this->put(route('propiedades.ubicacion.guardar', $propiedad), ['zona' => ['x'], 'latitud' => ['1']])->assertSessionHasErrors();
    }

    public function test_propiedad_dada_de_baja_o_inexistente_da_404_y_otros_roles_403(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('propiedades.ubicacion', $this->propiedad(['estadopropiedad' => Propiedad::BAJA])))->assertNotFound();
        $this->get('/propiedades/99999/ubicacion')->assertNotFound();

        $propiedad = $this->propiedad();
        $this->actingAs($this->cliente())->get(route('propiedades.ubicacion', $propiedad))->assertForbidden();
        $this->actingAs($this->asistente())->get(route('propiedades.mapa'))->assertForbidden();
    }

    public function test_el_mapa_no_muestra_propiedades_dadas_de_baja_ni_sin_ubicacion(): void
    {
        $this->actingAs($this->admin());
        $this->propiedad(['titulo' => 'De Baja', 'estadopropiedad' => Propiedad::BAJA]);
        $sin = $this->propiedad(['titulo' => 'Sin Punto']);
        $sin->update(['idubicacion' => null]);
        $this->propiedad(['titulo' => 'Con Punto']);

        $this->get(route('propiedades.mapa'))->assertOk()->assertSee('Con Punto')->assertDontSee('De Baja')->assertDontSee('Sin Punto');
    }

    public function test_la_propiedad_movida_se_ve_en_su_nueva_posicion_en_la_ficha_del_catalogo(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->actingAs($agente);

        $this->guardar($propiedad, ['latitud' => '-17.7800000', 'longitud' => '-63.1750000']);

        $this->get(route('catalogo.detalle', $propiedad))->assertOk()->assertSee('-17.78')->assertSee('-63.175');
    }

    public function test_el_inventario_muestra_ubicacion_y_mapa_de_la_cartera(): void
    {
        $agente = $this->agente();
        $propia = $this->propiedad([], $agente);
        $this->propiedad([], $this->agente());

        $html = $this->actingAs($agente)->get(route('propiedades.index'))->assertOk()
            ->assertSee(route('propiedades.mapa'), false)->assertSee(route('propiedades.ubicacion', $propia), false)->getContent();
        $this->assertSame(1, substr_count($html, '>Ubicación</a>'));
    }
}