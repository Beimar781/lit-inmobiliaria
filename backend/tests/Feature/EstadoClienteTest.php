<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Services\BitacoraService;

/** Atributo "estado" del cliente: activo o inactivo (distinto del seguimiento del CRM). */
class EstadoClienteTest extends SistemaTestCase
{
    private function registrar(array $datos = [])
    {
        return $this->post(route('prospectos.store'), array_merge([
            'idpropiedad' => $this->propiedad()->idpropiedad,
            'tipocliente' => 'Comprador',
            'nombre' => 'Ana Rojas',
            'email' => 'ana@correo.com',
            'telefono' => '70011223',
        ], $datos));
    }

    public function test_el_estado_por_defecto_es_activo(): void
    {
        $cliente = Cliente::create(['nombre' => 'Sin estado', 'email' => 'x@correo.com']);

        $this->assertSame('activo', $cliente->fresh()->estado);

        $this->actingAs($this->agente());
        $this->registrar()->assertSessionHasNoErrors();
        $this->assertSame('activo', Cliente::where('email', 'ana@correo.com')->value('estado'));
    }

    public function test_el_estado_se_guarda_al_registrar_y_se_ve_en_listado_y_detalle(): void
    {
        $agente = $this->agente();
        $this->actingAs($agente);

        $this->registrar(['estado' => 'inactivo'])->assertSessionHasNoErrors();

        $cliente = Cliente::firstWhere('email', 'ana@correo.com');
        $this->assertSame('inactivo', $cliente->estado);

        $cliente->update(['idusuario' => $agente->idusuario]);
        $this->get(route('prospectos.index'))->assertOk()->assertSee('Inactivo');
        $this->get(route('prospectos.show', $cliente))->assertOk()->assertSee('Inactivo');
    }

    public function test_un_estado_invalido_se_rechaza(): void
    {
        $this->actingAs($this->agente());

        $this->registrar(['estado' => 'suspendido'])->assertSessionHasErrors('estado');
        $this->assertSame(0, Cliente::count());
    }

    public function test_el_estado_se_modifica_desde_el_detalle_y_se_valida(): void
    {
        $agente = $this->agente();
        $cliente = Cliente::create(['nombre' => 'Ana', 'email' => 'a@correo.com', 'estadoseguimiento' => Cliente::NUEVO, 'idusuario' => $agente->idusuario]);
        $this->actingAs($agente);

        $this->patch(route('prospectos.seguimiento', $cliente), ['estadoseguimiento' => Cliente::NUEVO, 'estado' => 'inactivo'])
            ->assertSessionHasNoErrors();
        $this->assertSame('inactivo', $cliente->fresh()->estado);

        // El cambio queda en la bitácora con su etiqueta legible.
        $this->assertSame(1, Bitacora::where('accion', 'PROSPECTO_ESTADO')->count());
        $this->assertSame('Estado de cliente actualizado', BitacoraService::ETIQUETAS['PROSPECTO_ESTADO']);

        $this->patch(route('prospectos.seguimiento', $cliente), ['estadoseguimiento' => Cliente::NUEVO, 'estado' => 'otro'])
            ->assertSessionHasErrors('estado');
        $this->assertSame('inactivo', $cliente->fresh()->estado);
    }

    public function test_el_listado_se_filtra_por_estado(): void
    {
        $agente = $this->agente();
        Cliente::create(['nombre' => 'Cliente Activo Uno', 'email' => 'a@correo.com', 'idusuario' => $agente->idusuario]);
        Cliente::create(['nombre' => 'Cliente Inactivo Dos', 'email' => 'b@correo.com', 'idusuario' => $agente->idusuario, 'estado' => 'inactivo']);

        $this->actingAs($agente)->get(route('prospectos.index', ['actividad' => 'inactivo']))
            ->assertSee('Cliente Inactivo Dos')->assertDontSee('Cliente Activo Uno');
    }
}
