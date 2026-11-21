<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Models\Inquilino;
use App\Models\Usuario;

/** CU24: Gestionar inquilinos. Un test por paso del documento (6.2.4). */
class CU24_GestionarInquilinosTest extends SistemaTestCase
{
    private function prospecto(?Usuario $agente = null): Cliente
    {
        static $n = 0;
        $n++;

        return Cliente::create([
            'nombre' => "Inquilino Prospecto {$n}", 'email' => "inq24_{$n}@test.com", 'telefono' => '70011122',
            'tipocliente' => 'Inquilino', 'idusuario' => $agente?->idusuario,
        ]);
    }

    private function guardar(Cliente $cliente, array $cambios = [])
    {
        return $this->post(route('prospectos.inquilino.guardar', $cliente), array_merge([
            'ocupacion' => 'Ingeniero civil',
            'tipogarantia' => 'DEPOSITO',
            'detallegarantia' => 'Depósito de dos meses de alquiler',
        ], $cambios));
    }

    public function test_paso_1_registrar_el_inquilino_con_ocupacion_y_garantia_se_guarda_y_se_muestra_en_el_detalle(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto($agente);
        $this->actingAs($agente);

        $this->get(route('prospectos.inquilino', $cliente))->assertOk()
            ->assertSee('<span class="cu-etiqueta">(CU24)</span>', false)->assertSee('Registrar inquilino');
        $this->guardar($cliente)->assertRedirect(route('prospectos.show', $cliente));

        $inquilino = Inquilino::where('idcliente', $cliente->idcliente)->firstOrFail();
        $this->assertSame('ACTIVO', $inquilino->estado);
        $this->assertNotNull($inquilino->fecharegistro);
        $this->get(route('prospectos.show', $cliente))->assertOk()
            ->assertSee('Ingeniero civil')->assertSee('Depósito: Depósito de dos meses de alquiler')->assertSee('Activo')
            ->assertSee('registrado el ' . now()->format('d/m/Y'));
        $this->assertSame(1, Bitacora::where('accion', 'INQUILINO_REGISTRADO')->where('modulo', 'CRM')->count());
    }

    public function test_paso_2_registrar_sin_elegir_el_tipo_de_garantia_muestra_garantia_obligatoria(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto($agente);
        $this->actingAs($agente);

        $this->guardar($cliente, ['tipogarantia' => ''])->assertSessionHasErrors(['tipogarantia' => 'Elige el tipo de garantía.']);
        $this->guardar($cliente, ['ocupacion' => ''])->assertSessionHasErrors(['ocupacion' => 'La ocupación es obligatoria.']);

        $this->assertSame(0, Inquilino::count());
    }

    public function test_paso_3_actualizar_el_detalle_de_la_garantia_se_actualiza_sin_crear_otro_inquilino(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto($agente);
        $this->actingAs($agente);
        $this->guardar($cliente);

        $this->get(route('prospectos.inquilino', $cliente))->assertOk()
            ->assertSee('Editar inquilino')->assertSee('Ingeniero civil')->assertSee('Depósito de dos meses de alquiler');
        $this->guardar($cliente, ['detallegarantia' => 'Depósito de tres meses'])->assertSessionDoesntHaveErrors();

        $this->assertSame(1, Inquilino::count());
        $this->assertSame('Depósito de tres meses', Inquilino::first()->detallegarantia);
        $this->assertSame(1, Bitacora::where('accion', 'INQUILINO_MODIFICADO')->count());
    }

    public function test_paso_4_cambiar_el_estado_a_inactivo_se_actualiza_y_se_muestra_en_el_detalle(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto($agente);
        $this->actingAs($agente);
        $this->guardar($cliente);

        $this->guardar($cliente, ['estado' => 'INACTIVO'])->assertSessionDoesntHaveErrors();

        $this->assertSame('INACTIVO', Inquilino::first()->estado);
        $this->get(route('prospectos.show', $cliente))->assertSee('Inactivo');

        $this->guardar($cliente, ['estado' => 'ACTIVO']); // se puede volver a activar
        $this->assertSame('ACTIVO', Inquilino::first()->estado);
        $this->assertSame(1, Inquilino::count());
    }

    public function test_paso_5_intentar_registrar_un_segundo_inquilino_para_el_mismo_cliente_actualiza_el_existente(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto($agente);
        $this->actingAs($agente);

        $this->guardar($cliente, ['ocupacion' => 'Primera ocupación']);
        $this->guardar($cliente, ['ocupacion' => 'Segunda ocupación', 'tipogarantia' => 'GARANTE', 'detallegarantia' => 'Fiador: Juan Pérez']);

        $this->assertSame(1, Inquilino::where('idcliente', $cliente->idcliente)->count());
        $inquilino = Inquilino::first();
        $this->assertSame('Segunda ocupación', $inquilino->ocupacion);
        $this->assertSame('GARANTE', $inquilino->tipogarantia);
    }

    public function test_paso_6_un_agente_que_registra_un_inquilino_para_el_cliente_de_otro_agente_recibe_403(): void
    {
        $cliente = $this->prospecto($this->agente());
        $this->actingAs($this->agente());

        $this->get(route('prospectos.inquilino', $cliente))->assertForbidden();
        $this->guardar($cliente)->assertForbidden();
        $this->guardar($cliente, ['ocupacion' => ''])->assertForbidden(); // el 403 va antes de validar
        $this->assertSame(0, Inquilino::count());
    }

    public function test_tipo_ninguna_sin_detalle_se_acepta_y_deposito_sin_detalle_se_rechaza(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto($agente);
        $this->actingAs($agente);

        $this->guardar($cliente, ['tipogarantia' => 'DEPOSITO', 'detallegarantia' => ''])
            ->assertSessionHasErrors(['detallegarantia' => 'Describe la garantía.']);
        $this->guardar($cliente, ['tipogarantia' => 'GARANTE', 'detallegarantia' => '  '])->assertSessionHasErrors('detallegarantia');
        $this->assertSame(0, Inquilino::count());

        $this->guardar($cliente, ['tipogarantia' => 'NINGUNA', 'detallegarantia' => ''])->assertSessionDoesntHaveErrors();
        $this->assertNull(Inquilino::first()->detallegarantia);
        $this->get(route('prospectos.show', $cliente))->assertSee('Ninguna');

        // Con NINGUNA el detalle que llegue no se guarda
        $this->guardar($cliente, ['tipogarantia' => 'NINGUNA', 'detallegarantia' => 'texto de más']);
        $this->assertNull(Inquilino::first()->detallegarantia);
    }

    public function test_el_estado_no_se_puede_fijar_al_registrar_y_un_valor_invalido_se_rechaza(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto($agente);
        $this->actingAs($agente);

        $this->guardar($cliente, ['estado' => 'INACTIVO'])->assertSessionDoesntHaveErrors();
        $this->assertSame('ACTIVO', Inquilino::first()->estado); // al crear siempre queda activo

        $this->guardar($cliente, ['estado' => 'OTRO'])->assertSessionHasErrors('estado');
        $this->guardar($cliente, ['tipogarantia' => 'INVENTADA'])->assertSessionHasErrors('tipogarantia');
        $this->guardar($cliente, ['ocupacion' => str_repeat('a', 101)])->assertSessionHasErrors('ocupacion');
    }

    public function test_el_administrador_puede_registrar_en_cualquier_cliente_y_otros_roles_reciben_403(): void
    {
        $cliente = $this->prospecto($this->agente());

        $this->actingAs($this->admin());
        $this->guardar($cliente)->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Inquilino::count());
        $this->get('/crm/prospectos/99999/inquilino')->assertNotFound();

        $this->actingAs($this->cliente())->get(route('prospectos.inquilino', $cliente))->assertForbidden();
        $this->actingAs($this->asistente())->get(route('prospectos.inquilino', $cliente))->assertForbidden();
    }

    public function test_el_detalle_sin_inquilino_ofrece_el_boton_para_registrarlo(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto($agente);

        $this->actingAs($agente)->get(route('prospectos.show', $cliente))->assertOk()
            ->assertSee('Este cliente aún no está registrado como inquilino.')
            ->assertSee(route('prospectos.inquilino', $cliente), false);
    }
}