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
 * CU16: Cancelar visita. La fecha de las pruebas se fija en el martes 15 de diciembre de 2026, hora de Bolivia.
 */
class CU16_CancelarVisitaTest extends SistemaTestCase
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

    private function cancelar(Usuario $actor, Visita $visita, array $datos = ['accion' => 'cancelar', 'motivo' => 'El cliente ya no puede asistir'])
    {
        return $this->actingAs($actor)->put(route('visitas.cancelar.confirmar', $visita), $datos);
    }

    // ============================================ paso 1: motivo y confirmación

    public function test_cancelar_con_motivo_y_confirmacion_deja_la_visita_cancelada_y_en_el_historial(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana));

        $this->cancelar($ana, $visita)
            ->assertRedirect(route('visitas.calendario', ['fecha' => '2026-12-18']))
            ->assertSessionHas('status', 'La visita fue cancelada y se conserva en el historial. El horario quedó libre.');

        $this->assertSame(1, Visita::count(), 'no se borra');
        $visita->refresh();
        $this->assertSame(Visita::CANCELADA, $visita->estado);
        $this->assertSame('El cliente ya no puede asistir', $visita->motivo);
        $this->assertSame('2026-12-18', $visita->fecha->format('Y-m-d'));
        $this->assertSame('09:00', $visita->hora_corta);
    }

    public function test_la_visita_cancelada_sigue_en_el_calendario_y_se_filtra_por_estado(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad(['titulo' => 'Casa de Ana'], $ana));
        $this->cancelar($ana, $visita);

        $this->actingAs($ana)->get(route('visitas.calendario', ['estado' => 'CANCELADA']))
            ->assertSee('Casa de Ana')->assertSee('Cancelada');
    }

    public function test_cancelar_queda_en_la_bitacora_con_el_motivo(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana), '2026-12-18', '09:00:00', Visita::REAGENDADA);

        $this->cancelar($ana, $visita);

        $registro = Bitacora::where('accion', 'VISITA_CANCELADA')->firstOrFail();
        $this->assertSame('Agenda', $registro->modulo);
        $this->assertSame($ana->idusuario, $registro->idusuario);
        $this->assertSame('El cliente ya no puede asistir', $registro->detalle['motivo']);
        $this->assertSame(Visita::REAGENDADA, $registro->detalle['estado_anterior']);
        $this->assertSame($visita->idvisita, $registro->detalle['idvisita']);
    }

    public function test_una_visita_reagendada_tambien_se_puede_cancelar(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana), '2026-12-18', '09:00:00', Visita::REAGENDADA);

        $this->cancelar($ana, $visita)->assertSessionHas('status');

        $this->assertSame(Visita::CANCELADA, $visita->fresh()->estado);
    }

    public function test_la_pantalla_de_confirmacion_pide_el_motivo_y_ofrece_confirmar_o_desistir(): void
    {
        $ana = $this->agente(['nombre' => 'Ana Agente']);
        $visita = $this->visita($this->propiedad(['titulo' => 'Casa de Ana'], $ana), '2026-12-18', '14:00:00');

        $this->actingAs($ana)->get(route('visitas.cancelar', $visita))
            ->assertOk()
            ->assertSee('Cancelar visita')
            ->assertSee('Casa de Ana')->assertSee('18/12/2026')->assertSee('14:00')
            ->assertSee('Motivo de la cancelación')
            ->assertSee('Sí, cancelar la visita')->assertSee('No, mantener la visita')
            ->assertSee(route('visitas.cancelar.confirmar', $visita), false);
    }

    // ============================================ paso 2: el motivo es obligatorio

    public function test_cancelar_sin_motivo_se_rechaza(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana));

        $this->cancelar($ana, $visita, ['accion' => 'cancelar', 'motivo' => ''])
            ->assertSessionHasErrors(['motivo' => 'El motivo de la cancelación es obligatorio.']);
        $this->cancelar($ana, $visita, ['accion' => 'cancelar', 'motivo' => '   '])->assertSessionHasErrors('motivo');
        $this->cancelar($ana, $visita, ['accion' => 'cancelar', 'motivo' => 'ab'])->assertSessionHasErrors('motivo');
        $this->cancelar($ana, $visita, ['accion' => 'cancelar', 'motivo' => str_repeat('x', 501)])->assertSessionHasErrors('motivo');
        $this->cancelar($ana, $visita, ['accion' => 'cancelar'])->assertSessionHasErrors('motivo');

        $this->assertSame(Visita::PROGRAMADA, $visita->fresh()->estado);
        $this->assertNull($visita->fresh()->motivo);
        $this->assertSame(0, Bitacora::where('accion', 'VISITA_CANCELADA')->count());
    }

    // ============================================ paso 3: desistir

    public function test_desistir_en_la_confirmacion_deja_la_visita_sin_cambios(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana));

        $this->cancelar($ana, $visita, ['accion' => 'mantener', 'motivo' => 'Escribí un motivo pero me arrepentí'])
            ->assertRedirect(route('visitas.show', $visita))
            ->assertSessionHas('status', 'La visita se mantiene sin cambios.')
            ->assertSessionHasNoErrors();

        $visita->refresh();
        $this->assertSame(Visita::PROGRAMADA, $visita->estado);
        $this->assertNull($visita->motivo);
        $this->assertSame(0, Bitacora::where('accion', 'VISITA_CANCELADA')->count());
    }

    public function test_enviar_sin_confirmar_explicitamente_tampoco_cancela(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana));

        $this->cancelar($ana, $visita, ['motivo' => 'Sin botón de confirmar'])->assertRedirect(route('visitas.show', $visita));
        $this->cancelar($ana, $visita, [])->assertRedirect(route('visitas.show', $visita));

        $this->assertSame(Visita::PROGRAMADA, $visita->fresh()->estado);
    }

    // ============================================ paso 4 y 6: permisos

    public function test_el_agente_no_puede_cancelar_la_visita_de_otro_agente(): void
    {
        $ana = $this->agente();
        $deBeto = $this->visita($this->propiedad([], $this->agente()));

        $this->actingAs($ana)->get(route('visitas.cancelar', $deBeto))->assertForbidden();
        $this->cancelar($ana, $deBeto)->assertForbidden();
        $this->cancelar($ana, $deBeto, ['accion' => 'mantener'])->assertForbidden();

        $this->assertSame(Visita::PROGRAMADA, $deBeto->fresh()->estado);
        $this->assertSame(0, Bitacora::where('accion', 'VISITA_CANCELADA')->count());
    }

    public function test_el_administrador_cancela_la_visita_de_cualquier_agente(): void
    {
        $visita = $this->visita($this->propiedad([], $this->agente()));
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('visitas.cancelar', $visita))->assertOk();
        $this->cancelar($admin, $visita)->assertSessionHas('status');

        $this->assertSame(Visita::CANCELADA, $visita->fresh()->estado);
        $this->assertSame($admin->idusuario, Bitacora::where('accion', 'VISITA_CANCELADA')->firstOrFail()->idusuario);
    }

    public function test_el_asistente_el_cliente_y_el_invitado_no_entran(): void
    {
        $visita = $this->visita($this->propiedad([], $this->agente()));

        $this->get(route('visitas.cancelar', $visita))->assertRedirect(route('login'));
        $this->put(route('visitas.cancelar.confirmar', $visita), [])->assertRedirect(route('login'));

        foreach ([$this->asistente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario)->get(route('visitas.cancelar', $visita))->assertForbidden();
            $this->cancelar($usuario, $visita)->assertForbidden();
        }
        $this->assertSame(Visita::PROGRAMADA, $visita->fresh()->estado);
    }

    public function test_una_visita_inexistente_da_404(): void
    {
        $this->actingAs($this->admin())->get('/agenda/visitas/9999/cancelar')->assertNotFound();
        $this->actingAs($this->admin())->put('/agenda/visitas/9999/cancelar', [])->assertNotFound();
    }

    // ============================================ paso 5: ya cancelada o realizada

    public function test_una_visita_ya_cancelada_o_realizada_no_se_puede_cancelar(): void
    {
        $ana = $this->agente();

        foreach ([Visita::CANCELADA => 'cancelada', Visita::REALIZADA => 'realizada'] as $estado => $texto) {
            $visita = $this->visita($this->propiedad([], $ana), '2026-12-18', '09:00:00', $estado);

            $this->actingAs($ana)->get(route('visitas.cancelar', $visita))
                ->assertRedirect(route('visitas.show', $visita))
                ->assertSessionHas('error', "La visita no puede cancelarse porque ya está {$texto}.");

            $this->cancelar($ana, $visita)
                ->assertRedirect(route('visitas.show', $visita))
                ->assertSessionHas('error');

            $visita->refresh();
            $this->assertSame($estado, $visita->estado);
            $this->assertNull($visita->motivo, 'no se pisa el motivo anterior');
        }
        $this->assertSame(0, Bitacora::where('accion', 'VISITA_CANCELADA')->count());
    }

    // ============================================ paso 7: el horario liberado

    public function test_tras_cancelar_el_horario_liberado_permite_agendar_otra_visita(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $otra = $this->propiedad([], $ana);
        $visita = $this->visita($propiedad, '2026-12-18', '09:00:00');
        $datos = ['fecha' => '2026-12-18', 'hora' => '09:00', 'nombre' => 'Luis Mamani', 'email' => 'luis@correo.com', 'telefono' => '71100000'];

        // Antes de cancelar, el horario está ocupado.
        $this->post(route('visitas.store', $otra), $datos)->assertSessionHasErrors('hora');

        $this->cancelar($ana, $visita);

        $this->assertFalse((new AgendaDeVisitas())->ocupado($propiedad, '2026-12-18', '09:00'));
        $this->post(route('visitas.store', $otra), $datos)->assertSessionHasNoErrors();

        $this->assertSame(2, Visita::count());
        $this->assertSame(1, Visita::where('estado', Visita::PROGRAMADA)->count());
        $this->assertSame(Visita::CANCELADA, $visita->fresh()->estado, 'la cancelada se conserva');
    }

    public function test_el_formulario_de_agendar_ya_no_marca_como_ocupada_la_hora_liberada(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $visita = $this->visita($propiedad, '2026-12-18', '09:00:00');

        $this->assertStringContainsString('"2026-12-18":["09:00"]', $this->get(route('visitas.create', $propiedad))->getContent());

        $this->cancelar($ana, $visita);

        $this->assertStringNotContainsString('"2026-12-18"', $this->get(route('visitas.create', $propiedad))->getContent());
    }

    public function test_tras_cancelar_se_puede_reagendar_otra_visita_al_horario_liberado(): void
    {
        $ana = $this->agente();
        $cancelada = $this->visita($this->propiedad([], $ana), '2026-12-18', '09:00:00');
        $otra = $this->visita($this->propiedad([], $ana), '2026-12-19', '10:00:00');
        $this->cancelar($ana, $cancelada);

        $this->actingAs($ana)->put(route('visitas.reagendar.guardar', $otra), [
            'fecha' => '2026-12-18', 'hora' => '09:00', 'motivo' => 'Se liberó ese horario',
        ])->assertSessionHas('status');

        $this->assertSame('2026-12-18', $otra->fresh()->fecha->format('Y-m-d'));
    }

    // ============================================ errores y entrada desde el detalle

    public function test_un_error_de_la_base_de_datos_avisa_y_no_cancela(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana));
        Visita::updating(function () {
            throw new QueryException('sqlite', 'update visita', [], new \Exception('conexión perdida'));
        });

        $this->cancelar($ana, $visita)
            ->assertSessionHas('error', 'No se pudo cancelar la visita por un problema con la base de datos. Intenta de nuevo en unos minutos.');

        $this->assertSame(Visita::PROGRAMADA, $visita->fresh()->estado);
    }

    public function test_el_detalle_de_la_visita_enlaza_a_cancelar(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana));

        $this->actingAs($ana)->get(route('visitas.show', $visita))
            ->assertSee('Cancelar visita')
            ->assertSee(route('visitas.cancelar', $visita), false);
    }

    public function test_el_panel_lista_el_cu16_para_el_agente_y_el_administrador(): void
    {
        foreach ([$this->agente(), $this->admin()] as $usuario) {
            $this->actingAs($usuario)->get(route('panel'))->assertSee('Cancelar visita');
        }
        $this->actingAs($this->asistente())->get(route('panel'))->assertDontSee('Cancelar visita');
    }
}
