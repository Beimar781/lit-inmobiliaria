<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Historial;
use App\Models\Propiedad;

/** CU8: Cambiar estado de propiedad (Administrador o Agente dueño de la propiedad). */
class CU8_CambiarEstadoTest extends SistemaTestCase
{
    private function cambiar(Propiedad $propiedad, string $estado, ?string $motivo = null)
    {
        return $this->put(route('propiedades.estado.actualizar', $propiedad), array_filter([
            'estadopropiedad' => $estado,
            'motivo' => $motivo,
        ], fn ($v) => $v !== null));
    }

    // ------------------------------------------------------------------- acceso

    public function test_el_invitado_es_enviado_al_login(): void
    {
        $propiedad = $this->propiedad();

        $this->get(route('propiedades.estado', $propiedad))->assertRedirect(route('login'));
        $this->cambiar($propiedad, Propiedad::RESERVADO)->assertRedirect(route('login'));
    }

    public function test_el_asistente_y_el_cliente_no_pueden_cambiar_el_estado(): void
    {
        $propiedad = $this->propiedad();

        foreach ([$this->asistente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario);
            $this->get(route('propiedades.estado', $propiedad))->assertForbidden();
            $this->cambiar($propiedad, Propiedad::RESERVADO)->assertForbidden();
        }

        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);
    }

    public function test_el_administrador_cambia_el_estado_de_cualquier_propiedad(): void
    {
        $propiedad = $this->propiedad([], $this->agente());
        $this->actingAs($this->admin());

        $this->get(route('propiedades.estado', $propiedad))->assertOk()->assertSee('Cambiar estado');
        $this->cambiar($propiedad, Propiedad::RESERVADO)->assertSessionHasNoErrors();

        $this->assertSame(Propiedad::RESERVADO, $propiedad->fresh()->estadopropiedad);
    }

    public function test_el_agente_cambia_el_estado_de_su_propiedad(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->actingAs($agente);

        $this->get(route('propiedades.estado', $propiedad))->assertOk();
        $this->cambiar($propiedad, Propiedad::RESERVADO)
            ->assertRedirect(route('propiedades.index'))
            ->assertSessionHas('status');

        $this->assertSame(Propiedad::RESERVADO, $propiedad->fresh()->estadopropiedad);
    }

    public function test_el_agente_no_cambia_el_estado_de_la_propiedad_de_otro(): void
    {
        $propiedad = $this->propiedad([], $this->agente());
        $this->actingAs($this->agente());

        $this->get(route('propiedades.estado', $propiedad))->assertForbidden();
        $this->cambiar($propiedad, Propiedad::RESERVADO)->assertForbidden();

        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);
    }

    public function test_el_agente_ajeno_recibe_403_aunque_mande_datos_invalidos(): void
    {
        $propiedad = $this->propiedad([], $this->agente());
        $this->actingAs($this->agente());

        // La autorización va antes que la validación: nunca un "302 con errores" que insinúe que podría cambiarla.
        $this->put(route('propiedades.estado.actualizar', $propiedad), ['estadopropiedad' => 'INVENTADO'])->assertForbidden();
        $this->put(route('propiedades.estado.actualizar', $propiedad), [])->assertForbidden();
    }

    public function test_propiedad_inexistente_o_dada_de_baja_da_404(): void
    {
        $baja = $this->propiedad(['estadopropiedad' => Propiedad::BAJA]);
        $this->actingAs($this->admin());

        $this->get('/propiedades/9999/estado')->assertNotFound();
        $this->get(route('propiedades.estado', $baja))->assertNotFound();
        $this->cambiar($baja, Propiedad::DISPONIBLE, 'x')->assertNotFound();

        $this->assertSame(Propiedad::BAJA, $baja->fresh()->estadopropiedad);
    }

    // ----------------------------------------------------------------- estados

    public function test_una_venta_se_puede_marcar_como_vendida_y_no_como_alquilada(): void
    {
        $propiedad = $this->propiedad(['tipopropiedad' => 'Venta']);
        $this->actingAs($this->admin());

        $this->cambiar($propiedad, Propiedad::ALQUILADO)->assertSessionHasErrors('estadopropiedad');
        $this->cambiar($propiedad, Propiedad::VENDIDO)->assertSessionHasNoErrors();

        $this->assertSame(Propiedad::VENDIDO, $propiedad->fresh()->estadopropiedad);
    }

    public function test_un_alquiler_y_un_anticretico_se_marcan_como_alquilados_y_no_como_vendidos(): void
    {
        $this->actingAs($this->admin());

        foreach (['Alquiler', 'Anticrético'] as $tipo) {
            $propiedad = $this->propiedad(['tipopropiedad' => $tipo]);

            $this->cambiar($propiedad, Propiedad::VENDIDO)->assertSessionHasErrors('estadopropiedad');
            $this->cambiar($propiedad, Propiedad::ALQUILADO)->assertSessionHasNoErrors();

            $this->assertSame(Propiedad::ALQUILADO, $propiedad->fresh()->estadopropiedad);
        }
    }

    public function test_la_baja_y_los_estados_inventados_no_se_eligen_aqui(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->admin());

        $this->cambiar($propiedad, Propiedad::BAJA)->assertSessionHasErrors('estadopropiedad');
        $this->cambiar($propiedad, 'INVENTADO')->assertSessionHasErrors('estadopropiedad');
        $this->put(route('propiedades.estado.actualizar', $propiedad), [])->assertSessionHasErrors('estadopropiedad');

        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);
    }

    public function test_no_se_puede_elegir_el_mismo_estado(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->admin());

        $this->cambiar($propiedad, Propiedad::DISPONIBLE)->assertSessionHasErrors(['estadopropiedad' => 'La propiedad ya está en ese estado.']);
        $this->assertSame(0, Historial::count());
    }

    public function test_volver_a_disponible_una_propiedad_cerrada_exige_motivo(): void
    {
        $propiedad = $this->propiedad(['estadopropiedad' => Propiedad::VENDIDO]);
        $this->actingAs($this->admin());

        $this->cambiar($propiedad, Propiedad::DISPONIBLE)->assertSessionHasErrors('motivo');
        $this->assertSame(Propiedad::VENDIDO, $propiedad->fresh()->estadopropiedad);

        $this->cambiar($propiedad, Propiedad::DISPONIBLE, 'Se cayó la venta')->assertSessionHasNoErrors();
        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);
    }

    public function test_reservar_no_exige_motivo_pero_el_motivo_no_puede_ser_muy_largo(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->admin());

        $this->cambiar($propiedad, Propiedad::RESERVADO, str_repeat('a', 501))->assertSessionHasErrors('motivo');
        $this->cambiar($propiedad, Propiedad::RESERVADO)->assertSessionHasNoErrors();
    }

    // -------------------------------------------------------- historial y bitácora

    public function test_el_cambio_queda_en_el_historial_y_en_la_bitacora(): void
    {
        $agente = $this->agente();
        $propiedad = $this->propiedad([], $agente);
        $this->actingAs($agente);

        $this->cambiar($propiedad, Propiedad::RESERVADO, 'Seña de 5.000 USD');

        $registro = Historial::where('idpropiedad', $propiedad->idpropiedad)->first();
        $this->assertSame(Historial::CAMBIO_ESTADO, $registro->tipo);
        $this->assertSame($agente->idusuario, $registro->idusuario);
        $this->assertSame(['estado' => 'DISPONIBLE'], json_decode($registro->valoranterior, true));
        $this->assertSame(['estado' => 'RESERVADO'], json_decode($registro->valoractual, true));
        $this->assertSame('Seña de 5.000 USD', $registro->motivo);

        $bitacora = Bitacora::where('accion', 'PROPIEDAD_ESTADO')->first();
        $this->assertNotNull($bitacora);
        $this->assertSame('Propiedades', $bitacora->modulo);
        $this->assertSame($agente->idusuario, $bitacora->idusuario);
    }

    public function test_un_cambio_rechazado_no_deja_rastro(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->admin());

        $this->cambiar($propiedad, 'INVENTADO');

        $this->assertSame(0, Historial::count());
        $this->assertSame(0, Bitacora::where('accion', 'PROPIEDAD_ESTADO')->count());
    }

    // --------------------------------------------------------------- inventario

    public function test_el_inventario_ofrece_cambiar_estado_solo_en_lo_que_el_agente_puede(): void
    {
        $agente = $this->agente();
        $propia = $this->propiedad(['titulo' => 'Mía'], $agente);
        $ajena = $this->propiedad(['titulo' => 'Ajena'], $this->agente());

        $this->actingAs($agente)->get(route('propiedades.index'))
            ->assertOk()
            ->assertSee(route('propiedades.estado', $propia), false)
            ->assertDontSee(route('propiedades.estado', $ajena), false);

        $this->actingAs($this->admin())->get(route('propiedades.index'))
            ->assertSee(route('propiedades.estado', $propia), false)
            ->assertSee(route('propiedades.estado', $ajena), false);
    }
}
