<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Models\Propiedad;
use App\Models\Usuario;
use App\Models\Visita;
use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

/**
 * CU15: Reagendar visita. La fecha de las pruebas se fija en el martes 15 de diciembre de 2026, hora de Bolivia.
 */
class CU15_ReagendarVisitaTest extends SistemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-12-15 10:00:00', 'America/La_Paz'));
    }

    private function visita(Propiedad $propiedad, string $fecha = '2026-12-18', string $hora = '09:00:00', string $estado = Visita::PROGRAMADA): Visita
    {
        return Visita::create([
            'idpropiedad' => $propiedad->idpropiedad,
            'idcliente' => Cliente::create(['nombre' => 'Cliente ' . uniqid(), 'email' => uniqid() . '@correo.com'])->idcliente,
            'fecha' => $fecha, 'hora' => $hora, 'estado' => $estado,
        ]);
    }

    private function reagendar(Usuario $actor, Visita $visita, array $cambios = [])
    {
        return $this->actingAs($actor)->put(route('visitas.reagendar.guardar', $visita), array_merge([
            'fecha' => '2026-12-22', 'hora' => '11:00', 'motivo' => 'El cliente pidió otro día',
        ], $cambios));
    }

    // ============================================ paso 1: el agente reagenda una visita propia

    public function test_el_agente_reagenda_una_visita_propia_con_motivo_y_se_libera_el_horario_anterior(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $visita = $this->visita($propiedad, '2026-12-18', '09:00:00');

        $this->reagendar($ana, $visita)
            ->assertRedirect(route('visitas.calendario', ['fecha' => '2026-12-22']))
            ->assertSessionHas('status', 'La visita fue reagendada para el 22/12/2026 a las 11:00.');

        $visita->refresh();
        $this->assertSame(Visita::REAGENDADA, $visita->estado);
        $this->assertSame('2026-12-22', $visita->fecha->format('Y-m-d'));
        $this->assertSame('11:00', $visita->hora_corta);
        $this->assertSame('El cliente pidió otro día', $visita->motivo);
        $this->assertSame(1, Visita::count(), 'se actualiza la misma visita, no se crea otra');

        // El horario anterior queda libre y el nuevo, ocupado.
        $agenda = new AgendaDeVisitas();
        $this->assertFalse($agenda->ocupado($propiedad, '2026-12-18', '09:00'));
        $this->assertTrue($agenda->ocupado($propiedad, '2026-12-22', '11:00'));
    }

    public function test_el_calendario_se_actualiza_con_la_nueva_fecha(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad(['titulo' => 'Casa de Ana'], $ana), '2026-12-18');

        $this->reagendar($ana, $visita, ['fecha' => '2027-01-12']);

        $this->actingAs($ana)->get(route('visitas.calendario', ['fecha' => '2027-01-12']))
            ->assertSee('Casa de Ana')->assertSee('Reagendada');
        $this->actingAs($ana)->get(route('visitas.calendario', ['fecha' => '2026-12-18']))
            ->assertDontSee('Casa de Ana');
    }

    public function test_reagendar_queda_en_la_bitacora_con_el_motivo_y_los_horarios(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana), '2026-12-18', '09:00:00');

        $this->reagendar($ana, $visita);

        $registro = Bitacora::where('accion', 'VISITA_REAGENDADA')->firstOrFail();
        $this->assertSame('Agenda', $registro->modulo);
        $this->assertSame($ana->idusuario, $registro->idusuario);
        $this->assertSame('El cliente pidió otro día', $registro->detalle['motivo']);
        $this->assertSame('2026-12-18', $registro->detalle['fecha_anterior']);
        $this->assertSame('09:00', $registro->detalle['hora_anterior']);
        $this->assertSame('2026-12-22', $registro->detalle['fecha_nueva']);
        $this->assertSame('11:00', $registro->detalle['hora_nueva']);
        $this->assertSame(Visita::PROGRAMADA, $registro->detalle['estado_anterior']);
    }

    public function test_el_formulario_muestra_la_fecha_y_hora_actuales_y_los_campos_nuevos(): void
    {
        $ana = $this->agente(['nombre' => 'Ana Agente']);
        $visita = $this->visita($this->propiedad(['titulo' => 'Casa de Ana'], $ana), '2026-12-18', '14:00:00');

        $this->actingAs($ana)->get(route('visitas.reagendar', $visita))
            ->assertOk()
            ->assertSee('Reagendar visita')
            ->assertSee('18/12/2026')->assertSee('14:00')
            ->assertSee('Casa de Ana')->assertSee('Programada')
            ->assertSee('Nueva fecha')->assertSee('Nueva hora')->assertSee('Motivo del cambio')
            ->assertSee(route('visitas.reagendar.guardar', $visita), false);
    }

    public function test_una_visita_reagendada_se_puede_reagendar_otra_vez(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana), '2026-12-18', '09:00:00', Visita::REAGENDADA);

        $this->reagendar($ana, $visita, ['fecha' => '2026-12-23', 'hora' => '10:00', 'motivo' => 'Otra vez'])->assertSessionHas('status');

        $this->assertSame('2026-12-23', $visita->fresh()->fecha->format('Y-m-d'));
        $this->assertSame('Otra vez', $visita->fresh()->motivo);
    }

    public function test_se_puede_cambiar_solo_la_hora_el_mismo_dia(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana), '2026-12-18', '09:00:00');

        $this->reagendar($ana, $visita, ['fecha' => '2026-12-18', 'hora' => '15:00'])->assertSessionHas('status');

        $this->assertSame('15:00', $visita->fresh()->hora_corta);
    }

    // ============================================ paso 2: horario ocupado

    public function test_reagendar_a_un_horario_en_que_el_agente_ya_tiene_otra_visita_se_rechaza(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana), '2026-12-18', '09:00:00');
        $this->visita($this->propiedad([], $ana), '2026-12-22', '11:00:00'); // otra propiedad del mismo agente

        $this->from(route('visitas.reagendar', $visita))
            ->reagendar($ana, $visita)
            ->assertRedirect(route('visitas.reagendar', $visita))
            ->assertSessionHasErrors('hora');

        $mensaje = session('errors')->first('hora');
        $this->assertStringContainsString('ocupado', $mensaje);
        $this->assertStringNotContainsString('11:00', explode('Horarios libres', $mensaje)[1]);

        $visita->refresh();
        $this->assertSame(Visita::PROGRAMADA, $visita->estado);
        $this->assertSame('2026-12-18', $visita->fecha->format('Y-m-d'));
        $this->assertSame('09:00', $visita->hora_corta);
        $this->assertNull($visita->motivo);
        $this->assertSame(0, Bitacora::where('accion', 'VISITA_REAGENDADA')->count());
    }

    public function test_el_horario_de_otro_agente_o_de_una_visita_cancelada_no_cuenta_como_ocupado(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana), '2026-12-18', '09:00:00');
        $this->visita($this->propiedad([], $this->agente()), '2026-12-22', '11:00:00');                       // otro agente
        $this->visita($this->propiedad([], $ana), '2026-12-22', '12:00:00', Visita::CANCELADA);              // cancelada

        $this->reagendar($ana, $visita, ['hora' => '11:00'])->assertSessionHas('status');
        $visita->refresh();
        $this->reagendar($ana, $visita, ['hora' => '12:00'])->assertSessionHas('status');
    }

    public function test_si_la_propiedad_no_tiene_agente_el_cruce_se_mira_sobre_la_propiedad(): void
    {
        $admin = $this->admin();
        $propiedad = $this->propiedad();
        $visita = $this->visita($propiedad, '2026-12-18', '09:00:00');
        $this->visita($propiedad, '2026-12-22', '11:00:00');

        $this->reagendar($admin, $visita)->assertSessionHasErrors('hora');
    }

    public function test_el_formulario_marca_las_horas_ocupadas_sin_contar_la_propia_visita(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana), '2026-12-18', '09:00:00');
        $this->visita($this->propiedad([], $ana), '2026-12-18', '10:00:00');

        $html = $this->actingAs($ana)->get(route('visitas.reagendar', $visita))->getContent();

        $this->assertStringContainsString('"2026-12-18":["10:00"]', $html);
    }

    // ============================================ paso 3: fecha pasada o igual a la actual

    public function test_una_fecha_pasada_se_rechaza(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana));

        $this->reagendar($ana, $visita, ['fecha' => '2026-12-14'])
            ->assertSessionHasErrors(['fecha' => 'La nueva fecha y hora deben ser futuras.']);

        $this->assertSame(Visita::PROGRAMADA, $visita->fresh()->estado);
    }

    public function test_hoy_con_una_hora_que_ya_paso_se_rechaza_y_con_una_futura_se_acepta(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana));

        // Son las 10:00 en La Paz.
        $this->reagendar($ana, $visita, ['fecha' => '2026-12-15', 'hora' => '09:00'])->assertSessionHasErrors('fecha');
        $this->reagendar($ana, $visita, ['fecha' => '2026-12-15', 'hora' => '10:00'])->assertSessionHasErrors('fecha');
        $this->reagendar($ana, $visita, ['fecha' => '2026-12-15', 'hora' => '16:00'])->assertSessionHasNoErrors();
    }

    public function test_la_misma_fecha_y_hora_que_la_actual_se_rechaza(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana), '2026-12-18', '09:00:00');

        $this->reagendar($ana, $visita, ['fecha' => '2026-12-18', 'hora' => '09:00'])
            ->assertSessionHasErrors(['fecha' => 'Elige una fecha u hora distinta de la actual.']);

        $this->assertSame(Visita::PROGRAMADA, $visita->fresh()->estado);
        $this->assertNull($visita->fresh()->motivo);
    }

    public function test_la_hora_debe_estar_en_el_horario_de_atencion_y_la_fecha_tener_formato_valido(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana));

        foreach (['07:00', '18:00', '09:30', 'abc'] as $hora) {
            $this->reagendar($ana, $visita, ['hora' => $hora])->assertSessionHasErrors('hora');
        }
        $this->reagendar($ana, $visita, ['fecha' => '22/12/2026'])->assertSessionHasErrors('fecha');
    }

    // ============================================ paso 4: el motivo

    public function test_reagendar_sin_motivo_se_rechaza(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana));

        $this->reagendar($ana, $visita, ['motivo' => ''])
            ->assertSessionHasErrors(['motivo' => 'El motivo del cambio es obligatorio.']);
        $this->reagendar($ana, $visita, ['motivo' => '   '])->assertSessionHasErrors('motivo');
        $this->reagendar($ana, $visita, ['motivo' => 'ab'])->assertSessionHasErrors('motivo');
        $this->reagendar($ana, $visita, ['motivo' => str_repeat('x', 501)])->assertSessionHasErrors('motivo');

        $this->assertSame(Visita::PROGRAMADA, $visita->fresh()->estado);
    }

    public function test_los_campos_vacios_se_indican(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana));

        $this->actingAs($ana)->put(route('visitas.reagendar.guardar', $visita), [])
            ->assertSessionHasErrors([
                'fecha' => 'Elige la nueva fecha de la visita.',
                'hora' => 'Elige la nueva hora de la visita.',
                'motivo' => 'El motivo del cambio es obligatorio.',
            ]);
    }

    // ============================================ paso 5 y 6: permisos

    public function test_el_agente_no_puede_reagendar_la_visita_de_otro_agente(): void
    {
        $ana = $this->agente();
        $deBeto = $this->visita($this->propiedad([], $this->agente()));

        $this->actingAs($ana)->get(route('visitas.reagendar', $deBeto))->assertForbidden();
        $this->reagendar($ana, $deBeto)->assertForbidden();

        $deBeto->refresh();
        $this->assertSame(Visita::PROGRAMADA, $deBeto->estado);
        $this->assertSame('2026-12-18', $deBeto->fecha->format('Y-m-d'));
        $this->assertSame(0, Bitacora::where('accion', 'VISITA_REAGENDADA')->count());
    }

    public function test_el_administrador_reagenda_la_visita_de_cualquier_agente(): void
    {
        $visita = $this->visita($this->propiedad([], $this->agente()));
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('visitas.reagendar', $visita))->assertOk();
        $this->reagendar($admin, $visita)->assertSessionHas('status');

        $this->assertSame(Visita::REAGENDADA, $visita->fresh()->estado);
        $this->assertSame($admin->idusuario, Bitacora::where('accion', 'VISITA_REAGENDADA')->firstOrFail()->idusuario);
    }

    public function test_el_asistente_el_cliente_y_el_invitado_no_entran(): void
    {
        $visita = $this->visita($this->propiedad([], $this->agente()));

        $this->get(route('visitas.reagendar', $visita))->assertRedirect(route('login'));
        $this->put(route('visitas.reagendar.guardar', $visita), [])->assertRedirect(route('login'));

        foreach ([$this->asistente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario)->get(route('visitas.reagendar', $visita))->assertForbidden();
            $this->reagendar($usuario, $visita)->assertForbidden();
        }
        $this->assertSame(Visita::PROGRAMADA, $visita->fresh()->estado);
    }

    public function test_una_visita_inexistente_da_404(): void
    {
        $this->actingAs($this->admin())->get('/agenda/visitas/9999/reagendar')->assertNotFound();
        $this->actingAs($this->admin())->put('/agenda/visitas/9999/reagendar', [])->assertNotFound();
    }

    // ============================================ paso 7: canceladas y realizadas

    public function test_una_visita_cancelada_o_realizada_no_se_puede_reagendar(): void
    {
        $ana = $this->agente();

        foreach ([Visita::CANCELADA => 'cancelada', Visita::REALIZADA => 'realizada'] as $estado => $texto) {
            $visita = $this->visita($this->propiedad([], $ana), '2026-12-18', '09:00:00', $estado);

            $this->actingAs($ana)->get(route('visitas.reagendar', $visita))
                ->assertRedirect(route('visitas.show', $visita))
                ->assertSessionHas('error', "La visita no puede reagendarse porque está {$texto}.");

            $this->reagendar($ana, $visita)
                ->assertRedirect(route('visitas.show', $visita))
                ->assertSessionHas('error');

            $visita->refresh();
            $this->assertSame($estado, $visita->estado);
            $this->assertSame('2026-12-18', $visita->fecha->format('Y-m-d'));
        }
        $this->assertSame(0, Bitacora::where('accion', 'VISITA_REAGENDADA')->count());
    }

    // ============================================ errores y entrada desde el detalle

    public function test_un_error_de_la_base_de_datos_avisa_y_no_cambia_la_visita(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana));
        Visita::updating(function () {
            throw new QueryException('sqlite', 'update visita', [], new \Exception('conexión perdida'));
        });

        $this->reagendar($ana, $visita)
            ->assertSessionHas('error', 'No se pudo reagendar la visita por un problema con la base de datos. Intenta de nuevo en unos minutos.');

        $this->assertSame(Visita::PROGRAMADA, $visita->fresh()->estado);
    }

    public function test_el_detalle_de_la_visita_enlaza_a_reagendar(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana));

        $this->actingAs($ana)->get(route('visitas.show', $visita))
            ->assertSee('Reagendar')
            ->assertSee(route('visitas.reagendar', $visita), false);
    }

    public function test_cancelar_el_proceso_vuelve_al_detalle_sin_cambiar_nada(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana));

        $this->actingAs($ana)->get(route('visitas.reagendar', $visita))
            ->assertSee('Cancelar')
            ->assertSee(route('visitas.show', $visita), false);

        $this->assertSame(Visita::PROGRAMADA, $visita->fresh()->estado);
    }

    public function test_el_panel_lista_el_cu15_para_el_agente_y_el_administrador(): void
    {
        foreach ([$this->agente(), $this->admin()] as $usuario) {
            $this->actingAs($usuario)->get(route('panel'))->assertSee('Reagendar visita');
        }
        $this->actingAs($this->asistente())->get(route('panel'))->assertDontSee('Reagendar visita');
    }
}
