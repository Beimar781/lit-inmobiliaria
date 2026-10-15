<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** CU4: Gestionar usuarios (solo Administrador): listar, registrar, editar y activar/desactivar. */
class CU4_GestionarUsuariosTest extends SistemaTestCase
{
    private function datosUsuario(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Laura Pérez',
            'email' => 'laura@test.com',
            'telefono' => '71234567',
            'idrol' => $this->idRol(Rol::AGENTE),
            'estado' => Usuario::ACTIVO,
            'password' => 'Clave2026',
            'password_confirmation' => 'Clave2026',
        ], $cambios);
    }

    // ------------------------------------------------------------------- acceso

    public function test_el_invitado_es_enviado_al_login(): void
    {
        $this->get(route('usuarios.index'))->assertRedirect(route('login'));
        $this->get(route('usuarios.create'))->assertRedirect(route('login'));
        $this->post(route('usuarios.store'), $this->datosUsuario())->assertRedirect(route('login'));
    }

    public function test_solo_el_administrador_entra_al_modulo(): void
    {
        $objetivo = $this->cliente();

        foreach ([$this->agente(), $this->asistente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario);
            $this->get(route('usuarios.index'))->assertForbidden();
            $this->get(route('usuarios.create'))->assertForbidden();
            $this->post(route('usuarios.store'), $this->datosUsuario())->assertForbidden();
            $this->get(route('usuarios.edit', $objetivo))->assertForbidden();
            $this->put(route('usuarios.update', $objetivo), $this->datosUsuario())->assertForbidden();
            $this->patch(route('usuarios.estado', $objetivo))->assertForbidden();
        }

        $this->assertSame(0, Usuario::where('email', 'laura@test.com')->count());
        $this->assertTrue($objetivo->fresh()->estaActivo());
    }

    public function test_el_administrador_ve_el_modulo(): void
    {
        $this->actingAs($this->admin())->get(route('usuarios.index'))
            ->assertOk()
            ->assertSee('Registrar usuario');
    }

    // ------------------------------------------------------------------ listado

    public function test_el_listado_muestra_nombre_correo_rol_y_estado(): void
    {
        $agente = $this->agente(['nombre' => 'María Torrez', 'email' => 'maria@test.com', 'estado' => Usuario::INACTIVO]);

        $this->actingAs($this->admin())->get(route('usuarios.index'))
            ->assertOk()
            ->assertSee('María Torrez')
            ->assertSee('maria@test.com')
            ->assertSee('Agente Inmobiliario')
            ->assertSee('INACTIVO')
            ->assertSee('Activar');
    }

    public function test_el_listado_se_puede_buscar_por_nombre_o_correo(): void
    {
        $this->agente(['nombre' => 'Zulema Quiroga', 'email' => 'zq@test.com']);
        $this->agente(['nombre' => 'Pedro Vaca', 'email' => 'pedro.vaca@test.com']);
        $this->actingAs($this->admin());

        $this->get(route('usuarios.index', ['q' => 'Zulema']))->assertSee('Zulema Quiroga')->assertDontSee('Pedro Vaca');
        $this->get(route('usuarios.index', ['q' => 'pedro.vaca']))->assertSee('Pedro Vaca')->assertDontSee('Zulema Quiroga');
    }

    public function test_el_listado_se_puede_filtrar_por_rol(): void
    {
        $this->agente(['nombre' => 'Agente Visible']);
        $this->cliente(['nombre' => 'Cliente Oculto']);

        $this->actingAs($this->admin())
            ->get(route('usuarios.index', ['idrol' => $this->idRol(Rol::AGENTE)]))
            ->assertSee('Agente Visible')
            ->assertDontSee('Cliente Oculto');
    }

    public function test_el_listado_pagina_de_10_en_10(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->cliente();
        }

        $this->actingAs($this->admin());

        $this->get(route('usuarios.index'))->assertViewHas('usuarios', fn ($p) => $p->count() === 10 && $p->total() === 13);
        $this->get(route('usuarios.index', ['page' => 2]))->assertViewHas('usuarios', fn ($p) => $p->count() === 3);
    }

    public function test_el_administrador_no_tiene_boton_para_desactivarse_a_si_mismo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('usuarios.index'))
            ->assertDontSee(route('usuarios.estado', $admin), false);
    }

    // ----------------------------------------------------------------- registrar

    public function test_se_muestra_el_formulario_de_registro(): void
    {
        $this->actingAs($this->admin())->get(route('usuarios.create'))
            ->assertOk()
            ->assertSee('Registrar usuario')
            ->assertSee('Administrador')
            ->assertSee('Cliente');
    }

    public function test_el_administrador_registra_un_usuario(): void
    {
        $this->actingAs($this->admin())
            ->post(route('usuarios.store'), $this->datosUsuario())
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('status', 'Usuario registrado correctamente.');

        $nuevo = Usuario::where('email', 'laura@test.com')->first();
        $this->assertNotNull($nuevo);
        $this->assertSame('Laura Pérez', $nuevo->nombre);
        $this->assertSame('71234567', $nuevo->telefono);
        $this->assertSame(Rol::AGENTE, $nuevo->rol->nombre);
        $this->assertTrue($nuevo->estaActivo());
    }

    public function test_la_contrasena_del_usuario_nuevo_se_guarda_con_hash_y_le_sirve_para_entrar(): void
    {
        $this->actingAs($this->admin())->post(route('usuarios.store'), $this->datosUsuario());

        $guardada = DB::table('usuario')->where('email', 'laura@test.com')->value('password');
        $this->assertNotSame('Clave2026', $guardada);
        $this->assertTrue(Hash::check('Clave2026', $guardada));

        $this->post(route('logout'));
        $this->post(route('login.ingresar'), ['email' => 'laura@test.com', 'password' => 'Clave2026'])
            ->assertRedirect(route('panel'));
    }

    public function test_se_puede_registrar_un_usuario_inactivo_y_no_podra_entrar(): void
    {
        $this->actingAs($this->admin())->post(route('usuarios.store'), $this->datosUsuario(['estado' => Usuario::INACTIVO]));
        $this->post(route('logout'));

        $this->post(route('login.ingresar'), ['email' => 'laura@test.com', 'password' => 'Clave2026'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_el_telefono_es_opcional(): void
    {
        $this->actingAs($this->admin())
            ->post(route('usuarios.store'), $this->datosUsuario(['telefono' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Usuario::where('email', 'laura@test.com')->value('telefono'));
    }

    public function test_los_campos_obligatorios_al_registrar(): void
    {
        $this->actingAs($this->admin())
            ->post(route('usuarios.store'), [])
            ->assertSessionHasErrors(['nombre', 'email', 'idrol', 'estado', 'password']);
    }

    public function test_no_se_permite_un_correo_repetido(): void
    {
        $existente = $this->agente(['email' => 'repetido@test.com']);

        $this->actingAs($this->admin())
            ->post(route('usuarios.store'), $this->datosUsuario(['email' => 'repetido@test.com']))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Usuario::where('email', 'repetido@test.com')->count());
    }

    public function test_el_correo_debe_tener_formato_valido(): void
    {
        $this->actingAs($this->admin())
            ->post(route('usuarios.store'), $this->datosUsuario(['email' => 'sin-arroba']))
            ->assertSessionHasErrors('email');
    }

    public function test_el_rol_y_el_estado_deben_existir(): void
    {
        $this->actingAs($this->admin())
            ->post(route('usuarios.store'), $this->datosUsuario(['idrol' => 9999, 'estado' => 'SUSPENDIDO']))
            ->assertSessionHasErrors(['idrol', 'estado']);
    }

    public function test_el_telefono_solo_acepta_numeros(): void
    {
        $this->actingAs($this->admin())
            ->post(route('usuarios.store'), $this->datosUsuario(['telefono' => 'abc']))
            ->assertSessionHasErrors(['telefono' => 'El teléfono solo puede tener números (de 7 a 20 dígitos).']);
    }

    public function test_requisitos_de_la_contrasena_al_registrar(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('usuarios.store'), $this->datosUsuario(['password' => 'Ab1', 'password_confirmation' => 'Ab1']))
            ->assertSessionHasErrors(['password' => 'La contraseña debe tener al menos 8 caracteres.']);

        $this->post(route('usuarios.store'), $this->datosUsuario(['password' => 'SoloLetras', 'password_confirmation' => 'SoloLetras']))
            ->assertSessionHasErrors(['password' => 'La contraseña debe incluir letras y números.']);

        $this->post(route('usuarios.store'), $this->datosUsuario(['password' => '12345678', 'password_confirmation' => '12345678']))
            ->assertSessionHasErrors(['password' => 'La contraseña debe incluir letras y números.']);

        $this->post(route('usuarios.store'), $this->datosUsuario(['password_confirmation' => 'Distinta123']))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, Usuario::where('email', 'laura@test.com')->count());
    }

    public function test_el_registro_queda_en_la_bitacora(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('usuarios.store'), $this->datosUsuario());

        $registro = Bitacora::where('accion', 'USUARIO_CREADO')->first();
        $this->assertNotNull($registro);
        $this->assertSame($admin->idusuario, $registro->idusuario);
        $this->assertSame('Usuarios', $registro->modulo);
        $this->assertStringContainsString('laura@test.com', $registro->descripcion);
    }

    // -------------------------------------------------------------------- editar

    public function test_se_muestra_el_formulario_de_edicion_con_los_datos_actuales(): void
    {
        $agente = $this->agente(['nombre' => 'Carla Torres', 'email' => 'carla@test.com']);

        $this->actingAs($this->admin())->get(route('usuarios.edit', $agente))
            ->assertOk()
            ->assertSee('Editar usuario')
            ->assertSee('Carla Torres')
            ->assertSee('carla@test.com')
            ->assertSee('Déjala vacía para conservar la actual.');
    }

    public function test_el_administrador_modifica_los_datos_de_un_usuario(): void
    {
        $agente = $this->agente();

        $this->actingAs($this->admin())
            ->put(route('usuarios.update', $agente), $this->datosUsuario([
                'nombre' => 'Nombre Nuevo',
                'email' => 'nuevo@test.com',
                'telefono' => '76543210',
                'idrol' => $this->idRol(Rol::ASISTENTE),
                'password' => '',
                'password_confirmation' => '',
            ]))
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('status', 'Usuario actualizado correctamente.');

        $agente->refresh();
        $this->assertSame('Nombre Nuevo', $agente->nombre);
        $this->assertSame('nuevo@test.com', $agente->email);
        $this->assertSame('76543210', $agente->telefono);
        $this->assertSame(Rol::ASISTENTE, $agente->rol->nombre);
    }

    public function test_si_la_contrasena_se_deja_vacia_se_conserva_la_actual(): void
    {
        $agente = $this->agente();

        $this->actingAs($this->admin())->put(route('usuarios.update', $agente), $this->datosUsuario([
            'email' => $agente->email, 'password' => '', 'password_confirmation' => '',
        ]));

        $this->assertTrue(Hash::check(self::CLAVE, $agente->fresh()->password));
    }

    public function test_si_se_escribe_una_contrasena_nueva_se_cambia(): void
    {
        $agente = $this->agente();

        $this->actingAs($this->admin())->put(route('usuarios.update', $agente), $this->datosUsuario([
            'email' => $agente->email, 'password' => 'Cambiada99', 'password_confirmation' => 'Cambiada99',
        ]))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Cambiada99', $agente->fresh()->password));
    }

    public function test_al_editar_puede_conservar_su_propio_correo_pero_no_usar_el_de_otro(): void
    {
        $a = $this->agente(['email' => 'a@test.com']);
        $b = $this->agente(['email' => 'b@test.com']);
        $this->actingAs($this->admin());

        $this->put(route('usuarios.update', $a), $this->datosUsuario(['email' => 'a@test.com', 'password' => '', 'password_confirmation' => '']))
            ->assertSessionHasNoErrors();

        $this->put(route('usuarios.update', $a), $this->datosUsuario(['email' => 'b@test.com', 'password' => '', 'password_confirmation' => '']))
            ->assertSessionHasErrors('email');

        $this->assertSame('a@test.com', $a->fresh()->email);
    }

    public function test_la_contrasena_nueva_tambien_debe_cumplir_los_requisitos_al_editar(): void
    {
        $agente = $this->agente();

        $this->actingAs($this->admin())
            ->put(route('usuarios.update', $agente), $this->datosUsuario([
                'email' => $agente->email, 'password' => 'corta', 'password_confirmation' => 'corta',
            ]))
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::CLAVE, $agente->fresh()->password));
    }

    public function test_un_administrador_no_puede_cambiarse_su_propio_rol_ni_estado(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('usuarios.update', $admin), $this->datosUsuario([
            'nombre' => 'Admin Renombrado',
            'email' => $admin->email,
            'idrol' => $this->idRol(Rol::CLIENTE),
            'estado' => Usuario::INACTIVO,
            'password' => '',
            'password_confirmation' => '',
        ]));

        $admin->refresh();
        $this->assertSame('Admin Renombrado', $admin->nombre);
        $this->assertSame(Rol::ADMINISTRADOR, $admin->rol->nombre);
        $this->assertTrue($admin->estaActivo());
    }

    public function test_editar_un_usuario_inexistente_da_404(): void
    {
        $this->actingAs($this->admin())->get('/usuarios/9999/editar')->assertNotFound();
    }

    public function test_la_modificacion_queda_en_la_bitacora_con_lo_que_cambio(): void
    {
        $agente = $this->agente(['telefono' => '70000000']);

        $this->actingAs($this->admin())->put(route('usuarios.update', $agente), $this->datosUsuario([
            'nombre' => $agente->nombre, 'email' => $agente->email, 'telefono' => '79999999',
            'idrol' => $agente->idrol, 'password' => '', 'password_confirmation' => '',
        ]));

        $registro = Bitacora::where('accion', 'USUARIO_MODIFICADO')->first();
        $this->assertNotNull($registro);
        $this->assertSame('70000000', $registro->detalle['antes']['telefono']);
        $this->assertSame('79999999', $registro->detalle['despues']['telefono']);
        $this->assertFalse($registro->detalle['contrasena_cambiada']);
    }

    public function test_guardar_sin_cambios_no_llena_la_bitacora(): void
    {
        $agente = $this->agente();

        $this->actingAs($this->admin())->put(route('usuarios.update', $agente), $this->datosUsuario([
            'nombre' => $agente->nombre, 'email' => $agente->email, 'telefono' => $agente->telefono,
            'idrol' => $agente->idrol, 'estado' => $agente->estado, 'password' => '', 'password_confirmation' => '',
        ]));

        $this->assertSame(0, Bitacora::where('accion', 'USUARIO_MODIFICADO')->count());
    }

    public function test_cambiar_solo_la_contrasena_queda_en_la_bitacora_sin_mostrarla(): void
    {
        $agente = $this->agente();

        $this->actingAs($this->admin())->put(route('usuarios.update', $agente), $this->datosUsuario([
            'nombre' => $agente->nombre, 'email' => $agente->email, 'telefono' => $agente->telefono,
            'idrol' => $agente->idrol, 'password' => 'Secreta123', 'password_confirmation' => 'Secreta123',
        ]));

        $registro = Bitacora::where('accion', 'USUARIO_MODIFICADO')->first();
        $this->assertNotNull($registro);
        $this->assertTrue($registro->detalle['contrasena_cambiada']);
        $this->assertStringNotContainsString('Secreta123', json_encode($registro->getAttributes()));
    }

    // ------------------------------------------------------ activar / desactivar

    public function test_el_administrador_desactiva_y_vuelve_a_activar_una_cuenta(): void
    {
        $agente = $this->agente();
        $this->actingAs($this->admin());

        $this->patch(route('usuarios.estado', $agente))->assertSessionHas('status', 'El usuario ahora está inactivo.');
        $this->assertSame(Usuario::INACTIVO, $agente->fresh()->estado);

        $this->patch(route('usuarios.estado', $agente))->assertSessionHas('status', 'El usuario ahora está activo.');
        $this->assertSame(Usuario::ACTIVO, $agente->fresh()->estado);
    }

    public function test_desactivar_es_baja_logica_el_usuario_no_se_borra(): void
    {
        $agente = $this->agente();

        $this->actingAs($this->admin())->patch(route('usuarios.estado', $agente));

        $this->assertDatabaseHas('usuario', ['idusuario' => $agente->idusuario]);
    }

    public function test_un_usuario_desactivado_no_puede_iniciar_sesion(): void
    {
        $agente = $this->agente();
        $this->actingAs($this->admin())->patch(route('usuarios.estado', $agente));
        $this->post(route('logout'));

        $this->post(route('login.ingresar'), ['email' => $agente->email, 'password' => self::CLAVE])
            ->assertSessionHasErrors(['email' => 'Tu cuenta está inactiva o bloqueada. Comunícate con el administrador.']);
    }

    public function test_el_administrador_no_puede_desactivar_su_propia_cuenta(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('usuarios.estado', $admin))
            ->assertSessionHas('error', 'No puedes desactivar tu propia cuenta.');

        $this->assertTrue($admin->fresh()->estaActivo());
    }

    public function test_activar_y_desactivar_quedan_en_la_bitacora(): void
    {
        $agente = $this->agente();
        $this->actingAs($this->admin());

        $this->patch(route('usuarios.estado', $agente));
        $this->patch(route('usuarios.estado', $agente));

        $this->assertSame(1, Bitacora::where('accion', 'USUARIO_DESACTIVADO')->count());
        $this->assertSame(1, Bitacora::where('accion', 'USUARIO_ACTIVADO')->count());
    }
}
