<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Models\Propiedad;
use App\Models\Usuario;
use App\Models\Visita;
use Carbon\CarbonImmutable;

/**
 * CU17: Ver calendario de visitas.
 * La fecha de las pruebas se fija en el 15 de diciembre de 2026 (martes), hora de Bolivia.
 */
class CU17_VerCalendarioVisitasTest extends SistemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-12-15 10:00:00', 'America/La_Paz'));
    }

    private function visita(Propiedad $propiedad, string $fecha, string $hora = '09:00:00', string $estado = Visita::PROGRAMADA, ?Cliente $cliente = null, array $extra = []): Visita
    {
        return Visita::create(array_merge([
            'idpropiedad' => $propiedad->idpropiedad,
            'idcliente' => ($cliente ?? Cliente::create(['nombre' => 'Cliente ' . uniqid(), 'email' => uniqid() . '@correo.com']))->idcliente,
            'fecha' => $fecha,
            'hora' => $hora,
            'estado' => $estado,
        ], $extra));
    }

    private function conVisitas(): array
    {
        $ana = $this->agente(['nombre' => 'Ana Agente']);
        $beto = $this->agente(['nombre' => 'Beto Agente']);
        $casaAna = $this->propiedad(['titulo' => 'Casa de Ana'], $ana);
        $casaBeto = $this->propiedad(['titulo' => 'Casa de Beto'], $beto);

        return [
            $ana, $beto,
            $this->visita($casaAna, '2026-12-10', '09:00:00', Visita::PROGRAMADA, Cliente::create(['nombre' => 'Clara Cliente'])),
            $this->visita($casaAna, '2026-12-16', '15:00:00', Visita::CANCELADA, Cliente::create(['nombre' => 'Carlos Cancelado'])),
            $this->visita($casaBeto, '2026-12-17', '10:00:00', Visita::REAGENDADA, Cliente::create(['nombre' => 'Berta Cliente'])),
        ];
    }

    // ============================================ paso 1 y 2: quién ve qué

    public function test_el_agente_ve_solo_sus_visitas_con_fecha_hora_propiedad_cliente_y_estado(): void
    {
        [$ana] = $this->conVisitas();

        $this->actingAs($ana)->get(route('visitas.calendario'))
            ->assertOk()
            ->assertSee('Diciembre 2026')
            ->assertSee('09:00')->assertSee('Casa de Ana')->assertSee('Clara Cliente')->assertSee('Programada')
            ->assertSee('15:00')->assertSee('Carlos Cancelado')->assertSee('Cancelada')
            ->assertDontSee('Casa de Beto')->assertDontSee('Berta Cliente');
    }

    public function test_el_administrador_ve_las_visitas_de_todos_los_agentes(): void
    {
        $this->conVisitas();

        $this->actingAs($this->admin())->get(route('visitas.calendario'))
            ->assertOk()
            ->assertSee('Casa de Ana')->assertSee('Casa de Beto')
            ->assertSee('Todos los agentes');
    }

    public function test_las_visitas_de_una_propiedad_sin_agente_solo_las_ve_el_administrador(): void
    {
        $this->visita($this->propiedad(['titulo' => 'Casa huérfana']), '2026-12-12');

        $this->actingAs($this->agente())->get(route('visitas.calendario'))->assertDontSee('Casa huérfana');
        $this->actingAs($this->admin())->get(route('visitas.calendario'))->assertSee('Casa huérfana');
    }

    // ============================================ paso 3: filtro por agente (administrador)

    public function test_el_administrador_filtra_el_calendario_por_agente(): void
    {
        [$ana, $beto] = $this->conVisitas();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('visitas.calendario', ['agente' => $beto->idusuario]))
            ->assertOk()
            ->assertSee('Casa de Beto')
            ->assertDontSee('Casa de Ana');

        $this->actingAs($admin)->get(route('visitas.calendario', ['agente' => $ana->idusuario]))
            ->assertSee('Casa de Ana')
            ->assertDontSee('Casa de Beto');
    }

    public function test_un_agente_que_pide_las_visitas_de_otro_agente_recibe_acceso_denegado(): void
    {
        [$ana, $beto] = $this->conVisitas();

        $this->actingAs($ana)->get(route('visitas.calendario', ['agente' => $beto->idusuario]))->assertForbidden();
        $this->actingAs($ana)->get(route('visitas.calendario', ['agente' => $ana->idusuario]))->assertOk()->assertSee('Casa de Ana');
    }

    public function test_el_agente_no_ve_el_selector_de_agentes_pero_el_administrador_si(): void
    {
        [$ana] = $this->conVisitas();

        $this->actingAs($ana)->get(route('visitas.calendario'))->assertDontSee('Todos los agentes');
        $this->actingAs($this->admin())->get(route('visitas.calendario'))->assertSee('Beto Agente');
    }

    // ============================================ paso 4: vista semanal

    public function test_la_vista_semanal_muestra_solo_las_visitas_de_esa_semana(): void
    {
        [$ana] = $this->conVisitas();

        // Semana del lunes 14 al domingo 20 de diciembre: está la del 16 y no la del 10.
        $this->actingAs($ana)->get(route('visitas.calendario', ['vista' => 'semana']))
            ->assertOk()
            ->assertSee('Semana del 14 de Diciembre al 20 de Diciembre de 2026')
            ->assertSee('Carlos Cancelado')
            ->assertDontSee('Clara Cliente');

        $this->actingAs($ana)->get(route('visitas.calendario', ['vista' => 'semana', 'fecha' => '2026-12-10']))
            ->assertSee('Semana del 7 de Diciembre al 13 de Diciembre de 2026')
            ->assertSee('Clara Cliente')
            ->assertDontSee('Carlos Cancelado');
    }

    public function test_la_vista_semanal_puede_cruzar_de_mes(): void
    {
        $ana = $this->agente();
        $this->visita($this->propiedad(['titulo' => 'Casa fin de año'], $ana), '2027-01-01');

        $this->actingAs($ana)->get(route('visitas.calendario', ['vista' => 'semana', 'fecha' => '2026-12-30']))
            ->assertSee('Semana del 28 de Diciembre al 3 de Enero de 2027')
            ->assertSee('Casa fin de año');
    }

    public function test_hay_botones_para_cambiar_entre_mes_y_semana(): void
    {
        $this->actingAs($this->agente())->get(route('visitas.calendario'))
            ->assertSee(e(route('visitas.calendario', ['vista' => 'semana', 'fecha' => '2026-12-15'])), false);

        $this->actingAs($this->agente())->get(route('visitas.calendario', ['vista' => 'semana', 'fecha' => '2026-12-15']))
            ->assertSee(e(route('visitas.calendario', ['fecha' => '2026-12-15'])), false);
    }

    // ============================================ navegación

    public function test_navega_al_mes_anterior_y_al_siguiente(): void
    {
        $ana = $this->agente();
        $casa = $this->propiedad([], $ana);
        $this->visita($casa, '2026-11-20', '09:00:00', Visita::PROGRAMADA, Cliente::create(['nombre' => 'Cliente de noviembre']));
        $this->visita($casa, '2027-01-20', '09:00:00', Visita::PROGRAMADA, Cliente::create(['nombre' => 'Cliente de enero']));

        $respuesta = $this->actingAs($ana)->get(route('visitas.calendario'))
            ->assertSee(e(route('visitas.calendario', ['fecha' => '2026-11-01'])), false)
            ->assertSee(e(route('visitas.calendario', ['fecha' => '2027-01-01'])), false);

        $this->actingAs($ana)->get(route('visitas.calendario', ['fecha' => '2026-11-01']))
            ->assertSee('Noviembre 2026')->assertSee('Cliente de noviembre')->assertDontSee('Cliente de enero');
        $this->actingAs($ana)->get(route('visitas.calendario', ['fecha' => '2027-01-01']))
            ->assertSee('Enero 2027')->assertSee('Cliente de enero')->assertDontSee('Cliente de noviembre');
    }

    public function test_navega_a_la_semana_anterior_y_a_la_siguiente(): void
    {
        $this->actingAs($this->agente())->get(route('visitas.calendario', ['vista' => 'semana']))
            ->assertSee(e(route('visitas.calendario', ['vista' => 'semana', 'fecha' => '2026-12-07'])), false)
            ->assertSee(e(route('visitas.calendario', ['vista' => 'semana', 'fecha' => '2026-12-21'])), false);
    }

    public function test_la_navegacion_conserva_los_filtros(): void
    {
        $this->actingAs($this->admin())->get(route('visitas.calendario', ['estado' => 'CANCELADA']))
            ->assertSee(e(route('visitas.calendario', ['estado' => 'CANCELADA', 'fecha' => '2027-01-01'])), false);
    }

    public function test_el_mes_muestra_las_semanas_completas_con_dias_de_los_meses_vecinos(): void
    {
        $ana = $this->agente();
        // El 30 de noviembre (lunes) aparece en la primera fila del calendario de diciembre.
        $this->visita($this->propiedad(['titulo' => 'Casa del 30'], $ana), '2026-11-30');

        $this->actingAs($ana)->get(route('visitas.calendario'))->assertSee('Casa del 30');
    }

    // ============================================ paso 5: filtro por estado

    public function test_filtra_por_estado_cancelada(): void
    {
        [$ana] = $this->conVisitas();

        $this->actingAs($ana)->get(route('visitas.calendario', ['estado' => 'CANCELADA']))
            ->assertOk()
            ->assertSee('Carlos Cancelado')
            ->assertDontSee('Clara Cliente');

        $this->actingAs($ana)->get(route('visitas.calendario', ['estado' => 'PROGRAMADA']))
            ->assertSee('Clara Cliente')->assertDontSee('Carlos Cancelado');
    }

    // ============================================ paso 6: mes sin visitas

    public function test_un_mes_sin_visitas_muestra_el_mensaje_informativo(): void
    {
        [$ana] = $this->conVisitas();

        $this->actingAs($ana)->get(route('visitas.calendario', ['fecha' => '2027-03-01']))
            ->assertOk()
            ->assertSee('Marzo 2027')
            ->assertSee('Sin visitas programadas');
    }

    public function test_si_hay_visitas_no_aparece_el_mensaje_de_vacio(): void
    {
        [$ana] = $this->conVisitas();

        $this->actingAs($ana)->get(route('visitas.calendario'))->assertDontSee('Sin visitas programadas');
    }

    public function test_un_filtro_sin_resultados_tambien_muestra_el_mensaje(): void
    {
        [$ana] = $this->conVisitas();

        $this->actingAs($ana)->get(route('visitas.calendario', ['estado' => 'REALIZADA']))->assertSee('Sin visitas programadas');
    }

    public function test_las_visitas_de_los_dias_vecinos_no_cuentan_para_el_mensaje_del_mes(): void
    {
        $ana = $this->agente();
        // El 30 de noviembre se ve en la cuadrícula de diciembre, pero diciembre sigue sin visitas propias.
        $this->visita($this->propiedad([], $ana), '2026-11-30');

        $this->actingAs($ana)->get(route('visitas.calendario'))->assertSee('Sin visitas programadas');
    }

    // ============================================ paso 7: detalle y acciones

    public function test_el_detalle_muestra_propiedad_cliente_contacto_fecha_hora_estado_y_observaciones(): void
    {
        $ana = $this->agente(['nombre' => 'Ana Agente']);
        $visita = $this->visita(
            $this->propiedad(['titulo' => 'Casa de Ana'], $ana), '2026-12-18', '14:00:00', Visita::PROGRAMADA,
            Cliente::create(['nombre' => 'Clara Cliente', 'email' => 'clara@correo.com', 'telefono' => '70011223']),
            ['observacion' => 'Prefiere la tarde']
        );

        $this->actingAs($ana)->get(route('visitas.show', $visita))
            ->assertOk()
            ->assertSee('Casa de Ana')->assertSee('Clara Cliente')
            ->assertSee('clara@correo.com')->assertSee('70011223')
            ->assertSee('18/12/2026')->assertSee('14:00')->assertSee('Programada')
            ->assertSee('Prefiere la tarde')->assertSee('Ana Agente')
            ->assertSee('Marcar como realizada');
    }

    public function test_el_detalle_ofrece_reagendar_y_cancelar_en_visitas_programadas_y_reagendadas(): void
    {
        $ana = $this->agente();
        $programada = $this->visita($this->propiedad([], $ana), '2026-12-18');
        $reagendada = $this->visita($this->propiedad([], $ana), '2026-12-19', '10:00:00', Visita::REAGENDADA);

        foreach ([$programada, $reagendada] as $visita) {
            $this->actingAs($ana)->get(route('visitas.show', $visita))
                ->assertSee('Reagendar')->assertSee('Cancelar visita')
                ->assertSee(route('visitas.reagendar', $visita), false)
                ->assertSee(route('visitas.cancelar', $visita), false);
        }
    }

    public function test_el_detalle_de_una_visita_cancelada_o_realizada_no_ofrece_acciones(): void
    {
        $ana = $this->agente();
        foreach ([Visita::CANCELADA, Visita::REALIZADA] as $i => $estado) {
            $visita = $this->visita($this->propiedad([], $ana), '2026-12-18', sprintf('%02d:00:00', 9 + $i), $estado);

            $this->actingAs($ana)->get(route('visitas.show', $visita))
                ->assertOk()
                ->assertDontSee('Marcar como realizada')
                ->assertDontSee('Reagendar')
                ->assertDontSee('Cancelar visita')
                ->assertSee('ya no tiene acciones disponibles');
        }
    }

    public function test_el_detalle_muestra_el_motivo_si_lo_hay(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana), '2026-12-18', '09:00:00', Visita::CANCELADA, null, ['motivo' => 'El cliente viajó']);

        $this->actingAs($ana)->get(route('visitas.show', $visita))->assertSee('El cliente viajó');
    }

    public function test_el_agente_no_puede_ver_el_detalle_de_una_visita_ajena_pero_el_administrador_si(): void
    {
        [$ana, $beto, , , $deBeto] = $this->conVisitas();

        $this->actingAs($ana)->get(route('visitas.show', $deBeto))->assertForbidden();
        $this->actingAs($beto)->get(route('visitas.show', $deBeto))->assertOk();
        $this->actingAs($this->admin())->get(route('visitas.show', $deBeto))->assertOk();
    }

    public function test_una_visita_inexistente_da_404(): void
    {
        $this->actingAs($this->admin())->get('/agenda/visitas/9999')->assertNotFound();
    }

    // ============================================ marcar como realizada

    public function test_el_agente_marca_como_realizada_una_visita_suya_y_queda_en_la_bitacora(): void
    {
        $ana = $this->agente();
        $visita = $this->visita($this->propiedad([], $ana), '2026-12-18', '09:00:00', Visita::REAGENDADA);

        $this->actingAs($ana)->patch(route('visitas.realizada', $visita))
            ->assertRedirect(route('visitas.show', $visita))
            ->assertSessionHas('status', 'La visita quedó marcada como realizada.');

        $this->assertSame(Visita::REALIZADA, $visita->fresh()->estado);
        $registro = Bitacora::where('accion', 'VISITA_REALIZADA')->firstOrFail();
        $this->assertSame($ana->idusuario, $registro->idusuario);
        $this->assertSame(Visita::REAGENDADA, $registro->detalle['estado_anterior']);
    }

    public function test_no_se_puede_marcar_como_realizada_una_visita_cancelada_o_ya_realizada(): void
    {
        $ana = $this->agente();
        foreach ([Visita::CANCELADA, Visita::REALIZADA] as $i => $estado) {
            $visita = $this->visita($this->propiedad([], $ana), '2026-12-18', sprintf('%02d:00:00', 9 + $i), $estado);

            $this->actingAs($ana)->patch(route('visitas.realizada', $visita))
                ->assertSessionHas('error');
            $this->assertSame($estado, $visita->fresh()->estado);
        }
        $this->assertSame(0, Bitacora::where('accion', 'VISITA_REALIZADA')->count());
    }

    public function test_un_agente_no_puede_marcar_como_realizada_la_visita_de_otro(): void
    {
        [$ana, , , , $deBeto] = $this->conVisitas();

        $this->actingAs($ana)->patch(route('visitas.realizada', $deBeto))->assertForbidden();
        $this->assertSame(Visita::REAGENDADA, $deBeto->fresh()->estado);

        $this->actingAs($this->admin())->patch(route('visitas.realizada', $deBeto))->assertRedirect();
        $this->assertSame(Visita::REALIZADA, $deBeto->fresh()->estado);
    }

    // ============================================ período o filtro no válido

    public function test_un_periodo_o_filtro_no_valido_avisa_y_usa_los_valores_por_defecto(): void
    {
        [$ana] = $this->conVisitas();

        $this->actingAs($ana)->get(route('visitas.calendario', ['fecha' => '2026-13-45']))
            ->assertOk()->assertSee('La fecha no es válida')->assertSee('Diciembre 2026');
        $this->actingAs($ana)->get(route('visitas.calendario', ['fecha' => 'ayer']))
            ->assertOk()->assertSee('La fecha no es válida');
        $this->actingAs($ana)->get(route('visitas.calendario', ['vista' => 'anio']))
            ->assertOk()->assertSee('La vista no es válida')->assertSee('Diciembre 2026');
        $this->actingAs($ana)->get(route('visitas.calendario', ['estado' => 'INVENTADO']))
            ->assertOk()->assertSee('El estado elegido no es válido')->assertSee('Clara Cliente');
        $this->actingAs($this->admin())->get(route('visitas.calendario', ['agente' => '9999']))
            ->assertOk()->assertSee('El agente elegido no es válido')->assertSee('Casa de Ana');
    }

    public function test_los_parametros_mal_formados_no_dan_error_500(): void
    {
        $ana = $this->agente();

        $this->actingAs($ana)->get('/agenda/visitas?fecha[]=x&estado[]=y&vista[]=z')->assertOk();
        $this->actingAs($this->admin())->get('/agenda/visitas?agente[]=1')->assertOk();
    }

    // ============================================ acceso por rol

    public function test_el_asistente_el_cliente_y_el_invitado_no_entran(): void
    {
        [, , $visita] = $this->conVisitas();

        $this->get(route('visitas.calendario'))->assertRedirect(route('login'));
        $this->get(route('visitas.show', $visita))->assertRedirect(route('login'));
        $this->patch(route('visitas.realizada', $visita))->assertRedirect(route('login'));

        foreach ([$this->asistente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario)->get(route('visitas.calendario'))->assertForbidden();
            $this->actingAs($usuario)->get(route('visitas.show', $visita))->assertForbidden();
            $this->actingAs($usuario)->patch(route('visitas.realizada', $visita))->assertForbidden();
        }
    }

    public function test_el_calendario_no_modifica_ningun_dato(): void
    {
        [$ana] = $this->conVisitas();
        $antes = Visita::orderBy('idvisita')->get(['idvisita', 'estado', 'fecha', 'hora'])->toArray();

        $this->actingAs($ana)->get(route('visitas.calendario'));
        $this->actingAs($ana)->get(route('visitas.calendario', ['vista' => 'semana', 'estado' => 'CANCELADA']));

        $this->assertSame($antes, Visita::orderBy('idvisita')->get(['idvisita', 'estado', 'fecha', 'hora'])->toArray());
        $this->assertSame(0, Bitacora::where('modulo', 'Agenda')->count());
    }

    // ============================================ panel y diseño

    public function test_el_panel_lista_el_cu17_para_el_agente_y_el_administrador(): void
    {
        foreach ([$this->agente(), $this->admin()] as $usuario) {
            $this->actingAs($usuario)->get(route('panel'))
                ->assertSee('Ver calendario de visitas')
                ->assertSee(route('visitas.calendario'), false);
        }
        $this->actingAs($this->asistente())->get(route('panel'))->assertDontSee('Ver calendario de visitas');
    }

    public function test_el_calendario_se_adapta_a_celular_y_el_dia_actual_esta_marcado(): void
    {
        $this->actingAs($this->agente())->get(route('visitas.calendario'))
            ->assertSee('@media (max-width: 767.98px)', false)
            ->assertSee('cal-dia hoy', false);
    }

    public function test_despues_de_agendar_el_personal_puede_ir_al_calendario_en_el_mes_de_la_visita(): void
    {
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);

        $respuesta = $this->actingAs($ana)->post(route('visitas.store', $propiedad), [
            'fecha' => '2027-02-10', 'hora' => '09:00', 'nombre' => 'Ana Rojas', 'email' => 'ana@correo.com',
        ]);

        $this->get($respuesta->headers->get('Location'))
            ->assertOk()
            ->assertSee(e(route('visitas.calendario', ['fecha' => '2027-02-10'])), false);
    }
}
