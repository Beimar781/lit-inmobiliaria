<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Models\Seguimiento;
use App\Models\Usuario;

/** CU18: Registrar seguimiento de cliente. Un test por paso del documento (6.2.4). */
class CU18_RegistrarSeguimientoTest extends SistemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reloj fijo (martes 15/12/2026, hora de Bolivia): las pruebas no dependen de la hora real ni de un cambio de día o de mes.
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-12-15 10:00:00', 'America/La_Paz'));
    }
    private function prospecto(?Usuario $agente = null, array $datos = []): Cliente
    {
        static $n = 0;
        $n++;

        return Cliente::create(array_merge([
            'nombre' => "Prospecto {$n}", 'email' => "prospecto18_{$n}@test.com", 'telefono' => '70011122',
            'tipocliente' => 'Comprador', 'idusuario' => $agente?->idusuario,
        ], $datos));
    }

    private function registrar(Cliente $cliente, array $cambios = [])
    {
        return $this->post(route('prospectos.seguimientos.guardar', $cliente), array_merge([
            'medio' => 'LLAMADA',
            'nota' => 'Se llamó para confirmar su interés.',
            'fechacontacto' => now()->subHour()->format('Y-m-d\TH:i'),
        ], $cambios));
    }

    public function test_paso_1_registrar_un_seguimiento_por_llamada_con_nota_y_fecha_se_guarda_y_aparece_al_inicio(): void
    {
        $agente = $this->agente(['nombre' => 'Ana Agente']);
        $cliente = $this->prospecto($agente);
        $this->actingAs($agente);

        $this->get(route('prospectos.seguimientos', $cliente))->assertOk()
            ->assertSee('<span class="cu-etiqueta">(CU18)</span>', false);
        $this->registrar($cliente)->assertRedirect(route('prospectos.seguimientos', $cliente));

        $s = Seguimiento::where('idcliente', $cliente->idcliente)->firstOrFail();
        $this->assertSame('LLAMADA', $s->medio);
        $this->assertSame($agente->idusuario, $s->idusuario);
        $this->assertNull($s->idpropiedad);
        $this->assertNotNull($s->fecharegistro);
        $this->get(route('prospectos.seguimientos', $cliente))->assertSee('Llamada')->assertSee('Se llamó para confirmar su interés.')->assertSee('Ana Agente');
        $this->assertSame(1, Bitacora::where('accion', 'SEGUIMIENTO_REGISTRADO')->where('modulo', 'CRM')->count());
    }

    public function test_paso_2_indicar_la_fecha_del_proximo_contacto_se_muestra_destacada_en_el_detalle_del_prospecto(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto($agente);
        $this->actingAs($agente);
        $manana = now()->addDays(3);

        $this->registrar($cliente, ['proximocontacto' => $manana->format('Y-m-d')])->assertSessionDoesntHaveErrors();

        $this->get(route('prospectos.show', $cliente))->assertOk()
            ->assertSee('Próximo contacto: ' . $manana->format('d/m/Y'))
            ->assertSee('Último contacto:')->assertSee('Se llamó para confirmar su interés.')
            ->assertSee(route('prospectos.seguimientos', $cliente), false);
    }

    public function test_paso_3_guardar_sin_nota_muestra_nota_obligatoria_y_no_se_guarda(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto($agente);
        $this->actingAs($agente);

        $this->registrar($cliente, ['nota' => '   '])->assertSessionHasErrors(['nota' => 'Escribe la nota del seguimiento.']);

        $this->assertSame(0, Seguimiento::count());
    }

    public function test_paso_4_fecha_de_contacto_futura_es_rechazada(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto($agente);
        $this->actingAs($agente);

        $this->registrar($cliente, ['fechacontacto' => now()->addDay()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors(['fechacontacto' => 'La fecha del contacto no puede ser futura.']);
        $this->assertSame(0, Seguimiento::count());

        // La tolerancia de 1 minuto por el reloj del navegador sí se acepta
        $this->registrar($cliente, ['fechacontacto' => now()->format('Y-m-d\TH:i')])->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Seguimiento::count());
    }

    public function test_paso_5_proximo_contacto_en_el_pasado_o_en_el_dia_de_hoy_es_rechazado(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto($agente);
        $this->actingAs($agente);
        $mensaje = 'La fecha del próximo contacto debe ser posterior a hoy.';

        $this->registrar($cliente, ['proximocontacto' => now()->subDay()->format('Y-m-d')])->assertSessionHasErrors(['proximocontacto' => $mensaje]);
        $this->registrar($cliente, ['proximocontacto' => now()->format('Y-m-d')])->assertSessionHasErrors(['proximocontacto' => $mensaje]);
        $this->registrar($cliente, ['proximocontacto' => 'no-es-fecha'])->assertSessionHasErrors(['proximocontacto' => $mensaje]);
        $this->assertSame(0, Seguimiento::count());
    }

    public function test_paso_6_un_agente_que_registra_un_seguimiento_a_un_prospecto_de_otro_agente_recibe_403(): void
    {
        $cliente = $this->prospecto($this->agente());
        $this->actingAs($this->agente());

        $this->get(route('prospectos.seguimientos', $cliente))->assertForbidden();
        $this->registrar($cliente)->assertForbidden();
        $this->registrar($cliente, ['nota' => ''])->assertForbidden(); // el 403 va antes de validar
        $this->assertSame(0, Seguimiento::count());
    }

    public function test_paso_7_abrir_el_historial_de_un_cliente_sin_seguimientos_muestra_sin_seguimientos_registrados(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto($agente);

        $this->actingAs($agente)->get(route('prospectos.seguimientos', $cliente))->assertOk()->assertSee('Sin seguimientos registrados');
        $this->get(route('prospectos.show', $cliente))->assertOk()->assertSee('Sin seguimientos registrados');
    }

    public function test_el_historial_va_del_mas_reciente_al_mas_antiguo(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto($agente);
        $this->actingAs($agente);

        $this->registrar($cliente, ['nota' => 'NotaAntigua', 'fechacontacto' => now()->subDays(5)->format('Y-m-d\TH:i')]);
        $this->registrar($cliente, ['nota' => 'NotaReciente', 'fechacontacto' => now()->subHour()->format('Y-m-d\TH:i')]);
        $this->registrar($cliente, ['nota' => 'NotaMedia', 'fechacontacto' => now()->subDays(2)->format('Y-m-d\TH:i')]);

        $html = $this->get(route('prospectos.seguimientos', $cliente))->getContent();
        $this->assertLessThan(strpos($html, 'NotaMedia'), strpos($html, 'NotaReciente'));
        $this->assertLessThan(strpos($html, 'NotaAntigua'), strpos($html, 'NotaMedia'));
    }

    public function test_el_administrador_puede_registrar_en_cualquier_prospecto_incluso_sin_agente(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->registrar($this->prospecto($this->agente()))->assertSessionDoesntHaveErrors();
        $this->registrar($this->prospecto(null, ['nombre' => 'Sin agente']), ['medio' => 'WHATSAPP'])->assertSessionDoesntHaveErrors();

        $this->assertSame(2, Seguimiento::where('idusuario', $admin->idusuario)->count());
    }

    public function test_no_existen_rutas_para_editar_ni_borrar_seguimientos(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto($agente);
        $this->actingAs($agente);
        $this->registrar($cliente);
        $id = Seguimiento::firstOrFail()->idseguimiento;

        foreach (['put', 'patch', 'delete'] as $metodo) {
            $this->{$metodo}("/crm/prospectos/{$cliente->idcliente}/seguimientos/{$id}")->assertStatus(404);
            $this->{$metodo}("/crm/prospectos/{$cliente->idcliente}/seguimientos")->assertStatus(405);
        }
        $this->assertSame(1, Seguimiento::count());
    }

    public function test_medio_invalido_propiedad_inexistente_cliente_inexistente_y_otros_roles(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto($agente);
        $this->actingAs($agente);

        $this->registrar($cliente, ['medio' => 'PALOMA'])->assertSessionHasErrors(['medio' => 'Elige un medio de contacto válido.']);
        $this->registrar($cliente, ['medio' => ''])->assertSessionHasErrors(['medio' => 'Elige un medio de contacto válido.']);
        $this->registrar($cliente, ['idpropiedad' => '99999'])->assertSessionHasErrors(['idpropiedad' => 'La propiedad indicada no existe.']);
        $this->registrar($cliente, ['nota' => str_repeat('x', 2001)])->assertSessionHasErrors('nota');
        $this->get('/crm/prospectos/99999/seguimientos')->assertNotFound();

        $this->registrar($cliente, ['idpropiedad' => (string) $this->propiedad()->idpropiedad])->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Seguimiento::count());

        $this->actingAs($this->cliente())->get(route('prospectos.seguimientos', $cliente))->assertForbidden();
        $this->actingAs($this->asistente())->get(route('prospectos.seguimientos', $cliente))->assertForbidden();
    }

    public function test_registrar_un_seguimiento_no_cambia_el_estado_de_seguimiento_del_cliente(): void
    {
        $agente = $this->agente();
        $cliente = $this->prospecto($agente);
        $antes = $cliente->fresh()->estadoseguimiento;
        $this->actingAs($agente);

        $this->registrar($cliente);

        $this->assertSame($antes, $cliente->fresh()->estadoseguimiento);
    }
}