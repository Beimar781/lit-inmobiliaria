<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Models\Interes;
use App\Models\Propiedad;
use App\Models\Visita;
use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

/**
 * CU14: Agendar visita a inmueble.
 * El cliente público agenda sin iniciar sesión desde la ficha; el Agente o el Administrador lo hacen en nombre de un cliente.
 */
class CU14_AgendarVisitaTest extends SistemaTestCase
{
    private function fechaFutura(int $dias = 3): string
    {
        return AgendaDeVisitas::ahora()->addDays($dias)->toDateString();
    }

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'fecha' => $this->fechaFutura(),
            'hora' => '09:00',
            'nombre' => 'Ana Rojas',
            'email' => 'ana@correo.com',
            'telefono' => '70011223',
            'observacion' => 'Prefiero por la tarde',
        ], $cambios);
    }

    private function agendar(Propiedad $propiedad, array $cambios = [])
    {
        return $this->post(route('visitas.store', $propiedad), $this->datos($cambios));
    }

    // ============================================ paso 1: cliente público

    public function test_el_cliente_publico_agenda_una_visita_sin_iniciar_sesion(): void
    {
        $agente = $this->agente(['nombre' => 'Carla Torres']);
        $propiedad = $this->propiedad(['titulo' => 'Casa en Equipetrol'], $agente);

        $respuesta = $this->agendar($propiedad);

        $visita = Visita::firstOrFail();
        $respuesta->assertRedirect();
        $this->assertStringContainsString("/visitas/{$visita->idvisita}/confirmacion", $respuesta->headers->get('Location'));
        $this->assertSame($propiedad->idpropiedad, $visita->idpropiedad);
        $this->assertSame(Visita::PROGRAMADA, $visita->estado);
        $this->assertSame($this->fechaFutura(), $visita->fecha->toDateString());
        $this->assertSame('09:00', $visita->hora_corta);
        $this->assertSame('Prefiero por la tarde', $visita->observacion);

        // Queda registrado como prospecto NUEVO del agente de la propiedad, con su interés.
        $cliente = Cliente::firstOrFail();
        $this->assertSame($cliente->idcliente, $visita->idcliente);
        $this->assertSame(Cliente::NUEVO, $cliente->estadoseguimiento);
        $this->assertSame($agente->idusuario, $cliente->idusuario);
        $this->assertSame(1, Interes::where('idcliente', $cliente->idcliente)->where('idpropiedad', $propiedad->idpropiedad)->count());

        // Paso 8: la confirmación muestra el resumen.
        $this->get($respuesta->headers->get('Location'))
            ->assertOk()
            ->assertSee('Visita agendada')
            ->assertSee('Casa en Equipetrol')
            ->assertSee(date('d/m/Y', strtotime($this->fechaFutura())))
            ->assertSee('09:00')
            ->assertSee('Programada')
            ->assertSee('Ana Rojas')
            ->assertSee('Carla Torres');
    }

    public function test_la_confirmacion_solo_se_abre_con_el_enlace_firmado(): void
    {
        $visita = Visita::create([
            'idpropiedad' => $this->propiedad()->idpropiedad,
            'idcliente' => Cliente::create(['nombre' => 'Ana', 'email' => 'a@a.com'])->idcliente,
            'fecha' => $this->fechaFutura(), 'hora' => '09:00:00',
        ]);

        $this->get("/visitas/{$visita->idvisita}/confirmacion")->assertForbidden();
    }

    public function test_el_formulario_muestra_los_datos_de_la_propiedad_y_pide_los_datos_de_contacto(): void
    {
        $propiedad = $this->propiedad(['titulo' => 'Casa del parque'], $this->agente());

        $this->get(route('visitas.create', $propiedad))
            ->assertOk()
            ->assertSee('Casa del parque')
            ->assertSee($propiedad->codigo)
            ->assertSee('Fecha')->assertSee('Hora')
            ->assertSee('Nombre completo')->assertSee('Correo electrónico')->assertSee('Teléfono')
            ->assertSee('Confirmar visita')
            ->assertDontSee('Cliente del CRM');
    }

    // ============================================ paso 2 y 3: cruces de horario

    public function test_el_mismo_dia_y_hora_de_otra_visita_del_agente_se_rechaza_e_informa_los_horarios_libres(): void
    {
        $agente = $this->agente();
        $casa = $this->propiedad(['titulo' => 'Casa A'], $agente);
        $depto = $this->propiedad(['titulo' => 'Depto B'], $agente); // otra propiedad del mismo agente
        $this->agendar($casa)->assertRedirect();

        $this->from(route('visitas.create', $depto))
            ->agendar($depto, ['nombre' => 'Otra Persona', 'email' => 'otra@correo.com', 'telefono' => '72200000'])
            ->assertRedirect(route('visitas.create', $depto))
            ->assertSessionHasErrors('hora');

        $this->assertStringContainsString('ocupado', session('errors')->first('hora'));
        $this->assertStringNotContainsString('09:00', explode('Horarios libres', session('errors')->first('hora'))[1]);
        $this->assertSame(1, Visita::count());
        $this->assertNull(Cliente::where('email', 'otra@correo.com')->first(), 'no debe crear al cliente si el horario está ocupado');
    }

    public function test_una_hora_distinta_del_mismo_agente_se_registra(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->agendar($propiedad)->assertRedirect();

        $this->agendar($propiedad, ['hora' => '10:00', 'nombre' => 'Luis Mamani', 'email' => 'luis@correo.com', 'telefono' => '71100000'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Visita::count());
    }

    public function test_el_mismo_horario_con_otro_agente_se_permite(): void
    {
        $this->agendar($this->propiedad([], $this->agente()))->assertRedirect();

        $this->agendar($this->propiedad([], $this->agente()), ['nombre' => 'Luis', 'email' => 'luis@correo.com', 'telefono' => '71100000'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Visita::count());
    }

    public function test_las_visitas_canceladas_y_realizadas_no_ocupan_horario_pero_las_reagendadas_si(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $cliente = Cliente::create(['nombre' => 'Previo', 'email' => 'previo@correo.com']);
        $fecha = $this->fechaFutura();

        foreach (['09:00:00' => Visita::CANCELADA, '10:00:00' => Visita::REALIZADA, '11:00:00' => Visita::REAGENDADA] as $hora => $estado) {
            Visita::create(['idpropiedad' => $propiedad->idpropiedad, 'idcliente' => $cliente->idcliente, 'fecha' => $fecha, 'hora' => $hora, 'estado' => $estado]);
        }

        $this->agendar($propiedad, ['hora' => '09:00'])->assertSessionHasNoErrors();
        $this->agendar($propiedad, ['hora' => '10:00'])->assertSessionHasNoErrors();
        $this->agendar($propiedad, ['hora' => '11:00'])->assertSessionHasErrors('hora');
    }

    public function test_si_la_propiedad_no_tiene_agente_el_cruce_se_mira_sobre_la_propiedad(): void
    {
        $sinAgente = $this->propiedad();
        $otraSinAgente = $this->propiedad();
        $this->agendar($sinAgente)->assertRedirect();

        $this->agendar($sinAgente, ['nombre' => 'Luis', 'email' => 'luis@correo.com', 'telefono' => '71100000'])->assertSessionHasErrors('hora');
        $this->agendar($otraSinAgente, ['nombre' => 'Luis', 'email' => 'luis@correo.com', 'telefono' => '71100000'])->assertSessionHasNoErrors();
    }

    public function test_el_formulario_marca_las_horas_ocupadas_del_agente(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->agendar($propiedad)->assertRedirect();

        $html = $this->get(route('visitas.create', $propiedad))->getContent();
        $this->assertStringContainsString('"' . $this->fechaFutura() . '":["09:00"]', $html);
    }

    // ============================================ paso 4: fecha y hora

    public function test_una_fecha_pasada_se_rechaza(): void
    {
        $propiedad = $this->propiedad([], $this->agente());

        $this->agendar($propiedad, ['fecha' => AgendaDeVisitas::ahora()->subDay()->toDateString()])
            ->assertSessionHasErrors(['fecha' => 'La fecha y la hora de la visita deben ser futuras.']);

        $this->assertSame(0, Visita::count());
        $this->assertSame(0, Cliente::count());
    }

    public function test_hoy_se_acepta_solo_si_la_hora_todavia_no_paso_en_la_hora_de_bolivia(): void
    {
        $propiedad = $this->propiedad([], $this->agente());
        // 12:00 en La Paz (16:00 UTC)
        $this->travelTo(CarbonImmutable::parse('2026-12-10 12:00:00', 'America/La_Paz'));

        $this->agendar($propiedad, ['fecha' => '2026-12-10', 'hora' => '09:00'])->assertSessionHasErrors('fecha');
        $this->agendar($propiedad, ['fecha' => '2026-12-10', 'hora' => '12:00'])->assertSessionHasErrors('fecha');
        $this->agendar($propiedad, ['fecha' => '2026-12-10', 'hora' => '17:00'])->assertSessionHasNoErrors();
        $this->assertSame(1, Visita::count());
    }

    public function test_la_hora_debe_estar_en_el_horario_de_atencion_en_bloques_de_una_hora(): void
    {
        $propiedad = $this->propiedad([], $this->agente());

        foreach (['07:00', '18:00', '09:30', '25:00', 'abc'] as $hora) {
            $this->agendar($propiedad, ['hora' => $hora])->assertSessionHasErrors('hora');
        }
        $this->assertSame(0, Visita::count());
    }

    public function test_la_primera_y_la_ultima_hora_del_horario_de_atencion_se_aceptan(): void
    {
        $propiedad = $this->propiedad([], $this->agente());

        foreach (['08:00' => 'primera', '17:00' => 'ultima'] as $hora => $quien) {
            $this->agendar($propiedad, ['hora' => $hora, 'nombre' => "Persona {$quien}", 'email' => "{$quien}@correo.com", 'telefono' => ''])->assertSessionHasNoErrors();
        }
        $this->assertSame(2, Visita::count());
    }

    public function test_una_fecha_con_formato_invalido_se_rechaza(): void
    {
        $this->agendar($this->propiedad(), ['fecha' => '31/12/2030'])->assertSessionHasErrors('fecha');
    }

    // ============================================ paso 5: campos obligatorios

    public function test_los_campos_obligatorios_vacios_se_rechazan_y_se_indican(): void
    {
        $propiedad = $this->propiedad([], $this->agente());

        $this->post(route('visitas.store', $propiedad), [])
            ->assertSessionHasErrors([
                'fecha' => 'Elige la fecha de la visita.',
                'hora' => 'Elige la hora de la visita.',
                'nombre' => 'Escribe el nombre.',
                'email' => 'Indica un correo electrónico o un teléfono de contacto.',
                'telefono' => 'Indica un correo electrónico o un teléfono de contacto.',
            ]);

        $this->assertSame(0, Visita::count());
    }

    public function test_alcanza_con_el_correo_o_con_el_telefono(): void
    {
        $propiedad = $this->propiedad([], $this->agente());

        $this->agendar($propiedad, ['email' => '', 'telefono' => '70011223'])->assertSessionHasNoErrors();
        $this->agendar($propiedad, ['hora' => '10:00', 'nombre' => 'Otro', 'email' => 'otro@correo.com', 'telefono' => ''])->assertSessionHasNoErrors();
        $this->assertSame(2, Visita::count());
    }

    public function test_el_correo_y_el_telefono_deben_tener_formato_valido(): void
    {
        $this->agendar($this->propiedad(), ['email' => 'no-es-correo', 'telefono' => 'abc'])
            ->assertSessionHasErrors(['email', 'telefono']);
    }

    // ============================================ paso 6: cliente existente

    public function test_un_cliente_con_el_mismo_correo_se_reutiliza(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $existente = Cliente::create(['nombre' => 'Ana R.', 'email' => 'ana@correo.com', 'estadoseguimiento' => Cliente::CONTACTADO, 'idusuario' => $agente->idusuario]);

        $this->agendar($propiedad, ['email' => 'ANA@correo.com', 'telefono' => ''])->assertSessionHasNoErrors();

        $this->assertSame(1, Cliente::count());
        $visita = Visita::firstOrFail();
        $this->assertSame($existente->idcliente, $visita->idcliente);
        $this->assertSame(Cliente::CONTACTADO, $existente->fresh()->estadoseguimiento, 'no se pisa el seguimiento del prospecto');
    }

    public function test_un_cliente_con_el_mismo_telefono_se_reutiliza_aunque_traiga_el_prefijo_de_bolivia(): void
    {
        $existente = Cliente::create(['nombre' => 'Ana', 'telefono' => '70011223']);

        $this->agendar($this->propiedad([], $this->agente()), ['email' => '', 'telefono' => '+591 70011223'])->assertSessionHasNoErrors();

        $this->assertSame(1, Cliente::count());
        $this->assertSame($existente->idcliente, Visita::firstOrFail()->idcliente);
    }

    public function test_el_mismo_cliente_puede_agendar_dos_visitas_sin_duplicar_su_interes(): void
    {
        $propiedad = $this->propiedad([], $this->agente());

        $this->agendar($propiedad)->assertSessionHasNoErrors();
        $this->agendar($propiedad, ['hora' => '11:00'])->assertSessionHasNoErrors();

        $this->assertSame(1, Cliente::count());
        $this->assertSame(1, Interes::count());
        $this->assertSame(2, Visita::count());
    }

    // ============================================ paso 7: propiedad no disponible

    public function test_una_propiedad_reservada_no_se_puede_visitar(): void
    {
        $propiedad = $this->propiedad(['estadopropiedad' => Propiedad::RESERVADO], $this->agente());

        $this->get(route('visitas.create', $propiedad))
            ->assertRedirect(route('catalogo.detalle', $propiedad))
            ->assertSessionHas('error', 'Esta propiedad ya no está disponible.');

        $this->agendar($propiedad)
            ->assertRedirect(route('catalogo.detalle', $propiedad))
            ->assertSessionHas('error', 'Esta propiedad ya no está disponible.');

        $this->assertSame(0, Visita::count());
        $this->assertSame(0, Cliente::count());
    }

    public function test_las_propiedades_vendidas_o_alquiladas_tampoco(): void
    {
        foreach ([Propiedad::VENDIDO, Propiedad::ALQUILADO] as $estado) {
            $this->agendar($this->propiedad(['estadopropiedad' => $estado]))->assertSessionHas('error');
        }
        $this->assertSame(0, Visita::count());
    }

    public function test_una_propiedad_dada_de_baja_o_inexistente_da_404(): void
    {
        $baja = $this->propiedad(['estadopropiedad' => Propiedad::BAJA]);

        $this->get(route('visitas.create', $baja))->assertNotFound();
        $this->agendar($baja)->assertNotFound();
        $this->get('/catalogo/9999/agendar-visita')->assertNotFound();
        $this->assertSame(0, Visita::count());
    }

    // ============================================ paso 8: el agente en nombre de un cliente

    public function test_el_agente_agenda_en_nombre_de_un_prospecto_del_crm(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $prospecto = Cliente::create(['nombre' => 'Marta Vaca', 'email' => 'marta@correo.com', 'idusuario' => $agente->idusuario]);

        $this->actingAs($agente)->get(route('visitas.create', ['propiedad' => $propiedad, 'cliente' => $prospecto->idcliente]))
            ->assertOk()
            ->assertSee('Cliente del CRM')
            ->assertSee('Marta Vaca');

        $this->actingAs($agente)->post(route('visitas.store', $propiedad), [
            'fecha' => $this->fechaFutura(), 'hora' => '14:00', 'idcliente' => $prospecto->idcliente,
        ])->assertSessionHasNoErrors();

        $visita = Visita::firstOrFail();
        $this->assertSame($prospecto->idcliente, $visita->idcliente);
        $this->assertSame($propiedad->idpropiedad, $visita->idpropiedad);
        $this->assertSame($agente->idusuario, $visita->propiedad->idusuario);
        $this->assertSame(Visita::PROGRAMADA, $visita->estado);
        $this->assertSame(1, Cliente::count());
    }

    public function test_el_agente_puede_agendar_para_un_cliente_nuevo_escribiendo_sus_datos(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);

        $this->actingAs($agente)->agendar($propiedad)->assertSessionHasNoErrors();

        $this->assertSame('Ana Rojas', Visita::firstOrFail()->cliente->nombre);
    }

    public function test_el_agente_solo_puede_elegir_sus_propios_prospectos(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $ajeno = Cliente::create(['nombre' => 'De otro', 'email' => 'otro@correo.com', 'idusuario' => $this->agente()->idusuario]);

        $this->actingAs($agente)->get(route('visitas.create', $propiedad))->assertDontSee('De otro');

        $this->actingAs($agente)->post(route('visitas.store', $propiedad), [
            'fecha' => $this->fechaFutura(), 'hora' => '09:00', 'idcliente' => $ajeno->idcliente,
        ])->assertSessionHasErrors(['idcliente' => 'Ese cliente no está entre tus prospectos.']);

        $this->assertSame(0, Visita::count());
    }

    public function test_el_administrador_agenda_para_cualquier_cliente(): void
    {
        $propiedad = $this->propiedad([], $this->agente());
        $cliente = Cliente::create(['nombre' => 'De un agente', 'email' => 'x@correo.com', 'idusuario' => $this->agente()->idusuario]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('visitas.create', $propiedad))->assertSee('De un agente');
        $this->actingAs($admin)->post(route('visitas.store', $propiedad), [
            'fecha' => $this->fechaFutura(), 'hora' => '09:00', 'idcliente' => $cliente->idcliente,
        ])->assertSessionHasNoErrors();

        $this->assertSame($cliente->idcliente, Visita::firstOrFail()->idcliente);
    }

    public function test_un_cliente_del_crm_que_no_existe_se_rechaza(): void
    {
        $agente = $this->agente();

        $this->actingAs($agente)->post(route('visitas.store', $this->propiedad([], $agente)), [
            'fecha' => $this->fechaFutura(), 'hora' => '09:00', 'idcliente' => 9999,
        ])->assertSessionHasErrors('idcliente');
    }

    public function test_el_asistente_y_el_cliente_con_sesion_ven_el_formulario_publico(): void
    {
        $propiedad = $this->propiedad([], $this->agente());

        foreach ([$this->asistente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario)->get(route('visitas.create', $propiedad))
                ->assertOk()
                ->assertDontSee('Cliente del CRM');
        }
    }

    // ============================================ entradas desde otras pantallas

    public function test_la_ficha_del_catalogo_ofrece_agendar_visita_solo_si_esta_disponible(): void
    {
        $disponible = $this->propiedad();
        $reservada = $this->propiedad(['estadopropiedad' => Propiedad::RESERVADO]);

        $this->get(route('catalogo.detalle', $disponible))
            ->assertOk()
            ->assertSee('Agendar visita')
            ->assertSee(route('visitas.create', $disponible), false);

        $this->get(route('catalogo.detalle', $reservada))
            ->assertOk()
            ->assertDontSee(route('visitas.create', $reservada), false);
    }

    public function test_el_inventario_ofrece_agendar_visita_en_las_propiedades_disponibles(): void
    {
        $agente = $this->agente();
        $disponible = $this->propiedad([], $agente);
        $vendida = $this->propiedad(['estadopropiedad' => Propiedad::VENDIDO], $agente);

        $this->actingAs($agente)->get(route('propiedades.index'))
            ->assertOk()
            ->assertSee(route('visitas.create', $disponible), false)
            ->assertDontSee(route('visitas.create', $vendida), false);
    }

    public function test_el_detalle_del_prospecto_ofrece_agendar_visita_con_el_cliente_elegido(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $prospecto = Cliente::create(['nombre' => 'Marta', 'email' => 'marta@correo.com', 'idusuario' => $agente->idusuario]);
        Interes::create(['idpropiedad' => $propiedad->idpropiedad, 'idcliente' => $prospecto->idcliente]);

        $this->actingAs($agente)->get(route('prospectos.show', $prospecto))
            ->assertOk()
            ->assertSee(route('visitas.create', ['propiedad' => $propiedad, 'cliente' => $prospecto->idcliente]), false);
    }

    public function test_el_panel_lista_el_cu14_para_el_agente_y_el_administrador(): void
    {
        foreach ([$this->agente(), $this->admin()] as $usuario) {
            $this->actingAs($usuario)->get(route('panel'))->assertSee('Agendar visita a inmueble');
        }
        $this->actingAs($this->asistente())->get(route('panel'))->assertDontSee('Agendar visita a inmueble');
    }

    // ============================================ bitácora, protección y errores

    public function test_agendar_una_visita_queda_en_la_bitacora(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);

        $this->agendar($propiedad);

        $registro = Bitacora::where('accion', 'VISITA_PROGRAMADA')->firstOrFail();
        $this->assertSame('Agenda', $registro->modulo);
        $this->assertNull($registro->idusuario, 'el cliente público no tiene usuario');
        $this->assertSame(Visita::firstOrFail()->idvisita, $registro->detalle['idvisita']);
        $this->assertSame($agente->idusuario, $registro->detalle['idagente']);
    }

    public function test_si_el_agente_agenda_la_bitacora_guarda_quien_fue(): void
    {
        $agente = $this->agente();

        $this->actingAs($agente)->agendar($this->propiedad([], $agente));

        $this->assertSame($agente->idusuario, Bitacora::where('accion', 'VISITA_PROGRAMADA')->firstOrFail()->idusuario);
    }

    public function test_hay_un_limite_de_envios_por_minuto(): void
    {
        $propiedad = $this->propiedad([], $this->agente());

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('visitas.store', $propiedad), [])->assertSessionHasErrors();
        }
        $this->post(route('visitas.store', $propiedad), [])->assertStatus(429);
    }

    public function test_el_campo_trampa_para_robots_finge_exito_sin_guardar_nada(): void
    {
        $propiedad = $this->propiedad([], $this->agente());

        $this->agendar($propiedad, ['sitio_web' => 'http://spam.com'])
            ->assertRedirect(route('catalogo.detalle', $propiedad));

        $this->assertSame(0, Visita::count());
        $this->assertSame(0, Cliente::count());
    }

    public function test_un_error_de_la_base_de_datos_avisa_y_no_deja_datos_a_medias(): void
    {
        $propiedad = $this->propiedad([], $this->agente());
        Visita::creating(function () {
            throw new QueryException('sqlite', 'insert into visita', [], new \Exception('conexión perdida'));
        });

        $this->agendar($propiedad)
            ->assertSessionHas('error', 'No se pudo registrar la visita por un problema con la base de datos. Intenta de nuevo en unos minutos.');

        $this->assertSame(0, Visita::count());
        $this->assertSame(0, Cliente::count(), 'el cliente se deshace junto con la visita');
    }

    public function test_cancelar_el_proceso_vuelve_a_la_ficha_sin_guardar_nada(): void
    {
        $propiedad = $this->propiedad([], $this->agente());

        $this->get(route('visitas.create', $propiedad))
            ->assertSee('Cancelar')
            ->assertSee(route('catalogo.detalle', $propiedad), false);

        $this->assertSame(0, Visita::count());
    }

    // ============================================ servicio de agenda

    public function test_las_horas_de_atencion_van_de_8_a_17_cada_hora(): void
    {
        $this->assertSame(
            ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'],
            AgendaDeVisitas::horas()
        );
    }

    public function test_las_horas_libres_descuentan_las_ocupadas_del_agente(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->agendar($propiedad, ['hora' => '10:00']);

        $libres = (new AgendaDeVisitas())->horasLibres($propiedad, $this->fechaFutura());

        $this->assertNotContains('10:00', $libres);
        $this->assertCount(9, $libres);
    }
}
