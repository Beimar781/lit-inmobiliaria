<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Models\Interes;
use App\Models\Propiedad;
use App\Models\Usuario;

/**
 * CU12: Registrar cliente interesado.
 * Vía 1: el Cliente público pulsa "Me interesa" en la ficha (sin iniciar sesión).
 * Vía 2: el Agente registra a quien lo contactó por otro medio.
 */
class CU12_RegistrarClienteInteresadoTest extends SistemaTestCase
{
    private function meInteresa(Propiedad $propiedad, array $datos = [])
    {
        return $this->post(route('catalogo.interes', $propiedad), array_merge([
            'nombre' => 'Ana Rojas',
            'email' => 'ana@correo.com',
            'telefono' => '70011223',
            'mensaje' => 'Quisiera visitarla',
        ], $datos));
    }

    private function registrarComoAgente(array $datos)
    {
        return $this->post(route('prospectos.store'), $datos);
    }

    // =============================================== vía 1: formulario público

    public function test_el_cliente_publico_registra_su_interes_sin_iniciar_sesion(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);

        $this->meInteresa($propiedad)
            ->assertRedirect(route('catalogo.detalle', $propiedad))
            ->assertSessionHas('status');

        $cliente = Cliente::firstOrFail();
        $this->assertSame('Ana Rojas', $cliente->nombre);
        $this->assertSame('ana@correo.com', $cliente->email);
        $this->assertSame('70011223', $cliente->telefono);
        $this->assertSame(Cliente::NUEVO, $cliente->estadoseguimiento);
        $this->assertSame('Comprador', $cliente->tipocliente);
        $this->assertSame($agente->idusuario, $cliente->idusuario);

        $interes = Interes::firstOrFail();
        $this->assertSame($propiedad->idpropiedad, $interes->idpropiedad);
        $this->assertSame($cliente->idcliente, $interes->idcliente);
        $this->assertSame('Quisiera visitarla', $interes->mensaje);
        $this->assertNotNull($interes->fecha);
    }

    public function test_en_un_alquiler_el_cliente_queda_como_inquilino(): void
    {
        $propiedad = $this->propiedad(['tipopropiedad' => 'Alquiler']);

        $this->meInteresa($propiedad);

        $this->assertSame('Inquilino', Cliente::firstOrFail()->tipocliente);
    }

    public function test_basta_con_el_correo_o_con_el_telefono(): void
    {
        $propiedad = $this->propiedad();

        $this->meInteresa($propiedad, ['nombre' => 'Solo correo', 'email' => 'a@a.com', 'telefono' => ''])->assertSessionHasNoErrors();
        $this->meInteresa($propiedad, ['nombre' => 'Solo teléfono', 'email' => '', 'telefono' => '71234567'])->assertSessionHasNoErrors();

        $this->assertSame(2, Cliente::count());
    }

    public function test_sin_correo_ni_telefono_se_rechaza(): void
    {
        $propiedad = $this->propiedad();

        $this->meInteresa($propiedad, ['email' => '', 'telefono' => ''])
            ->assertSessionHasErrors(['email', 'telefono']);

        $this->assertSame(0, Cliente::count());
    }

    public function test_se_validan_nombre_correo_telefono_y_mensaje(): void
    {
        $propiedad = $this->propiedad();

        $this->meInteresa($propiedad, ['nombre' => ''])->assertSessionHasErrors('nombre');
        $this->meInteresa($propiedad, ['nombre' => 'A'])->assertSessionHasErrors('nombre');
        $this->meInteresa($propiedad, ['email' => 'no-es-correo'])->assertSessionHasErrors('email');
        $this->meInteresa($propiedad, ['telefono' => 'abc123'])->assertSessionHasErrors('telefono');
        $this->meInteresa($propiedad, ['mensaje' => str_repeat('a', 1001)])->assertSessionHasErrors('mensaje');

        $this->assertSame(0, Cliente::count());
        $this->assertSame(0, Interes::count());
    }

    public function test_el_formulario_conserva_lo_escrito_si_hay_errores(): void
    {
        $propiedad = $this->propiedad();

        $this->from(route('catalogo.detalle', $propiedad))
            ->meInteresa($propiedad, ['email' => 'mal'])
            ->assertRedirect(route('catalogo.detalle', $propiedad))
            ->assertSessionHasInput('nombre', 'Ana Rojas');
    }

    public function test_el_correo_se_guarda_en_minusculas_y_el_telefono_solo_con_digitos_sin_el_prefijo_de_bolivia(): void
    {
        $propiedad = $this->propiedad();

        $this->meInteresa($propiedad, ['email' => '  ANA@Correo.COM ', 'telefono' => '+591 700-11223']);

        $cliente = Cliente::firstOrFail();
        $this->assertSame('ana@correo.com', $cliente->email);
        $this->assertSame('70011223', $cliente->telefono);
    }

    public function test_el_mismo_telefono_con_y_sin_prefijo_es_un_solo_cliente(): void
    {
        $propiedad = $this->propiedad();

        foreach (['71234567', '+591 71234567', '591-71234567', '00591 71234567'] as $i => $telefono) {
            $this->meInteresa($propiedad, ['nombre' => "Cliente {$i}", 'email' => '', 'telefono' => $telefono])->assertSessionHasNoErrors();
        }

        $this->assertSame(1, Cliente::count());
        $this->assertSame('71234567', Cliente::firstOrFail()->telefono);
        $this->assertSame(1, Interes::count());
    }

    public function test_el_campo_trampa_para_robots_no_guarda_nada(): void
    {
        $propiedad = $this->propiedad();

        $this->meInteresa($propiedad, ['sitio_web' => 'http://spam.example'])
            ->assertRedirect(route('catalogo.detalle', $propiedad));

        $this->assertSame(0, Cliente::count());
        $this->assertSame(0, Interes::count());
    }

    public function test_no_se_duplica_el_cliente_ni_el_interes(): void
    {
        $propiedad = $this->propiedad();

        $this->meInteresa($propiedad);
        $this->meInteresa($propiedad, ['nombre' => 'Ana R.'])->assertSessionHas('status');

        $this->assertSame(1, Cliente::count());
        $this->assertSame(1, Interes::count());
        $this->assertSame(1, Bitacora::where('accion', 'INTERES_REGISTRADO')->count());
    }

    public function test_un_cliente_existente_suma_interes_en_otra_propiedad_conservando_su_estado(): void
    {
        $uno = $this->propiedad(['titulo' => 'Uno']);
        $dos = $this->propiedad(['titulo' => 'Dos']);

        $this->meInteresa($uno);
        Cliente::firstOrFail()->update(['estadoseguimiento' => Cliente::EN_NEGOCIACION]);

        // vuelve a escribir con el mismo teléfono pero sin correo
        $this->meInteresa($dos, ['email' => '']);

        $this->assertSame(1, Cliente::count());
        $this->assertSame(2, Interes::count());
        $this->assertSame(Cliente::EN_NEGOCIACION, Cliente::firstOrFail()->estadoseguimiento);
    }

    public function test_el_cliente_existente_completa_los_datos_que_le_faltaban(): void
    {
        $propiedad = $this->propiedad();

        $this->meInteresa($propiedad, ['email' => '', 'telefono' => '70011223']);
        $this->meInteresa($this->propiedad(), ['email' => 'nuevo@correo.com', 'telefono' => '70011223']);

        $cliente = Cliente::firstOrFail();
        $this->assertSame(1, Cliente::count());
        $this->assertSame('nuevo@correo.com', $cliente->email);
    }

    public function test_si_la_propiedad_no_tiene_agente_el_prospecto_queda_sin_asignar(): void
    {
        $this->meInteresa($this->propiedad());

        $this->assertNull(Cliente::firstOrFail()->idusuario);
    }

    public function test_si_el_agente_de_la_propiedad_esta_desactivado_queda_sin_asignar(): void
    {
        $agente = $this->agente(['estado' => Usuario::INACTIVO]);

        $this->meInteresa($this->propiedad([], $agente));

        $this->assertNull(Cliente::firstOrFail()->idusuario);
    }

    public function test_no_se_registra_interes_en_propiedades_no_disponibles(): void
    {
        foreach ([Propiedad::RESERVADO, Propiedad::VENDIDO, Propiedad::ALQUILADO] as $estado) {
            $propiedad = $this->propiedad(['estadopropiedad' => $estado]);

            $this->meInteresa($propiedad)
                ->assertRedirect(route('catalogo.detalle', $propiedad))
                ->assertSessionHas('error');
        }

        $this->assertSame(0, Cliente::count());
        $this->assertSame(0, Interes::count());
    }

    public function test_una_propiedad_dada_de_baja_o_inexistente_da_404(): void
    {
        $baja = $this->propiedad(['estadopropiedad' => Propiedad::BAJA]);

        $this->meInteresa($baja)->assertNotFound();
        $this->post('/catalogo/9999/me-interesa', ['nombre' => 'Ana', 'email' => 'a@a.com'])->assertNotFound();
        $this->assertSame(0, Cliente::count());
    }

    public function test_el_formulario_limita_los_envios_por_minuto(): void
    {
        $propiedad = $this->propiedad();

        foreach (range(1, 6) as $i) {
            $this->meInteresa($propiedad, ['nombre' => "Cliente {$i}", 'email' => "c{$i}@a.com", 'telefono' => ''])->assertRedirect();
        }

        $this->meInteresa($propiedad, ['nombre' => 'Cliente 7', 'email' => 'c7@a.com', 'telefono' => ''])->assertStatus(429);
        $this->assertSame(6, Cliente::count());
    }

    public function test_deja_registro_en_la_bitacora_como_visitante(): void
    {
        $propiedad = $this->propiedad(['titulo' => 'Casa bitácora']);

        $this->meInteresa($propiedad);

        $registro = Bitacora::where('accion', 'INTERES_REGISTRADO')->firstOrFail();
        $this->assertSame('CRM', $registro->modulo);
        $this->assertNull($registro->idusuario);
        $this->assertStringContainsString('Casa bitácora', $registro->descripcion);
    }

    public function test_la_ficha_muestra_el_formulario_y_conserva_los_errores(): void
    {
        $propiedad = $this->propiedad();

        $this->get(route('catalogo.detalle', $propiedad))
            ->assertSee(route('catalogo.interes', $propiedad), false)
            ->assertSee('name="nombre"', false);
    }

    // ================================================ vía 2: el agente registra

    public function test_solo_el_agente_puede_registrar_clientes_interesados(): void
    {
        $this->get(route('prospectos.create'))->assertRedirect(route('login'));
        $this->registrarComoAgente([])->assertRedirect(route('login'));

        foreach ([$this->admin(), $this->asistente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario);
            $this->get(route('prospectos.create'))->assertForbidden();
            $this->registrarComoAgente(['nombre' => 'X'])->assertForbidden();
        }
        $this->assertSame(0, Cliente::count());
    }

    public function test_el_agente_ve_el_formulario_con_las_propiedades_vigentes(): void
    {
        $vigente = $this->propiedad(['titulo' => 'Vigente']);
        $this->propiedad(['titulo' => 'Dada de baja', 'estadopropiedad' => Propiedad::BAJA]);

        $this->actingAs($this->agente())->get(route('prospectos.create'))
            ->assertOk()
            ->assertSee('Registrar cliente interesado')
            ->assertSee($vigente->codigo)
            ->assertSee('Vigente')
            ->assertDontSee('Dada de baja');
    }

    public function test_el_agente_registra_un_cliente_de_su_propiedad_y_va_a_su_detalle(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->actingAs($agente);

        $respuesta = $this->registrarComoAgente([
            'idpropiedad' => $propiedad->idpropiedad, 'nombre' => 'Carlos Paz', 'telefono' => '71234567',
            'tipocliente' => 'Inversionista', 'mensaje' => 'Me escribió por WhatsApp',
        ]);

        $cliente = Cliente::firstOrFail();
        $respuesta->assertRedirect(route('prospectos.show', $cliente))->assertSessionHas('status');
        $this->assertSame($agente->idusuario, $cliente->idusuario);
        $this->assertSame('Inversionista', $cliente->tipocliente);
        $this->assertSame(Cliente::NUEVO, $cliente->estadoseguimiento);
        $this->assertSame(1, Interes::where('idpropiedad', $propiedad->idpropiedad)->count());
    }

    public function test_si_la_propiedad_es_de_otro_agente_el_prospecto_va_para_ese_agente(): void
    {
        $dueno = $this->agente(['nombre' => 'Dueño de la propiedad']);
        $propiedad = $this->propiedad([], $dueno);
        $this->actingAs($this->agente());

        $this->registrarComoAgente(['idpropiedad' => $propiedad->idpropiedad, 'nombre' => 'Carlos Paz', 'email' => 'c@p.com', 'tipocliente' => 'Comprador'])
            ->assertRedirect(route('prospectos.index'))
            ->assertSessionHas('status');

        $this->assertSame($dueno->idusuario, Cliente::firstOrFail()->idusuario);
    }

    public function test_si_la_propiedad_no_tiene_agente_queda_a_cargo_del_agente_que_lo_registra(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad();
        $this->actingAs($agente);

        $this->registrarComoAgente(['idpropiedad' => $propiedad->idpropiedad, 'nombre' => 'Carlos Paz', 'email' => 'c@p.com', 'tipocliente' => 'Comprador'])
            ->assertRedirect(route('prospectos.show', Cliente::firstOrFail()));

        $this->assertSame($agente->idusuario, Cliente::firstOrFail()->idusuario);
    }

    public function test_el_agente_puede_registrar_interes_en_una_propiedad_no_disponible(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad(['estadopropiedad' => Propiedad::RESERVADO], $agente);
        $this->actingAs($agente);

        $this->registrarComoAgente(['idpropiedad' => $propiedad->idpropiedad, 'nombre' => 'Lista de espera', 'email' => 'l@e.com', 'tipocliente' => 'Comprador'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Cliente::count());
    }

    public function test_no_se_puede_registrar_sobre_una_propiedad_dada_de_baja(): void
    {
        $baja = $this->propiedad(['estadopropiedad' => Propiedad::BAJA]);
        $this->actingAs($this->agente());

        $this->registrarComoAgente(['idpropiedad' => $baja->idpropiedad, 'nombre' => 'Carlos Paz', 'email' => 'c@p.com', 'tipocliente' => 'Comprador'])
            ->assertSessionHasErrors('idpropiedad');

        $this->assertSame(0, Cliente::count());
    }

    public function test_el_agente_no_duplica_clientes_que_ya_existen(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->actingAs($agente);
        $datos = ['idpropiedad' => $propiedad->idpropiedad, 'nombre' => 'Carlos Paz', 'email' => 'c@p.com', 'tipocliente' => 'Comprador'];

        $this->registrarComoAgente($datos);
        $this->registrarComoAgente($datos)->assertSessionHas('status');

        $this->assertSame(1, Cliente::count());
        $this->assertSame(1, Interes::count());
    }

    public function test_el_agente_debe_elegir_propiedad_y_tipo_de_cliente_validos(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->agente());

        $this->registrarComoAgente(['nombre' => 'Carlos Paz', 'email' => 'c@p.com'])->assertSessionHasErrors(['idpropiedad', 'tipocliente']);
        $this->registrarComoAgente(['idpropiedad' => 9999, 'nombre' => 'Carlos Paz', 'email' => 'c@p.com', 'tipocliente' => 'Comprador'])->assertSessionHasErrors('idpropiedad');
        $this->registrarComoAgente(['idpropiedad' => $propiedad->idpropiedad, 'nombre' => 'Carlos Paz', 'email' => 'c@p.com', 'tipocliente' => 'Vendedor'])->assertSessionHasErrors('tipocliente');
        $this->registrarComoAgente(['idpropiedad' => $propiedad->idpropiedad, 'nombre' => 'Carlos Paz', 'tipocliente' => 'Comprador'])->assertSessionHasErrors(['email', 'telefono']);

        $this->assertSame(0, Cliente::count());
    }

    public function test_el_formulario_del_agente_puede_llegar_con_la_propiedad_elegida(): void
    {
        $propiedad = $this->propiedad(['titulo' => 'Elegida']);

        $this->actingAs($this->agente())->get(route('prospectos.create', ['propiedad' => $propiedad->idpropiedad]))
            ->assertOk()
            ->assertSee('value="' . $propiedad->idpropiedad . '" selected', false);
    }

    public function test_el_registro_del_agente_deja_bitacora_con_su_nombre(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->actingAs($agente);

        $this->registrarComoAgente(['idpropiedad' => $propiedad->idpropiedad, 'nombre' => 'Carlos Paz', 'email' => 'c@p.com', 'tipocliente' => 'Comprador']);

        $registro = Bitacora::where('accion', 'INTERES_REGISTRADO')->firstOrFail();
        $this->assertSame($agente->idusuario, $registro->idusuario);
    }
}
