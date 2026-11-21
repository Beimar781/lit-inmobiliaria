<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Propiedad;
use App\Models\Resena;
use App\Models\Usuario;

/** CU25: Registrar reseña de propiedad (solo el Cliente con contrato). Un test por paso del documento (6.2.4). */
class CU25_RegistrarResenaTest extends SistemaTestCase
{
    /** Un cliente con cuenta de acceso. @return array{0: Usuario, 1: Cliente} */
    private function clienteConCuenta(string $nombre = 'Clara Mendoza'): array
    {
        static $n = 0;
        $n++;
        $cuenta = $this->cliente(['nombre' => $nombre, 'email' => "cliente25_{$n}@correo.com"]);
        $cliente = Cliente::create(['nombre' => $nombre, 'email' => $cuenta->email, 'idusuariocuenta' => $cuenta->idusuario]);

        return [$cuenta, $cliente];
    }

    private function contrato(Cliente $cliente, ?Propiedad $propiedad = null, string $estado = Contrato::VIGENTE): Contrato
    {
        return Contrato::create([
            'idpropiedad' => ($propiedad ?? $this->propiedad())->idpropiedad, 'idcliente' => $cliente->idcliente,
            'tipocontrato' => 'Venta', 'monto' => 1000, 'fechainicio' => '2026-12-01', 'estado' => $estado,
        ]);
    }

    private function enviar(Propiedad $propiedad, array $datos = [])
    {
        return $this->post(route('resenas.guardar', $propiedad), array_merge(['puntuacion' => '5', 'comentario' => 'Excelente casa'], $datos));
    }

    public function test_paso_1_enviar_una_resena_con_puntuacion_5_y_comentario_se_publica_y_actualiza_el_promedio(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $propiedad = $this->propiedad();
        $this->contrato($cliente, $propiedad);
        $this->actingAs($cuenta);

        $this->get(route('resenas.crear', $propiedad))->assertOk()
            ->assertSee('<span class="cu-etiqueta">(CU25)</span>', false)->assertSee('Publicar reseña');
        $this->enviar($propiedad)->assertRedirect(route('resenas.crear', $propiedad))->assertSessionHas('status');

        $resena = Resena::where('idpropiedad', $propiedad->idpropiedad)->firstOrFail();
        $this->assertSame(5, $resena->puntuacion);
        $this->assertSame('Excelente casa', $resena->comentario);
        $this->assertSame($cliente->idcliente, $resena->idcliente);
        $this->assertSame(5.0, $propiedad->puntuacionPromedio());
        $this->get(route('resenas.crear', $propiedad))->assertSee('Tu reseña')->assertSee('Excelente casa')->assertDontSee('Publicar reseña');
        $this->assertSame(1, Bitacora::where('accion', 'RESENA_REGISTRADA')->where('modulo', 'CRM')->count());
    }

    public function test_paso_2_enviar_sin_elegir_la_puntuacion_muestra_puntuacion_obligatoria(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $propiedad = $this->propiedad();
        $this->contrato($cliente, $propiedad);
        $this->actingAs($cuenta);

        $this->enviar($propiedad, ['puntuacion' => ''])->assertSessionHasErrors(['puntuacion' => 'Elige una puntuación.']);
        $this->post(route('resenas.guardar', $propiedad), ['comentario' => 'x'])->assertSessionHasErrors(['puntuacion' => 'Elige una puntuación.']);

        $this->assertSame(0, Resena::count());
    }

    public function test_paso_3_puntuacion_fuera_de_rango_0_y_6_es_rechazada(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $propiedad = $this->propiedad();
        $this->contrato($cliente, $propiedad);
        $this->actingAs($cuenta);

        foreach (['0', '6', 'abc', '3.5', '-1'] as $valor) {
            $this->enviar($propiedad, ['puntuacion' => $valor])->assertSessionHasErrors(['puntuacion' => 'La puntuación debe estar entre 1 y 5.']);
        }
        $this->assertSame(0, Resena::count());

        $this->enviar($propiedad, ['puntuacion' => '1'])->assertSessionDoesntHaveErrors(); // límite inferior válido
        $this->assertSame(1, Resena::count());
    }

    public function test_paso_4_segunda_resena_sobre_la_misma_propiedad_es_rechazada(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $propiedad = $this->propiedad();
        $this->contrato($cliente, $propiedad);
        $this->actingAs($cuenta);
        $this->enviar($propiedad, ['puntuacion' => '4']);

        $this->enviar($propiedad, ['puntuacion' => '1'])
            ->assertRedirect(route('resenas.crear', $propiedad))
            ->assertSessionHas('error', 'Ya registraste una reseña de esta propiedad.');

        $this->assertSame(1, Resena::count());
        $this->assertSame(4, Resena::first()->puntuacion);
    }

    public function test_paso_5_un_cliente_sin_contrato_sobre_la_propiedad_es_rechazado(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $this->contrato($cliente); // contrato de OTRA propiedad
        $propiedad = $this->propiedad();
        $this->actingAs($cuenta);

        $this->get(route('resenas.crear', $propiedad))->assertRedirect(route('portal.contratos'))
            ->assertSessionHas('error', 'Solo puedes reseñar propiedades sobre las que tienes un contrato.');
        $this->enviar($propiedad)->assertRedirect(route('portal.contratos'))
            ->assertSessionHas('error', 'Solo puedes reseñar propiedades sobre las que tienes un contrato.');
        $this->followingRedirects()->get(route('resenas.crear', $propiedad))->assertSee('Solo puedes reseñar propiedades sobre las que tienes un contrato.');

        $this->assertSame(0, Resena::count());
    }

    public function test_paso_6_la_resena_publicada_se_ve_en_la_ficha_publica_con_puntuacion_comentario_y_fecha(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta('Clara Mendoza');
        $propiedad = $this->propiedad();
        $this->contrato($cliente, $propiedad);
        $this->actingAs($cuenta);
        $this->enviar($propiedad, ['puntuacion' => '4', 'comentario' => 'Muy buena ubicación']);
        auth()->logout();

        $this->get(route('catalogo.detalle', $propiedad))->assertOk()
            ->assertSee('Reseñas')->assertSee('4.0')->assertSee('1 reseña')
            ->assertSee('Muy buena ubicación')->assertSee(now()->format('d/m/Y'))
            ->assertSee('Clara')->assertDontSee('Mendoza');
    }

    public function test_un_contrato_rescindido_no_habilita_la_resena_pero_uno_finalizado_si(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $rescindida = $this->propiedad();
        $finalizada = $this->propiedad();
        $this->contrato($cliente, $rescindida, Contrato::RESCINDIDO);
        $this->contrato($cliente, $finalizada, Contrato::FINALIZADO);
        $this->actingAs($cuenta);

        $this->enviar($rescindida)->assertRedirect(route('portal.contratos'))->assertSessionHas('error');
        $this->enviar($finalizada)->assertSessionDoesntHaveErrors();

        $this->assertSame([$finalizada->idpropiedad], Resena::pluck('idpropiedad')->all());
    }

    public function test_el_personal_recibe_403_y_un_visitante_va_al_login(): void
    {
        $propiedad = $this->propiedad();

        $this->get(route('resenas.crear', $propiedad))->assertRedirect(route('login'));
        $this->post(route('resenas.guardar', $propiedad), ['puntuacion' => '5'])->assertRedirect(route('login'));

        foreach ([$this->admin(), $this->agente(), $this->asistente()] as $personal) {
            $this->actingAs($personal)->get(route('resenas.crear', $propiedad))->assertForbidden();
            $this->post(route('resenas.guardar', $propiedad), ['puntuacion' => '5'])->assertForbidden();
        }
        $this->assertSame(0, Resena::count());
    }

    public function test_el_comentario_vacio_se_guarda_solo_con_la_puntuacion_y_uno_largo_se_rechaza(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $propiedad = $this->propiedad();
        $this->contrato($cliente, $propiedad);
        $this->actingAs($cuenta);

        $this->enviar($propiedad, ['comentario' => str_repeat('a', 1001)])
            ->assertSessionHasErrors(['comentario' => 'El comentario no puede superar los 1000 caracteres.']);
        $this->assertSame(0, Resena::count());

        $this->enviar($propiedad, ['puntuacion' => '3', 'comentario' => '   '])->assertSessionDoesntHaveErrors();
        $this->assertNull(Resena::first()->comentario);
        $this->get(route('catalogo.detalle', $propiedad))->assertSee('3.0');
    }

    public function test_el_promedio_de_la_ficha_cuenta_todas_las_resenas_y_sin_resenas_dice_aun_sin_resenas(): void
    {
        $propiedad = $this->propiedad();
        $this->get(route('catalogo.detalle', $propiedad))->assertOk()->assertSee('Aún sin reseñas');

        foreach ([5, 3] as $puntos) {
            [$cuenta, $cliente] = $this->clienteConCuenta();
            $this->contrato($cliente, $propiedad);
            $this->actingAs($cuenta);
            $this->enviar($propiedad, ['puntuacion' => (string) $puntos]);
        }
        auth()->logout();

        $this->get(route('catalogo.detalle', $propiedad))->assertSee('4.0')->assertSee('2 reseñas');
    }

    public function test_el_portal_ofrece_calificar_la_propiedad_o_ver_la_resena(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $propiedad = $this->propiedad();
        $contrato = $this->contrato($cliente, $propiedad);
        $this->actingAs($cuenta);

        $this->get(route('portal.contrato', $contrato))->assertOk()
            ->assertSee('Calificar propiedad')->assertSee(route('resenas.crear', $propiedad), false);

        $this->enviar($propiedad);
        $this->get(route('portal.contrato', $contrato))->assertSee('Ver mi reseña')->assertDontSee('Calificar propiedad');

        $rescindido = $this->contrato($cliente, null, Contrato::RESCINDIDO);
        $this->get(route('portal.contrato', $rescindido))->assertDontSee('Calificar propiedad');
    }
}