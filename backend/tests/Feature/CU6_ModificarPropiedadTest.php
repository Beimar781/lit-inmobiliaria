<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Historial;
use App\Models\Imagen;
use App\Models\Propiedad;
use App\Models\Propietario;

/** CU6: Modificar propiedad (Administrador o Agente). */
class CU6_ModificarPropiedadTest extends SistemaTestCase
{
    private function actualizar(Propiedad $propiedad, array $cambios = [], array $extra = [])
    {
        return $this->put(route('propiedades.update', $propiedad), array_merge($this->datosActuales($propiedad, $cambios), $extra));
    }

    // ------------------------------------------------------------------- acceso

    public function test_el_invitado_es_enviado_al_login(): void
    {
        $propiedad = $this->propiedad();

        $this->get(route('propiedades.edit', $propiedad))->assertRedirect(route('login'));
        $this->actualizar($propiedad, ['precio' => '1'])->assertRedirect(route('login'));
    }

    public function test_el_asistente_y_el_cliente_no_pueden_modificar(): void
    {
        $propiedad = $this->propiedad();

        foreach ([$this->asistente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario);
            $this->get(route('propiedades.edit', $propiedad))->assertForbidden();
            $this->actualizar($propiedad, ['precio' => '1'])->assertForbidden();
        }

        $this->assertSame('85000.00', (string) $propiedad->fresh()->precio);
    }

    public function test_el_administrador_y_el_agente_ven_el_formulario_con_los_datos_actuales(): void
    {
        $propiedad = $this->propiedad(['titulo' => 'Departamento Sirari', 'precio' => 120000]);

        foreach ([$this->admin(), $this->agente()] as $usuario) {
            $this->actingAs($usuario)->get(route('propiedades.edit', $propiedad))
                ->assertOk()
                ->assertSee('Modificar propiedad')
                ->assertSee('Departamento Sirari')
                ->assertSee('120000.00')
                ->assertSee('Equipetrol')
                ->assertSee('Estado de la propiedad');
        }
    }

    public function test_una_propiedad_inexistente_da_404(): void
    {
        $this->actingAs($this->agente());

        $this->get('/propiedades/9999/editar')->assertNotFound();
        $this->put('/propiedades/9999', [])->assertNotFound();
    }

    public function test_una_propiedad_dada_de_baja_ya_no_se_puede_modificar(): void
    {
        $baja = $this->propiedad(['estadopropiedad' => Propiedad::BAJA]);
        $this->actingAs($this->agente());

        $this->get(route('propiedades.edit', $baja))->assertNotFound();
        $this->actualizar($baja, ['estadopropiedad' => Propiedad::DISPONIBLE, 'precio' => '1'])->assertNotFound();

        $this->assertSame(Propiedad::BAJA, $baja->fresh()->estadopropiedad);
    }

    // --------------------------------------------------------------- modificación

    public function test_se_modifican_precio_y_descripcion(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->agente());

        $this->actualizar($propiedad, ['precio' => '99000', 'descripcion' => 'Nueva descripción'])
            ->assertRedirect(route('propiedades.index'))
            ->assertSessionHas('status', 'Propiedad actualizada correctamente.');

        $propiedad->refresh();
        $this->assertSame('99000.00', (string) $propiedad->precio);
        $this->assertSame('Nueva descripción', $propiedad->descripcion);
    }

    public function test_se_modifican_todos_los_datos_generales(): void
    {
        $propiedad = $this->propiedad();
        $otraCategoria = $this->categoria('Departamento');
        $this->actingAs($this->admin());

        $this->actualizar($propiedad, [
            'titulo' => 'Título nuevo',
            'tipopropiedad' => 'Alquiler',
            'superficie' => '300',
            'areaconstruida' => '250',
            'habitaciones' => '5',
            'banos' => '4',
            'antiguedad' => '1',
            'idcategoria' => $otraCategoria->idcategoria,
        ])->assertSessionHasNoErrors();

        $propiedad->refresh();
        $this->assertSame('Título nuevo', $propiedad->titulo);
        $this->assertSame('Alquiler', $propiedad->tipopropiedad);
        $this->assertEquals(300, $propiedad->superficie);
        $this->assertEquals(250, $propiedad->areaconstruida);
        $this->assertSame(5, (int) $propiedad->habitaciones);
        $this->assertSame(4, (int) $propiedad->banos);
        $this->assertSame(1, (int) $propiedad->antiguedad);
        $this->assertSame($otraCategoria->idcategoria, $propiedad->idcategoria);
    }

    public function test_se_cambia_el_estado_de_la_propiedad(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->agente());

        foreach ([Propiedad::RESERVADO, Propiedad::VENDIDO, Propiedad::ALQUILADO, Propiedad::DISPONIBLE] as $estado) {
            $this->actualizar($propiedad, ['estadopropiedad' => $estado])->assertSessionHasNoErrors();
            $this->assertSame($estado, $propiedad->fresh()->estadopropiedad);
        }
    }

    public function test_el_estado_baja_no_se_puede_elegir_aqui(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->admin());

        $this->actualizar($propiedad, ['estadopropiedad' => Propiedad::BAJA])->assertSessionHasErrors('estadopropiedad');
        $this->actualizar($propiedad, ['estadopropiedad' => 'INVENTADO'])->assertSessionHasErrors('estadopropiedad');

        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);
    }

    public function test_el_agente_a_cargo_no_cambia_al_modificar(): void
    {
        $original = $this->agente();
        $otro = $this->agente();
        $propiedad = $this->propiedad([], $original);

        $this->actingAs($otro);
        $this->actualizar($propiedad, ['precio' => '70000']);

        $this->assertSame($original->idusuario, $propiedad->fresh()->idusuario);
    }

    public function test_se_cambia_a_otro_propietario_existente(): void
    {
        $propiedad = $this->propiedad();
        $otro = $this->propietario('Patricia Soliz');
        $this->actingAs($this->agente());

        $this->actualizar($propiedad, ['idpropietario' => $otro->idpropietario])->assertSessionHasNoErrors();

        $this->assertSame($otro->idpropietario, $propiedad->fresh()->idpropietario);
    }

    public function test_se_puede_registrar_un_propietario_nuevo_al_modificar(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->agente());

        $this->actualizar($propiedad, [
            'idpropietario' => '',
            'propietario_nombre' => 'Dueño Nuevo',
            'propietario_telefono' => '71111111',
        ])->assertSessionHasNoErrors();

        $nuevo = Propietario::where('nombre', 'Dueño Nuevo')->first();
        $this->assertNotNull($nuevo);
        $this->assertSame($nuevo->idpropietario, $propiedad->fresh()->idpropietario);
    }

    // ------------------------------------------------------------------ ubicación

    public function test_se_actualiza_la_ubicacion_dentro_del_cuarto_anillo(): void
    {
        $propiedad = $this->propiedad();
        $idUbicacion = $propiedad->idubicacion;
        $this->actingAs($this->agente());

        $this->actualizar($propiedad, [
            'zona' => 'Las Palmas', 'direccion' => 'Av. Grigotá', 'latitud' => '-17.8040', 'longitud' => '-63.1760',
        ])->assertSessionHasNoErrors();

        $ubicacion = $propiedad->fresh()->ubicacion;
        $this->assertSame($idUbicacion, $ubicacion->idubicacion, 'se actualiza la misma ubicación');
        $this->assertSame('Las Palmas', $ubicacion->zona);
        $this->assertSame('Av. Grigotá', $ubicacion->direccion);
        $this->assertEqualsWithDelta(-17.804, (float) $ubicacion->latitud, 0.0001);
    }

    public function test_mover_la_propiedad_fuera_del_cuarto_anillo_se_rechaza_y_no_cambia_nada(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->agente());

        $this->actualizar($propiedad, [
            'precio' => '1', 'latitud' => self::LAT_FUERA, 'longitud' => self::LNG_FUERA,
        ])->assertSessionHasErrors('latitud');

        $propiedad->refresh();
        $this->assertSame('85000.00', (string) $propiedad->precio);
        $this->assertEqualsWithDelta(-17.763, (float) $propiedad->ubicacion->latitud, 0.0001);
    }

    // ------------------------------------------------------------- validaciones

    public function test_se_aplican_las_mismas_validaciones_que_al_registrar(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->agente());

        $this->actualizar($propiedad, [
            'titulo' => '', 'precio' => '-4', 'tipopropiedad' => 'Permuta', 'idcategoria' => 9999,
            'zona' => '', 'direccion' => '', 'estadopropiedad' => '',
        ])->assertSessionHasErrors(['titulo', 'precio', 'tipopropiedad', 'idcategoria', 'zona', 'direccion', 'estadopropiedad']);

        $this->assertSame('Casa de prueba', $propiedad->fresh()->titulo);
    }

    public function test_al_modificar_no_se_exigen_imagenes_nuevas(): void
    {
        $propiedad = $this->conImagenes($this->propiedad(), 1);
        $this->actingAs($this->agente());

        $this->actualizar($propiedad, ['precio' => '90000'])->assertSessionHasNoErrors();
    }

    // ---------------------------------------------------------------- historial

    public function test_el_cambio_deja_historial_solo_con_los_campos_que_cambiaron(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad();
        $this->actingAs($agente);

        $this->actualizar($propiedad, ['precio' => '99000', 'estadopropiedad' => Propiedad::RESERVADO]);

        $registro = Historial::where('idpropiedad', $propiedad->idpropiedad)->first();
        $this->assertNotNull($registro);
        $this->assertSame(Historial::MODIFICACION, $registro->tipo);
        $this->assertSame($agente->idusuario, $registro->idusuario);
        $this->assertSame(['precio' => '85000.00', 'estado' => 'DISPONIBLE'], json_decode($registro->valoranterior, true));
        $this->assertSame(['precio' => '99000.00', 'estado' => 'RESERVADO'], json_decode($registro->valoractual, true));
    }

    public function test_el_cambio_de_ubicacion_tambien_queda_en_el_historial(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->agente());

        $this->actualizar($propiedad, ['zona' => 'Sirari']);

        $registro = Historial::where('idpropiedad', $propiedad->idpropiedad)->first();
        $this->assertSame(['zona' => 'Equipetrol'], json_decode($registro->valoranterior, true));
        $this->assertSame(['zona' => 'Sirari'], json_decode($registro->valoractual, true));
    }

    public function test_guardar_sin_cambios_no_genera_historial_ni_bitacora(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->agente());

        $this->actualizar($propiedad)->assertRedirect(route('propiedades.index'));

        $this->assertSame(0, Historial::count());
        $this->assertSame(0, Bitacora::where('accion', 'PROPIEDAD_MODIFICADA')->count());
    }

    public function test_cada_modificacion_suma_un_registro_al_historial(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->agente());

        $this->actualizar($propiedad, ['precio' => '90000']);
        $this->actualizar($propiedad, ['precio' => '95000']);

        $this->assertSame(2, Historial::where('idpropiedad', $propiedad->idpropiedad)->count());
    }

    public function test_la_modificacion_queda_en_la_bitacora(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad();
        $this->actingAs($agente);

        $this->actualizar($propiedad, ['precio' => '99000']);

        $registro = Bitacora::where('accion', 'PROPIEDAD_MODIFICADA')->first();
        $this->assertNotNull($registro);
        $this->assertSame($agente->idusuario, $registro->idusuario);
        $this->assertSame('85000.00', $registro->detalle['antes']['precio']);
        $this->assertSame('99000.00', $registro->detalle['despues']['precio']);
        $this->assertSame(0, $registro->detalle['imagenes_agregadas']);
    }

    // ------------------------------------------------------------------ imágenes

    public function test_el_formulario_muestra_las_imagenes_guardadas(): void
    {
        $propiedad = $this->conImagenes($this->propiedad(), 2);
        $this->actingAs($this->agente());

        $this->get(route('propiedades.edit', $propiedad))
            ->assertOk()
            ->assertSee($propiedad->imagenes[0]->ruta)
            ->assertSee($propiedad->imagenes[1]->ruta)
            ->assertSee('Eliminar');
    }

    public function test_se_agregan_imagenes_nuevas(): void
    {
        $propiedad = $this->conImagenes($this->propiedad(), 1);
        $this->actingAs($this->agente());

        $this->actualizar($propiedad, [], ['imagenes' => [$this->imagen('nueva1.jpg'), $this->imagen('nueva2.jpg')]])
            ->assertSessionHasNoErrors();

        $imagenes = $propiedad->fresh('imagenes')->imagenes;
        $this->assertCount(3, $imagenes);
        foreach ($imagenes as $imagen) {
            $this->assertFileExists($this->rutaFisica($imagen->ruta));
        }
    }

    public function test_agregar_imagenes_queda_en_la_bitacora_aunque_no_cambien_los_datos(): void
    {
        $propiedad = $this->conImagenes($this->propiedad(), 1);
        $this->actingAs($this->agente());

        $this->actualizar($propiedad, [], ['imagenes' => [$this->imagen('nueva.jpg')]]);

        $registro = Bitacora::where('accion', 'PROPIEDAD_MODIFICADA')->first();
        $this->assertNotNull($registro);
        $this->assertSame(1, $registro->detalle['imagenes_agregadas']);
        $this->assertSame(0, Historial::count(), 'el historial solo guarda cambios de datos');
    }

    public function test_se_eliminan_imagenes_del_disco_y_de_la_base(): void
    {
        $propiedad = $this->conImagenes($this->propiedad(), 3);
        [$a, $b, $c] = $propiedad->imagenes->all();
        $this->actingAs($this->agente());

        $this->actualizar($propiedad, [], ['eliminar_imagenes' => [$b->idimagen]]);

        $this->assertDatabaseMissing('imagen', ['idimagen' => $b->idimagen]);
        $this->assertFileDoesNotExist($this->rutaFisica($b->ruta));
        $this->assertFileExists($this->rutaFisica($a->ruta));
        $this->assertFileExists($this->rutaFisica($c->ruta));
        $this->assertSame(2, Imagen::where('idpropiedad', $propiedad->idpropiedad)->count());
    }

    public function test_al_eliminar_la_portada_otra_imagen_pasa_a_ser_portada(): void
    {
        $propiedad = $this->conImagenes($this->propiedad(), 2);
        $portada = $propiedad->imagenes->firstWhere('portada', true);
        $otra = $propiedad->imagenes->firstWhere('portada', false);
        $this->actingAs($this->agente());

        $this->actualizar($propiedad, [], ['eliminar_imagenes' => [$portada->idimagen], 'portada' => 'img-' . $portada->idimagen]);

        $this->assertTrue($otra->fresh()->portada);
        $this->assertSame(1, Imagen::where('idpropiedad', $propiedad->idpropiedad)->where('portada', true)->count());
    }

    public function test_se_cambia_la_portada_a_otra_imagen_guardada(): void
    {
        $propiedad = $this->conImagenes($this->propiedad(), 3);
        $segunda = $propiedad->imagenes[1];
        $this->actingAs($this->agente());

        $this->actualizar($propiedad, [], ['portada' => 'img-' . $segunda->idimagen]);

        $imagenes = Imagen::where('idpropiedad', $propiedad->idpropiedad)->orderBy('idimagen')->get();
        $this->assertSame([false, true, false], $imagenes->pluck('portada')->all());

        $registro = Bitacora::where('accion', 'PROPIEDAD_MODIFICADA')->first();
        $this->assertNotNull($registro, 'cambiar la portada se registra');
        $this->assertTrue($registro->detalle['portada_cambiada']);
    }

    public function test_una_imagen_nueva_puede_ser_la_portada(): void
    {
        $propiedad = $this->conImagenes($this->propiedad(), 1);
        $this->actingAs($this->agente());

        $this->actualizar($propiedad, [], ['imagenes' => [$this->imagen('nueva.jpg')], 'portada' => 'nueva-0']);

        $portada = Imagen::where('idpropiedad', $propiedad->idpropiedad)->where('portada', true)->get();
        $this->assertCount(1, $portada);
        $this->assertSame('nueva.jpg', $portada->first()->nombre);
    }

    public function test_el_total_de_imagenes_no_puede_pasar_de_8(): void
    {
        $propiedad = $this->conImagenes($this->propiedad(), 8);
        $this->actingAs($this->agente());

        $this->actualizar($propiedad, [], ['imagenes' => [$this->imagen('extra.jpg')]])
            ->assertSessionHasErrors(['imagenes' => 'Una propiedad puede tener como máximo 8 imágenes.']);
        $this->assertSame(8, Imagen::where('idpropiedad', $propiedad->idpropiedad)->count());

        // Si elimina una a la vez que agrega otra, sí cabe
        $this->actualizar($propiedad, [], [
            'imagenes' => [$this->imagen('extra.jpg')],
            'eliminar_imagenes' => [$propiedad->imagenes->first()->idimagen],
        ])->assertSessionHasNoErrors();
        $this->assertSame(8, Imagen::where('idpropiedad', $propiedad->idpropiedad)->count());
    }

    public function test_los_archivos_que_no_son_imagen_se_rechazan(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->agente());

        $pdf = \Illuminate\Http\UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf');
        $this->actualizar($propiedad, [], ['imagenes' => [$pdf]])->assertSessionHasErrors('imagenes.0');
    }

    public function test_no_se_pueden_borrar_imagenes_de_otra_propiedad(): void
    {
        $mia = $this->conImagenes($this->propiedad(['titulo' => 'Mía']), 1);
        $ajena = $this->conImagenes($this->propiedad(['titulo' => 'Ajena']), 1);
        $imagenAjena = $ajena->imagenes->first();
        $this->actingAs($this->agente());

        $this->actualizar($mia, [], ['eliminar_imagenes' => [$imagenAjena->idimagen]]);

        $this->assertDatabaseHas('imagen', ['idimagen' => $imagenAjena->idimagen]);
        $this->assertFileExists($this->rutaFisica($imagenAjena->ruta));
    }

    public function test_la_seleccion_de_portada_debe_tener_formato_valido(): void
    {
        $propiedad = $this->conImagenes($this->propiedad(), 1);
        $this->actingAs($this->agente());

        $this->actualizar($propiedad, [], ['portada' => 'cualquier-cosa'])->assertSessionHasErrors('portada');
    }
}
