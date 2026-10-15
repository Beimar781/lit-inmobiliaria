<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Models\Interes;
use App\Models\Usuario;

/** CU13: Gestionar prospectos CRM (el Agente ve los suyos; el Administrador, todos). */
class CU13_GestionarProspectosTest extends SistemaTestCase
{
    private function prospecto(array $datos = [], ?Usuario $agente = null): Cliente
    {
        static $n = 0;
        $n++;

        return Cliente::create(array_merge([
            'nombre' => "Prospecto {$n}",
            'email' => "prospecto{$n}@correo.com",
            'telefono' => '7000' . str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'tipocliente' => 'Comprador',
            'estadoseguimiento' => Cliente::NUEVO,
            'idusuario' => $agente?->idusuario,
        ], $datos));
    }

    // ------------------------------------------------------------------- acceso

    public function test_el_invitado_es_enviado_al_login(): void
    {
        $cliente = $this->prospecto();

        $this->get(route('prospectos.index'))->assertRedirect(route('login'));
        $this->get(route('prospectos.show', $cliente))->assertRedirect(route('login'));
        $this->patch(route('prospectos.seguimiento', $cliente), ['estadoseguimiento' => Cliente::CONTACTADO])->assertRedirect(route('login'));
        $this->patch(route('prospectos.asignar', $cliente), ['idusuario' => 1])->assertRedirect(route('login'));
    }

    public function test_el_asistente_y_el_cliente_no_acceden_al_crm(): void
    {
        $cliente = $this->prospecto();

        foreach ([$this->asistente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario);
            $this->get(route('prospectos.index'))->assertForbidden();
            $this->get(route('prospectos.show', $cliente))->assertForbidden();
            $this->patch(route('prospectos.seguimiento', $cliente), ['estadoseguimiento' => Cliente::CONTACTADO])->assertForbidden();
        }
        $this->assertSame(Cliente::NUEVO, $cliente->fresh()->estadoseguimiento);
    }

    // ------------------------------------------------------------------ listado

    public function test_el_agente_ve_solo_sus_prospectos(): void
    {
        $yo = $this->agente();
        $otro = $this->agente();
        $this->prospecto(['nombre' => 'Mío uno'], $yo);
        $this->prospecto(['nombre' => 'Mío dos'], $yo);
        $this->prospecto(['nombre' => 'Del otro'], $otro);
        $this->prospecto(['nombre' => 'Sin dueño']);

        $this->actingAs($yo)->get(route('prospectos.index'))
            ->assertOk()
            ->assertSee('Mío uno')->assertSee('Mío dos')
            ->assertDontSee('Del otro')->assertDontSee('Sin dueño');
    }

    public function test_el_administrador_ve_todos_incluidos_los_sin_asignar(): void
    {
        $this->prospecto(['nombre' => 'De agente'], $this->agente());
        $this->prospecto(['nombre' => 'Sin dueño']);

        $this->actingAs($this->admin())->get(route('prospectos.index'))
            ->assertOk()->assertSee('De agente')->assertSee('Sin dueño')->assertSee('Sin asignar');
    }

    public function test_sin_prospectos_muestra_un_aviso(): void
    {
        $this->actingAs($this->agente())->get(route('prospectos.index'))->assertOk()->assertSee('No hay prospectos');
    }

    public function test_busca_por_nombre_correo_y_telefono(): void
    {
        $agente = $this->agente();
        $this->prospecto(['nombre' => 'Lucía Méndez', 'email' => 'lucia@x.com', 'telefono' => '76543210'], $agente);
        $this->prospecto(['nombre' => 'Pedro Soliz', 'email' => 'pedro@y.com', 'telefono' => '71112222'], $agente);
        $this->actingAs($agente);

        $this->get(route('prospectos.index', ['q' => 'Lucía']))->assertSee('Lucía Méndez')->assertDontSee('Pedro Soliz');
        $this->get(route('prospectos.index', ['q' => 'pedro@y']))->assertSee('Pedro Soliz')->assertDontSee('Lucía Méndez');
        $this->get(route('prospectos.index', ['q' => '7654']))->assertSee('Lucía Méndez')->assertDontSee('Pedro Soliz');
    }

    public function test_la_busqueda_del_agente_no_se_sale_de_sus_prospectos(): void
    {
        $yo = $this->agente();
        $this->prospecto(['nombre' => 'Lucía ajena'], $this->agente());

        $this->actingAs($yo)->get(route('prospectos.index', ['q' => 'Lucía']))->assertDontSee('Lucía ajena');
    }

    public function test_filtra_por_estado_de_seguimiento(): void
    {
        $agente = $this->agente();
        $this->prospecto(['nombre' => 'Recién llegado'], $agente);
        $this->prospecto(['nombre' => 'Ya contactado', 'estadoseguimiento' => Cliente::CONTACTADO], $agente);
        $this->actingAs($agente);

        $this->get(route('prospectos.index', ['estado' => Cliente::CONTACTADO]))
            ->assertSee('Ya contactado')->assertDontSee('Recién llegado');
        $this->get(route('prospectos.index', ['estado' => 'INVENTADO']))
            ->assertSee('Ya contactado')->assertSee('Recién llegado'); // un estado inválido se ignora
    }

    public function test_el_administrador_filtra_por_agente_y_por_sin_asignar(): void
    {
        $uno = $this->agente();
        $dos = $this->agente();
        $this->prospecto(['nombre' => 'Del uno'], $uno);
        $this->prospecto(['nombre' => 'Del dos'], $dos);
        $this->prospecto(['nombre' => 'Huérfano']);
        $this->actingAs($this->admin());

        $this->get(route('prospectos.index', ['agente' => $uno->idusuario]))
            ->assertSee('Del uno')->assertDontSee('Del dos')->assertDontSee('Huérfano');
        $this->get(route('prospectos.index', ['agente' => 'sin']))
            ->assertSee('Huérfano')->assertDontSee('Del uno')->assertDontSee('Del dos');
    }

    public function test_el_agente_no_puede_ver_los_de_otros_con_el_filtro_de_agente(): void
    {
        $otro = $this->agente();
        $this->prospecto(['nombre' => 'Del otro'], $otro);

        $this->actingAs($this->agente())->get(route('prospectos.index', ['agente' => $otro->idusuario]))
            ->assertDontSee('Del otro');
    }

    public function test_el_listado_se_pagina_de_10_en_10(): void
    {
        $agente = $this->agente();
        foreach (range(1, 12) as $i) {
            $this->prospecto(['nombre' => "Cliente numero {$i}"], $agente);
        }
        $this->actingAs($agente);

        $this->get(route('prospectos.index'))->assertSee('Cliente numero 12')->assertDontSee('Cliente numero 2<', false);
        $this->get(route('prospectos.index', ['page' => 2]))->assertSee('Cliente numero 2<', false)->assertDontSee('Cliente numero 12');
    }

    public function test_el_listado_muestra_la_cantidad_de_intereses(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto(['nombre' => 'Muy interesado'], $agente);
        foreach ([$this->propiedad(), $this->propiedad()] as $propiedad) {
            Interes::create(['idpropiedad' => $propiedad->idpropiedad, 'idcliente' => $cliente->idcliente]);
        }

        $this->actingAs($agente)->get(route('prospectos.index'))
            ->assertSee('Muy interesado')
            ->assertSeeInOrder(['Muy interesado', '2']);
    }

    // ------------------------------------------------------------------ detalle

    public function test_el_agente_ve_el_detalle_de_su_prospecto_con_sus_propiedades_de_interes(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto(['nombre' => 'Lucía Méndez', 'email' => 'lucia@x.com'], $agente);
        $propiedad = $this->propiedad(['titulo' => 'Casa que le gustó']);
        Interes::create(['idpropiedad' => $propiedad->idpropiedad, 'idcliente' => $cliente->idcliente, 'mensaje' => 'Quiero verla el sábado']);

        $this->actingAs($agente)->get(route('prospectos.show', $cliente))
            ->assertOk()
            ->assertSee('Lucía Méndez')->assertSee('lucia@x.com')
            ->assertSee('Casa que le gustó')->assertSee($propiedad->codigo)
            ->assertSee('Quiero verla el sábado')
            ->assertSee(route('catalogo.detalle', $propiedad), false);
    }

    public function test_el_agente_no_ve_el_prospecto_de_otro_ni_el_sin_asignar(): void
    {
        $this->actingAs($this->agente());

        $this->get(route('prospectos.show', $this->prospecto([], $this->agente())))->assertForbidden();
        $this->get(route('prospectos.show', $this->prospecto()))->assertForbidden();
    }

    public function test_el_administrador_ve_cualquier_detalle(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('prospectos.show', $this->prospecto(['nombre' => 'Visible'], $this->agente())))->assertOk()->assertSee('Visible');
        $this->get(route('prospectos.show', $this->prospecto(['nombre' => 'Sin dueño'])))->assertOk()->assertSee('Sin asignar');
    }

    public function test_un_prospecto_inexistente_da_404(): void
    {
        $this->actingAs($this->admin());

        $this->get('/crm/prospectos/9999')->assertNotFound();
        $this->patch('/crm/prospectos/9999/seguimiento', ['estadoseguimiento' => Cliente::CONTACTADO])->assertNotFound();
    }

    public function test_el_detalle_no_enlaza_a_propiedades_dadas_de_baja(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto([], $agente);
        $baja = $this->propiedad(['titulo' => 'Retirada', 'estadopropiedad' => 'BAJA']);
        Interes::create(['idpropiedad' => $baja->idpropiedad, 'idcliente' => $cliente->idcliente]);

        $this->actingAs($agente)->get(route('prospectos.show', $cliente))
            ->assertOk()->assertSee('Retirada')->assertDontSee(route('catalogo.detalle', $baja), false);
    }

    public function test_solo_el_administrador_ve_el_formulario_de_reasignar(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto([], $agente);

        $this->actingAs($agente)->get(route('prospectos.show', $cliente))->assertDontSee(route('prospectos.asignar', $cliente), false);
        $this->actingAs($this->admin())->get(route('prospectos.show', $cliente))->assertSee(route('prospectos.asignar', $cliente), false);
    }

    // -------------------------------------------------------------- seguimiento

    public function test_el_agente_actualiza_el_seguimiento_de_su_prospecto(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto([], $agente);
        $this->actingAs($agente);

        foreach ([Cliente::CONTACTADO, Cliente::EN_NEGOCIACION, Cliente::CERRADO, Cliente::DESCARTADO, Cliente::NUEVO] as $estado) {
            $this->patch(route('prospectos.seguimiento', $cliente), ['estadoseguimiento' => $estado])
                ->assertRedirect(route('prospectos.show', $cliente))->assertSessionHas('status');
            $this->assertSame($estado, $cliente->fresh()->estadoseguimiento);
        }
    }

    public function test_el_administrador_tambien_actualiza_el_seguimiento(): void
    {
        $cliente = $this->prospecto([], $this->agente());
        $this->actingAs($this->admin());

        $this->patch(route('prospectos.seguimiento', $cliente), ['estadoseguimiento' => Cliente::CONTACTADO])->assertSessionHasNoErrors();

        $this->assertSame(Cliente::CONTACTADO, $cliente->fresh()->estadoseguimiento);
    }

    public function test_el_agente_no_actualiza_el_seguimiento_de_otro(): void
    {
        $cliente = $this->prospecto([], $this->agente());
        $this->actingAs($this->agente());

        $this->patch(route('prospectos.seguimiento', $cliente), ['estadoseguimiento' => Cliente::CERRADO])->assertForbidden();

        $this->assertSame(Cliente::NUEVO, $cliente->fresh()->estadoseguimiento);
    }

    public function test_un_estado_de_seguimiento_invalido_se_rechaza(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto([], $agente);
        $this->actingAs($agente);

        $this->patch(route('prospectos.seguimiento', $cliente), ['estadoseguimiento' => 'INVENTADO'])->assertSessionHasErrors('estadoseguimiento');
        $this->patch(route('prospectos.seguimiento', $cliente), [])->assertSessionHasErrors('estadoseguimiento');

        $this->assertSame(Cliente::NUEVO, $cliente->fresh()->estadoseguimiento);
        $this->assertSame(0, Bitacora::where('accion', 'PROSPECTO_SEGUIMIENTO')->count());
    }

    public function test_repetir_el_mismo_estado_avisa_y_no_deja_bitacora(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto([], $agente);
        $this->actingAs($agente);

        $this->patch(route('prospectos.seguimiento', $cliente), ['estadoseguimiento' => Cliente::NUEVO])
            ->assertSessionHas('status', 'El estado de seguimiento no cambió.');

        $this->assertSame(0, Bitacora::where('accion', 'PROSPECTO_SEGUIMIENTO')->count());
    }

    public function test_el_cambio_de_seguimiento_queda_en_la_bitacora(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto([], $agente);
        $this->actingAs($agente);

        $this->patch(route('prospectos.seguimiento', $cliente), ['estadoseguimiento' => Cliente::EN_NEGOCIACION]);

        $registro = Bitacora::where('accion', 'PROSPECTO_SEGUIMIENTO')->firstOrFail();
        $this->assertSame('CRM', $registro->modulo);
        $this->assertSame($agente->idusuario, $registro->idusuario);
    }

    // --------------------------------------------------------------- reasignar

    public function test_el_administrador_reasigna_un_prospecto_a_otro_agente(): void
    {
        $nuevo = $this->agente(['nombre' => 'Agente Nuevo']);
        $cliente = $this->prospecto([], $this->agente());
        $this->actingAs($this->admin());

        $this->patch(route('prospectos.asignar', $cliente), ['idusuario' => $nuevo->idusuario])
            ->assertRedirect(route('prospectos.show', $cliente))->assertSessionHas('status');

        $this->assertSame($nuevo->idusuario, $cliente->fresh()->idusuario);
        $this->assertSame(1, Bitacora::where('accion', 'PROSPECTO_REASIGNADO')->count());
    }

    public function test_el_administrador_asigna_un_prospecto_sin_agente(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto();
        $this->actingAs($this->admin());

        $this->patch(route('prospectos.asignar', $cliente), ['idusuario' => $agente->idusuario])->assertSessionHasNoErrors();

        $this->assertSame($agente->idusuario, $cliente->fresh()->idusuario);
    }

    public function test_el_prospecto_reasignado_pasa_a_ser_del_nuevo_agente(): void
    {
        $antes = $this->agente();
        $despues = $this->agente();
        $cliente = $this->prospecto(['nombre' => 'Se mudó'], $antes);

        $this->actingAs($this->admin())->patch(route('prospectos.asignar', $cliente), ['idusuario' => $despues->idusuario]);

        $this->actingAs($antes)->get(route('prospectos.index'))->assertDontSee('Se mudó');
        $this->actingAs($antes)->get(route('prospectos.show', $cliente))->assertForbidden();
        $this->actingAs($despues)->get(route('prospectos.index'))->assertSee('Se mudó');
    }

    public function test_solo_el_administrador_puede_reasignar(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto([], $agente);

        foreach ([$agente, $this->asistente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario)->patch(route('prospectos.asignar', $cliente), ['idusuario' => $this->agente()->idusuario])->assertForbidden();
        }
        $this->assertSame($agente->idusuario, $cliente->fresh()->idusuario);
    }

    public function test_no_se_reasigna_a_usuarios_que_no_son_agentes_o_estan_desactivados(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto([], $agente);
        $this->actingAs($this->admin());

        $this->patch(route('prospectos.asignar', $cliente), ['idusuario' => $this->asistente()->idusuario])->assertSessionHasErrors('idusuario');
        $this->patch(route('prospectos.asignar', $cliente), ['idusuario' => $this->agente(['estado' => Usuario::INACTIVO])->idusuario])->assertSessionHasErrors('idusuario');
        $this->patch(route('prospectos.asignar', $cliente), ['idusuario' => 9999])->assertSessionHasErrors('idusuario');
        $this->patch(route('prospectos.asignar', $cliente), [])->assertSessionHasErrors('idusuario');

        $this->assertSame($agente->idusuario, $cliente->fresh()->idusuario);
    }

    public function test_reasignar_al_mismo_agente_avisa_y_no_deja_bitacora(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto([], $agente);
        $this->actingAs($this->admin());

        $this->patch(route('prospectos.asignar', $cliente), ['idusuario' => $agente->idusuario])
            ->assertSessionHas('status', 'El prospecto ya estaba asignado a ese agente.');

        $this->assertSame(0, Bitacora::where('accion', 'PROSPECTO_REASIGNADO')->count());
    }
}
