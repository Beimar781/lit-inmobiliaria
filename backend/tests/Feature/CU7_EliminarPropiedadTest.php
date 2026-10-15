<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Historial;
use App\Models\Propiedad;

/** CU7: Eliminar propiedad = baja lógica (solo Administrador). */
class CU7_EliminarPropiedadTest extends SistemaTestCase
{
    private function darDeBaja(Propiedad $propiedad, string $motivo = 'El propietario retiró el inmueble')
    {
        return $this->delete(route('propiedades.destroy', $propiedad), ['motivo' => $motivo]);
    }

    // ------------------------------------------------------------------- acceso

    public function test_el_invitado_es_enviado_al_login(): void
    {
        $propiedad = $this->propiedad();

        $this->get(route('propiedades.baja', $propiedad))->assertRedirect(route('login'));
        $this->darDeBaja($propiedad)->assertRedirect(route('login'));

        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);
    }

    public function test_solo_el_administrador_puede_dar_de_baja(): void
    {
        $propiedad = $this->propiedad();

        foreach ([$this->agente(), $this->asistente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario);
            $this->get(route('propiedades.baja', $propiedad))->assertForbidden();
            $this->darDeBaja($propiedad)->assertForbidden();
        }

        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);
        $this->assertSame(0, Historial::count());
    }

    public function test_el_boton_dar_de_baja_solo_lo_ve_el_administrador(): void
    {
        $propiedad = $this->propiedad();

        $this->actingAs($this->agente())->get(route('propiedades.index'))
            ->assertOk()
            ->assertSee('Modificar')
            ->assertDontSee('Dar de baja');

        $this->actingAs($this->admin())->get(route('propiedades.index'))
            ->assertSee('Dar de baja')
            ->assertSee(route('propiedades.baja', $propiedad), false);
    }

    // ------------------------------------------------------------- confirmación

    public function test_se_muestra_la_pantalla_de_confirmacion_con_los_datos_de_la_propiedad(): void
    {
        $propiedad = $this->conImagenes($this->propiedad(['titulo' => 'Casa Urbarí', 'estadopropiedad' => Propiedad::VENDIDO]), 1);

        $this->actingAs($this->admin())->get(route('propiedades.baja', $propiedad))
            ->assertOk()
            ->assertSee('Dar de baja propiedad')
            ->assertSee('Casa Urbarí')
            ->assertSee('Vendido')
            ->assertSee('Motivo de la baja')
            ->assertSee('Su historial se conserva');
    }

    public function test_confirmar_una_propiedad_inexistente_o_ya_dada_de_baja_da_404(): void
    {
        $baja = $this->propiedad(['estadopropiedad' => Propiedad::BAJA]);
        $this->actingAs($this->admin());

        $this->get('/propiedades/9999/baja')->assertNotFound();
        $this->get(route('propiedades.baja', $baja))->assertNotFound();
    }

    // ------------------------------------------------------------------- la baja

    public function test_el_administrador_da_de_baja_una_propiedad(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->admin());

        $this->darDeBaja($propiedad)
            ->assertRedirect(route('propiedades.index'))
            ->assertSessionHas('status', 'La propiedad fue dada de baja.');

        $this->assertSame(Propiedad::BAJA, $propiedad->fresh()->estadopropiedad);
    }

    public function test_es_baja_logica_el_registro_y_sus_imagenes_se_conservan(): void
    {
        $propiedad = $this->conImagenes($this->propiedad(), 2);
        $this->actingAs($this->admin());

        $this->darDeBaja($propiedad);

        $this->assertDatabaseHas('propiedad', ['idpropiedad' => $propiedad->idpropiedad]);
        $this->assertDatabaseHas('ubicacion', ['idubicacion' => $propiedad->idubicacion]);
        $this->assertSame(2, $propiedad->imagenes()->count());
        foreach ($propiedad->imagenes as $imagen) {
            $this->assertFileExists($this->rutaFisica($imagen->ruta));
        }
    }

    public function test_la_propiedad_dada_de_baja_desaparece_del_catalogo(): void
    {
        $visible = $this->propiedad(['titulo' => 'Sigue visible']);
        $baja = $this->propiedad(['titulo' => 'Será dada de baja']);
        $this->actingAs($this->admin());

        $this->get(route('propiedades.index'))->assertSee('Será dada de baja');

        $this->darDeBaja($baja);

        $this->get(route('propiedades.index'))
            ->assertSee('Sigue visible')
            ->assertDontSee('Será dada de baja');
    }

    public function test_despues_de_la_baja_no_se_puede_modificar_ni_volver_a_dar_de_baja(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->admin());
        $this->darDeBaja($propiedad);

        $this->get(route('propiedades.edit', $propiedad))->assertNotFound();
        $this->get(route('propiedades.baja', $propiedad))->assertNotFound();
        $this->darDeBaja($propiedad)->assertNotFound();

        $this->assertSame(1, Historial::where('tipo', Historial::BAJA)->count(), 'la baja se registra una sola vez');
    }

    public function test_se_puede_dar_de_baja_desde_cualquier_estado_menos_reservado(): void
    {
        $this->actingAs($this->admin());

        foreach ([Propiedad::DISPONIBLE, Propiedad::VENDIDO, Propiedad::ALQUILADO] as $estado) {
            $propiedad = $this->propiedad(['estadopropiedad' => $estado]);

            $this->darDeBaja($propiedad)->assertSessionHasNoErrors();

            $this->assertSame(Propiedad::BAJA, $propiedad->fresh()->estadopropiedad, "desde {$estado}");
        }
    }

    // ------------------------------------------------------------- restricciones

    public function test_una_propiedad_reservada_no_se_puede_dar_de_baja(): void
    {
        $propiedad = $this->propiedad(['estadopropiedad' => Propiedad::RESERVADO]);
        $this->actingAs($this->admin());

        $this->darDeBaja($propiedad)
            ->assertSessionHasErrors(['propiedad' => 'No se puede dar de baja: la propiedad tiene una reserva vigente.']);

        $this->assertSame(Propiedad::RESERVADO, $propiedad->fresh()->estadopropiedad);
        $this->assertSame(0, Historial::count());
        $this->assertSame(0, Bitacora::where('accion', 'PROPIEDAD_BAJA')->count());
    }

    public function test_la_reservada_puede_darse_de_baja_cuando_deja_de_estar_reservada(): void
    {
        $propiedad = $this->propiedad(['estadopropiedad' => Propiedad::RESERVADO]);
        $this->actingAs($this->admin());

        $this->darDeBaja($propiedad)->assertSessionHasErrors('propiedad');

        // el agente libera la reserva (CU6) y entonces sí se puede
        $propiedad->update(['estadopropiedad' => Propiedad::DISPONIBLE]);
        $this->darDeBaja($propiedad)->assertSessionHasNoErrors();

        $this->assertSame(Propiedad::BAJA, $propiedad->fresh()->estadopropiedad);
    }

    public function test_el_motivo_es_obligatorio(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->admin());

        $this->delete(route('propiedades.destroy', $propiedad), [])
            ->assertSessionHasErrors(['motivo' => 'Indica el motivo de la baja.']);

        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);
    }

    public function test_el_motivo_debe_tener_entre_5_y_500_caracteres(): void
    {
        $propiedad = $this->propiedad();
        $this->actingAs($this->admin());

        $this->darDeBaja($propiedad, 'abc')
            ->assertSessionHasErrors(['motivo' => 'El motivo debe tener al menos 5 caracteres.']);

        $this->darDeBaja($propiedad, str_repeat('a', 501))->assertSessionHasErrors('motivo');

        $this->assertSame(Propiedad::DISPONIBLE, $propiedad->fresh()->estadopropiedad);

        $this->darDeBaja($propiedad, str_repeat('a', 500))->assertSessionHasNoErrors();
        $this->assertSame(Propiedad::BAJA, $propiedad->fresh()->estadopropiedad);
    }

    public function test_si_hay_error_el_motivo_escrito_se_conserva(): void
    {
        $propiedad = $this->propiedad(['estadopropiedad' => Propiedad::RESERVADO]);
        $this->actingAs($this->admin());

        $this->from(route('propiedades.baja', $propiedad))
            ->darDeBaja($propiedad, 'Motivo que no quiero perder')
            ->assertRedirect(route('propiedades.baja', $propiedad))
            ->assertSessionHasInput('motivo', 'Motivo que no quiero perder');
    }

    // ---------------------------------------------------- historial y bitácora

    public function test_la_baja_deja_historial_con_el_estado_anterior_y_el_motivo(): void
    {
        $admin = $this->admin();
        $propiedad = $this->propiedad(['estadopropiedad' => Propiedad::VENDIDO]);
        $this->actingAs($admin);

        $this->darDeBaja($propiedad, 'Venta anulada por el notario');

        $registro = Historial::where('idpropiedad', $propiedad->idpropiedad)->first();
        $this->assertNotNull($registro);
        $this->assertSame(Historial::BAJA, $registro->tipo);
        $this->assertSame('Venta anulada por el notario', $registro->motivo);
        $this->assertSame($admin->idusuario, $registro->idusuario);
        $this->assertSame(['estado' => 'VENDIDO'], json_decode($registro->valoranterior, true));
        $this->assertSame(['estado' => 'BAJA'], json_decode($registro->valoractual, true));
    }

    public function test_la_baja_queda_en_la_bitacora(): void
    {
        $admin = $this->admin();
        $propiedad = $this->propiedad(['titulo' => 'Casa para baja']);
        $this->actingAs($admin);

        $this->darDeBaja($propiedad, 'Ya no se ofrece');

        $registro = Bitacora::where('accion', 'PROPIEDAD_BAJA')->first();
        $this->assertNotNull($registro);
        $this->assertSame($admin->idusuario, $registro->idusuario);
        $this->assertSame('Propiedades', $registro->modulo);
        $this->assertStringContainsString('Casa para baja', $registro->descripcion);
        $this->assertSame('Ya no se ofrece', $registro->detalle['motivo']);
        $this->assertSame('DISPONIBLE', $registro->detalle['estado_anterior']);
    }

    public function test_el_historial_de_una_propiedad_dada_de_baja_se_conserva_completo(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('propiedades.store'), array_merge($this->datosPropiedad(), ['imagenes' => [$this->imagen()]]));
        $propiedad = Propiedad::first();

        $this->put(route('propiedades.update', $propiedad), $this->datosActuales($propiedad, ['precio' => '70000']));
        $this->darDeBaja($propiedad);

        $this->assertSame(
            [Historial::REGISTRO, Historial::MODIFICACION, Historial::BAJA],
            Historial::where('idpropiedad', $propiedad->idpropiedad)->orderBy('idhistorial')->pluck('tipo')->all()
        );
    }
}
