<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Historial;
use App\Models\Imagen;
use App\Models\Propiedad;
use App\Models\Propietario;
use App\Models\Ubicacion;

/** CU5: Registrar propiedad (Administrador o Agente). */
class CU5_RegistrarPropiedadTest extends SistemaTestCase
{
    private function registrar(array $datos = [], ?array $imagenes = null)
    {
        return $this->post(route('propiedades.store'), array_merge(
            $this->datosPropiedad($datos),
            ['imagenes' => $imagenes ?? [$this->imagen('uno.jpg')]]
        ));
    }

    private function nadaSeGuardo(): void
    {
        $this->assertSame(0, Propiedad::count());
        $this->assertSame(0, Ubicacion::count());
        $this->assertSame(0, Imagen::count());
        $this->assertSame(0, Propietario::count());
        $this->assertSame(0, Historial::count());
    }

    // ------------------------------------------------------------------- acceso

    public function test_el_invitado_es_enviado_al_login(): void
    {
        $this->get(route('propiedades.create'))->assertRedirect(route('login'));
        $this->registrar()->assertRedirect(route('login'));
        $this->assertSame(0, Propiedad::count());
    }

    public function test_el_asistente_y_el_cliente_no_pueden_registrar(): void
    {
        foreach ([$this->asistente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario);
            $this->get(route('propiedades.create'))->assertForbidden();
            $this->registrar()->assertForbidden();
        }

        $this->assertSame(0, Propiedad::count());
    }

    public function test_el_administrador_y_el_agente_ven_el_formulario(): void
    {
        $this->categoria('Departamento');

        foreach ([$this->admin(), $this->agente()] as $usuario) {
            $this->actingAs($usuario)->get(route('propiedades.create'))
                ->assertOk()
                ->assertSee('Registrar propiedad')
                ->assertSee('Departamento')
                ->assertSee('Precio (USD $)')
                ->assertSee('4.º anillo');
        }
    }

    // ------------------------------------------------------- registro correcto

    public function test_el_agente_registra_una_propiedad_completa(): void
    {
        $agente = $this->agente();

        $this->actingAs($agente);
        $this->registrar()
            ->assertRedirect(route('propiedades.index'))
            ->assertSessionHas('status', 'Propiedad registrada correctamente.');

        $propiedad = Propiedad::with(['ubicacion', 'propietario', 'categoria'])->first();

        $this->assertNotNull($propiedad);
        $this->assertSame('Casa familiar en Equipetrol', $propiedad->titulo);
        $this->assertSame('Casa con jardín y garaje.', $propiedad->descripcion);
        $this->assertSame('95000.50', (string) $propiedad->precio);
        $this->assertSame('Venta', $propiedad->tipopropiedad);
        $this->assertEquals(200, $propiedad->superficie);
        $this->assertEquals(150, $propiedad->areaconstruida);
        $this->assertSame(3, (int) $propiedad->habitaciones);
        $this->assertSame(2, (int) $propiedad->banos);
        $this->assertSame(5, (int) $propiedad->antiguedad);
        $this->assertSame('Casa', $propiedad->categoria->nombre);
        $this->assertSame($agente->idusuario, $propiedad->idusuario, 'queda a cargo del agente que la registra');
    }

    public function test_la_propiedad_nueva_queda_disponible_aunque_se_intente_otro_estado(): void
    {
        $this->actingAs($this->agente());

        $this->registrar(['estadopropiedad' => Propiedad::VENDIDO]);

        $this->assertSame(Propiedad::DISPONIBLE, Propiedad::first()->estadopropiedad);
    }

    public function test_tambien_el_administrador_puede_registrar(): void
    {
        $this->actingAs($this->admin());

        $this->registrar()->assertRedirect(route('propiedades.index'));

        $this->assertSame(1, Propiedad::count());
    }

    public function test_se_crea_la_ubicacion_con_la_ciudad_y_las_coordenadas(): void
    {
        $this->actingAs($this->agente());

        $this->registrar(['zona' => 'Equipetrol', 'direccion' => 'Av. San Martín #100']);

        $ubicacion = Propiedad::first()->ubicacion;
        $this->assertSame('Santa Cruz de la Sierra', $ubicacion->ciudad);
        $this->assertSame('Equipetrol', $ubicacion->zona);
        $this->assertSame('Av. San Martín #100', $ubicacion->direccion);
        $this->assertEqualsWithDelta(-17.763, (float) $ubicacion->latitud, 0.0001);
        $this->assertEqualsWithDelta(-63.198, (float) $ubicacion->longitud, 0.0001);
    }

    public function test_con_propietario_nuevo_se_crea_el_propietario(): void
    {
        $this->actingAs($this->agente());

        $this->registrar();

        $propietario = Propietario::first();
        $this->assertSame(1, Propietario::count());
        $this->assertSame('Nuevo Propietario', $propietario->nombre);
        $this->assertSame('70011223', $propietario->telefono);
        $this->assertSame('nuevo@test.com', $propietario->email);
        $this->assertSame($propietario->idpropietario, Propiedad::first()->idpropietario);
    }

    public function test_con_propietario_existente_no_se_crea_otro(): void
    {
        $existente = $this->propietario('Patricia Soliz');
        $this->actingAs($this->agente());

        $this->registrar(['idpropietario' => $existente->idpropietario, 'propietario_nombre' => null]);

        $this->assertSame(1, Propietario::count());
        $this->assertSame($existente->idpropietario, Propiedad::first()->idpropietario);
    }

    public function test_los_datos_opcionales_pueden_quedar_vacios(): void
    {
        $this->actingAs($this->agente());

        $this->registrar([
            'descripcion' => '', 'superficie' => '', 'areaconstruida' => '',
            'habitaciones' => '', 'banos' => '', 'antiguedad' => '',
            'propietario_telefono' => '', 'propietario_email' => '', 'propietario_direccion' => '',
        ])->assertSessionHasNoErrors();

        $propiedad = Propiedad::first();
        $this->assertNull($propiedad->descripcion);
        $this->assertNull($propiedad->superficie);
        $this->assertNull($propiedad->habitaciones);
    }

    public function test_se_aceptan_los_tres_tipos_de_operacion(): void
    {
        $this->actingAs($this->agente());

        foreach (['Venta', 'Alquiler', 'Anticrético'] as $tipo) {
            $this->registrar(['tipopropiedad' => $tipo])->assertSessionHasNoErrors();
        }

        $this->assertSame(['Alquiler', 'Anticrético', 'Venta'], Propiedad::orderBy('tipopropiedad')->pluck('tipopropiedad')->all());
    }

    // ------------------------------------------------------------------ imágenes

    public function test_las_imagenes_se_guardan_en_disco_y_en_la_base_de_datos(): void
    {
        $this->actingAs($this->agente());

        $this->registrar([], [$this->imagen('sala.jpg'), $this->imagen('cocina.png')]);

        $propiedad = Propiedad::first();
        $imagenes = $propiedad->imagenes;

        $this->assertCount(2, $imagenes);
        foreach ($imagenes as $imagen) {
            $this->assertStringStartsWith('uploads/propiedades/' . $propiedad->idpropiedad . '/', $imagen->ruta);
            $this->assertFileExists($this->rutaFisica($imagen->ruta));
        }
        $this->assertEqualsCanonicalizing(['sala.jpg', 'cocina.png'], $imagenes->pluck('nombre')->all());
    }

    public function test_la_primera_imagen_es_la_portada_por_defecto(): void
    {
        $this->actingAs($this->agente());

        $this->registrar([], [$this->imagen('a.jpg'), $this->imagen('b.jpg'), $this->imagen('c.jpg')]);

        $imagenes = Propiedad::first()->imagenes()->orderBy('idimagen')->get();
        $this->assertSame([true, false, false], $imagenes->pluck('portada')->all());
    }

    public function test_se_puede_elegir_otra_imagen_como_portada(): void
    {
        $this->actingAs($this->agente());

        $this->registrar(['portada' => 'nueva-1'], [$this->imagen('a.jpg'), $this->imagen('b.jpg'), $this->imagen('c.jpg')]);

        $imagenes = Propiedad::first()->imagenes()->orderBy('idimagen')->get();
        $this->assertSame([false, true, false], $imagenes->pluck('portada')->all());
        $this->assertSame(1, $imagenes->where('portada', true)->count(), 'solo una portada');
    }

    public function test_si_la_seleccion_de_portada_no_existe_se_usa_la_primera(): void
    {
        $this->actingAs($this->agente());

        $this->registrar(['portada' => 'nueva-9'], [$this->imagen('a.jpg'), $this->imagen('b.jpg')]);

        $this->assertSame([true, false], Propiedad::first()->imagenes()->orderBy('idimagen')->pluck('portada')->all());
    }

    public function test_se_exige_al_menos_una_imagen(): void
    {
        $this->actingAs($this->agente());

        $this->post(route('propiedades.store'), $this->datosPropiedad())
            ->assertSessionHasErrors(['imagenes' => 'Debes subir al menos una imagen de la propiedad.']);

        $this->nadaSeGuardo();
    }

    public function test_el_maximo_es_8_imagenes(): void
    {
        $this->actingAs($this->agente());

        $nueve = array_map(fn ($i) => $this->imagen("f{$i}.jpg"), range(1, 9));
        $this->registrar([], $nueve)->assertSessionHasErrors('imagenes');
        $this->nadaSeGuardo();

        $ocho = array_map(fn ($i) => $this->imagen("g{$i}.jpg"), range(1, 8));
        $this->registrar([], $ocho)->assertSessionHasNoErrors();
        $this->assertSame(8, Imagen::count());
    }

    public function test_solo_se_aceptan_imagenes_jpg_png_o_webp(): void
    {
        $this->actingAs($this->agente());

        $pdf = \Illuminate\Http\UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf');
        $this->registrar([], [$pdf])->assertSessionHasErrors('imagenes.0');

        $gif = \Illuminate\Http\UploadedFile::fake()->image('animada.gif');
        $this->registrar([], [$gif])->assertSessionHasErrors('imagenes.0');

        $this->nadaSeGuardo();
    }

    public function test_cada_imagen_pesa_como_maximo_4_mb(): void
    {
        $this->actingAs($this->agente());

        $this->registrar([], [$this->imagen('grande.jpg', 4097)])->assertSessionHasErrors('imagenes.0');
        $this->registrar([], [$this->imagen('justa.jpg', 4096)])->assertSessionHasNoErrors();
    }

    // --------------------------------------------------------- 4.º anillo (alcance)

    public function test_una_ubicacion_fuera_del_cuarto_anillo_se_rechaza(): void
    {
        $this->actingAs($this->agente());

        $this->registrar(['latitud' => self::LAT_FUERA, 'longitud' => self::LNG_FUERA])
            ->assertSessionHasErrors(['latitud' => 'La ubicación debe estar dentro del 4.º anillo de Santa Cruz de la Sierra.']);

        $this->nadaSeGuardo();
    }

    public function test_una_ubicacion_en_otra_ciudad_se_rechaza(): void
    {
        $this->actingAs($this->agente());

        // La Paz
        $this->registrar(['latitud' => '-16.5000000', 'longitud' => '-68.1500000'])->assertSessionHasErrors('latitud');

        $this->nadaSeGuardo();
    }

    public function test_el_centro_de_la_ciudad_es_valido(): void
    {
        $this->actingAs($this->agente());

        $this->registrar(['latitud' => '-17.7834', 'longitud' => '-63.1821'])->assertSessionHasNoErrors();

        $this->assertSame(1, Propiedad::count());
    }

    public function test_las_coordenadas_son_obligatorias_y_numericas(): void
    {
        $this->actingAs($this->agente());

        $this->registrar(['latitud' => '', 'longitud' => ''])->assertSessionHasErrors(['latitud', 'longitud']);
        $this->registrar(['latitud' => 'abc', 'longitud' => 'xyz'])->assertSessionHasErrors(['latitud', 'longitud']);
        $this->registrar(['latitud' => '-95', 'longitud' => '-63'])->assertSessionHasErrors('latitud');

        $this->nadaSeGuardo();
    }

    // ------------------------------------------------------------- validaciones

    public function test_los_campos_obligatorios(): void
    {
        $this->actingAs($this->agente());

        $this->post(route('propiedades.store'), [])->assertSessionHasErrors([
            'titulo', 'precio', 'tipopropiedad', 'idcategoria', 'zona', 'direccion',
            'latitud', 'longitud', 'propietario_nombre', 'imagenes',
        ]);

        $this->nadaSeGuardo();
    }

    public function test_el_precio_debe_ser_un_numero_positivo(): void
    {
        $this->actingAs($this->agente());

        foreach (['0', '-5', 'abc', '100000000'] as $precio) {
            $this->registrar(['precio' => $precio])->assertSessionHasErrors('precio');
        }

        $this->nadaSeGuardo();
    }

    public function test_el_tipo_de_operacion_y_la_categoria_deben_ser_validos(): void
    {
        $this->actingAs($this->agente());

        $this->registrar(['tipopropiedad' => 'Permuta', 'idcategoria' => 9999])
            ->assertSessionHasErrors(['tipopropiedad', 'idcategoria']);
    }

    public function test_el_propietario_existente_debe_existir(): void
    {
        $this->actingAs($this->agente());

        $this->registrar(['idpropietario' => 9999])->assertSessionHasErrors('idpropietario');
    }

    public function test_sin_propietario_elegido_hay_que_escribir_el_nombre_del_nuevo(): void
    {
        $this->actingAs($this->agente());

        $this->registrar(['propietario_nombre' => ''])->assertSessionHasErrors('propietario_nombre');
        $this->registrar(['propietario_email' => 'no-es-correo'])->assertSessionHasErrors('propietario_email');

        $this->nadaSeGuardo();
    }

    public function test_los_numeros_no_pueden_ser_negativos(): void
    {
        $this->actingAs($this->agente());

        $this->registrar(['superficie' => '-1', 'habitaciones' => '-2', 'banos' => '-1', 'antiguedad' => '-3'])
            ->assertSessionHasErrors(['superficie', 'habitaciones', 'banos', 'antiguedad']);

        $this->registrar(['habitaciones' => '2.5'])->assertSessionHasErrors('habitaciones');
    }

    public function test_el_titulo_no_puede_pasar_de_255_caracteres(): void
    {
        $this->actingAs($this->agente());

        $this->registrar(['titulo' => str_repeat('a', 256)])->assertSessionHasErrors('titulo');
    }

    public function test_si_la_validacion_falla_el_formulario_conserva_lo_escrito(): void
    {
        $this->actingAs($this->agente());

        $this->from(route('propiedades.create'))
            ->post(route('propiedades.store'), array_merge($this->datosPropiedad(['titulo' => 'Mi casa']), ['precio' => '-1']))
            ->assertRedirect(route('propiedades.create'))
            ->assertSessionHasInput('titulo', 'Mi casa');
    }

    // --------------------------------------------------- historial y bitácora

    public function test_el_registro_deja_historial_con_el_estado_inicial(): void
    {
        $agente = $this->agente();
        $this->actingAs($agente);

        $this->registrar();

        $propiedad = Propiedad::first();
        $historial = Historial::where('idpropiedad', $propiedad->idpropiedad)->get();

        $this->assertCount(1, $historial);
        $registro = $historial->first();
        $this->assertSame(Historial::REGISTRO, $registro->tipo);
        $this->assertNull($registro->valoranterior);
        $this->assertSame($agente->idusuario, $registro->idusuario);

        $actual = json_decode($registro->valoractual, true);
        $this->assertSame('Casa familiar en Equipetrol', $actual['titulo']);
        $this->assertSame('DISPONIBLE', $actual['estado']);
        $this->assertSame('Equipetrol', $actual['zona']);
    }

    public function test_el_registro_queda_en_la_bitacora(): void
    {
        $agente = $this->agente();
        $this->actingAs($agente);

        $this->registrar();

        $registro = Bitacora::where('accion', 'PROPIEDAD_REGISTRADA')->first();
        $this->assertNotNull($registro);
        $this->assertSame($agente->idusuario, $registro->idusuario);
        $this->assertSame('Propiedades', $registro->modulo);
        $this->assertStringContainsString('Casa familiar en Equipetrol', $registro->descripcion);
        $this->assertSame(Propiedad::first()->idpropiedad, $registro->detalle['idpropiedad']);
    }

    public function test_la_propiedad_registrada_aparece_en_el_inventario(): void
    {
        $this->actingAs($this->agente());
        $this->registrar();

        $this->get(route('propiedades.index'))
            ->assertOk()
            ->assertSee('Casa familiar en Equipetrol')
            ->assertSee('Disponible')
            ->assertSee('95,001'); // $ 95,000.50 redondeado a entero en el listado
    }
}
