<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\ActivarCuenta;
use App\Services\StripeService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Support\StripeFalso;

/**
 * La cuenta de acceso del cliente: se crea después de su primer pago (efectivo, tarjeta confirmada o el pago inicial de una reserva).
 * La fecha de las pruebas se fija en el martes 15 de diciembre de 2026, hora de Bolivia.
 */
class CuentaDelClienteTest extends SistemaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-12-15 10:00:00', 'America/La_Paz'));
    }

    private function contrato(?string $email = 'clara@correo.com', string $nombre = 'Clara Cliente', float $monto = 1000): Contrato
    {
        return Contrato::create([
            'idpropiedad' => $this->propiedad()->idpropiedad,
            'idcliente' => Cliente::create(['nombre' => $nombre, 'email' => $email, 'telefono' => '70011223'])->idcliente,
            'tipocontrato' => 'Venta', 'monto' => $monto, 'fechainicio' => '2026-12-01', 'estado' => Contrato::VIGENTE,
        ]);
    }

    private function pagar(Contrato $contrato, array $cambios = [], ?Usuario $actor = null)
    {
        return $this->actingAs($actor ?? $this->asistente())->post(route('pagos.store'), array_merge([
            'idcontrato' => $contrato->idcontrato, 'monto' => '100', 'moneda' => 'USD', 'fechapago' => '2026-12-15', 'metodo' => 'EFECTIVO',
        ], $cambios));
    }

    // ============================================ creación tras el primer pago

    public function test_el_primer_pago_en_efectivo_crea_la_cuenta_del_cliente_con_rol_cliente(): void
    {
        Notification::fake();
        $contrato = $this->contrato();

        $this->pagar($contrato)
            ->assertSessionHas('status_cuenta', 'Se creó la cuenta de acceso de Clara Cliente y se le envió un correo para activarla.')
            ->assertSessionMissing('aviso');

        $cliente = $contrato->cliente->fresh();
        $cuenta = Usuario::findOrFail($cliente->idusuariocuenta);
        $this->assertSame('clara@correo.com', $cuenta->email);
        $this->assertSame('Clara Cliente', $cuenta->nombre);
        $this->assertSame('70011223', $cuenta->telefono);
        $this->assertSame(Usuario::ACTIVO, $cuenta->estado);
        $this->assertTrue($cuenta->tieneRol(Rol::CLIENTE));
        $this->assertSame($cuenta->idusuario, $cliente->cuenta->idusuario);
        $this->assertSame($cliente->idcliente, $cuenta->clienteAsociado->idcliente);

        $registro = Bitacora::where('accion', 'CUENTA_CLIENTE_CREADA')->firstOrFail();
        $this->assertSame('Clientes', $registro->modulo);
        $this->assertSame($cuenta->idusuario, $registro->detalle['idusuario']);
    }

    public function test_la_contrasena_inicial_es_aleatoria_y_no_se_muestra_ni_se_guarda(): void
    {
        Notification::fake();
        $contrato = $this->contrato();
        $respuesta = $this->pagar($contrato);

        $cuenta = $contrato->cliente->fresh()->cuenta;
        // Está guardada con hash (no en texto) y no coincide con nada que se pueda adivinar.
        $this->assertStringStartsWith('$2y$', $cuenta->getRawOriginal('password'));
        foreach (['clara@correo.com', 'Clara Cliente', '70011223', 'password', 'Clave123', 'Lit2026*'] as $intento) {
            $this->assertFalse(Hash::check($intento, $cuenta->password), $intento);
        }
        // No aparece en ninguna pantalla ni mensaje, ni en la bitácora.
        $this->assertStringNotContainsString($cuenta->getRawOriginal('password'), json_encode(session()->all()));
        $this->assertSame(0, Bitacora::where('descripcion', 'like', '%password%')->count());
        $respuesta->assertSessionMissing('password');
    }

    public function test_el_cliente_recibe_un_correo_con_el_asunto_activa_tu_cuenta_y_un_enlace_para_poner_su_contrasena(): void
    {
        Notification::fake();
        $contrato = $this->contrato();
        $this->pagar($contrato);

        $cuenta = $contrato->cliente->fresh()->cuenta;
        Notification::assertSentTo($cuenta, ActivarCuenta::class, function (ActivarCuenta $notificacion) use ($cuenta) {
            $correo = $notificacion->toMail($cuenta);
            $this->assertStringStartsWith('Activa tu cuenta', $correo->subject);
            $this->assertSame('Hola, Clara Cliente', $correo->greeting);
            $this->assertSame('Establecer mi contraseña', $correo->actionText);
            $this->assertStringContainsString('/restablecer-password/' . $notificacion->token, $correo->actionUrl);
            $this->assertStringContainsString('email=' . urlencode('clara@correo.com'), $correo->actionUrl);

            return true;
        });
        Notification::assertCount(1);
    }

    public function test_el_cliente_activa_su_cuenta_con_el_enlace_y_entra_a_su_portal(): void
    {
        $contrato = $this->contrato();
        $enlace = null;
        Event::listen(NotificationSending::class, function (NotificationSending $evento) use (&$enlace) {
            if ($evento->notification instanceof ActivarCuenta) {
                $enlace = $evento->notification->token;
            }
        });
        $this->pagar($contrato);
        $this->assertNotNull($enlace, 'se generó el enlace');
        auth()->logout();
        $this->flushSession();

        $this->get(route('password.reset', ['token' => $enlace, 'email' => 'clara@correo.com']))->assertOk();
        $this->post(route('password.update'), [
            'token' => $enlace, 'email' => 'clara@correo.com', 'password' => 'MiClave2026', 'password_confirmation' => 'MiClave2026',
        ])->assertRedirect(route('login'));

        $this->post(route('login.ingresar'), ['email' => 'clara@correo.com', 'password' => 'MiClave2026'])->assertRedirect(route('panel'));
        $this->get(route('portal.contratos'))->assertOk()->assertSee('Mis contratos y pagos');
    }

    public function test_varios_pagos_no_crean_la_cuenta_dos_veces(): void
    {
        Notification::fake();
        $contrato = $this->contrato();

        $this->pagar($contrato);
        $this->pagar($contrato)->assertSessionMissing('status_cuenta')->assertSessionMissing('aviso');
        $this->pagar($contrato);

        $this->assertSame(1, Usuario::where('email', 'clara@correo.com')->count());
        $this->assertSame(1, Bitacora::where('accion', 'CUENTA_CLIENTE_CREADA')->count());
        Notification::assertCount(1);
    }

    public function test_dos_contratos_del_mismo_cliente_comparten_la_misma_cuenta(): void
    {
        Notification::fake();
        $primero = $this->contrato();
        $segundo = Contrato::create([
            'idpropiedad' => $this->propiedad()->idpropiedad, 'idcliente' => $primero->idcliente,
            'tipocontrato' => 'Alquiler', 'monto' => 500, 'fechainicio' => '2026-12-01', 'estado' => Contrato::VIGENTE,
        ]);

        $this->pagar($primero);
        $this->pagar($segundo);

        $this->assertSame(1, Usuario::where('email', 'clara@correo.com')->count());
    }

    // ============================================ cliente sin correo

    public function test_un_cliente_sin_correo_no_hace_fallar_el_pago_y_se_avisa_que_registren_el_correo(): void
    {
        Notification::fake();
        $contrato = $this->contrato(null);

        $this->pagar($contrato)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Pago registrado. Saldo restante del contrato: USD 900.00.')
            ->assertSessionHas('aviso', 'Registre el correo del cliente para crear su cuenta.');

        $this->assertSame(1, Pago::count());
        $this->assertNull($contrato->cliente->fresh()->idusuariocuenta);
        $this->assertSame(0, Usuario::where('nombre', 'Clara Cliente')->count());
        Notification::assertNothingSent();
    }

    public function test_el_aviso_se_muestra_en_pantalla(): void
    {
        $contrato = $this->contrato('');

        $this->pagar($contrato);

        $this->get(route('pagos.comprobante', Pago::firstOrFail()))->assertOk()->assertSee('Registre el correo del cliente para crear su cuenta.');
    }

    public function test_si_despues_registran_el_correo_el_siguiente_pago_crea_la_cuenta(): void
    {
        Notification::fake();
        $contrato = $this->contrato(null);
        $this->pagar($contrato);

        $contrato->cliente->update(['email' => 'clara@correo.com']);
        $this->pagar($contrato)->assertSessionHas('status_cuenta');

        $this->assertNotNull($contrato->cliente->fresh()->idusuariocuenta);
    }

    // ============================================ correo que ya pertenece a otro usuario

    public function test_si_el_correo_ya_es_de_un_usuario_no_se_crea_ni_se_enlaza_nada_y_se_avisa(): void
    {
        Notification::fake();
        $existente = $this->agente(['email' => 'clara@correo.com']);
        $contrato = $this->contrato('Clara@Correo.com');

        $this->pagar($contrato)
            ->assertSessionHas('status', 'Pago registrado. Saldo restante del contrato: USD 900.00.')
            ->assertSessionHas('aviso', 'No se creó la cuenta de Clara Cliente: el correo Clara@Correo.com ya pertenece a otro usuario del sistema. Revise el correo del cliente.');

        $this->assertNull($contrato->cliente->fresh()->idusuariocuenta);
        $this->assertSame(1, Pago::count());
        $this->assertSame(1, Usuario::whereRaw('lower(email) = ?', ['clara@correo.com'])->count());
        $this->assertSame(Rol::AGENTE, $existente->fresh()->rol->nombre, 'el usuario existente no se toca');
        Notification::assertNothingSent();
        $this->assertSame(1, Bitacora::where('accion', 'CUENTA_CLIENTE_NO_CREADA')->count());
        $this->assertSame(0, Bitacora::where('accion', 'CUENTA_CLIENTE_CREADA')->count());
    }

    // ============================================ si el correo falla

    public function test_si_el_correo_falla_el_pago_y_la_cuenta_quedan_y_se_avisa(): void
    {
        Event::listen(NotificationSending::class, function () {
            throw new \RuntimeException('El proveedor de correo no responde (simulado).');
        });
        $contrato = $this->contrato();

        $this->pagar($contrato)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('aviso');

        $this->assertStringContainsString('no se pudo enviar el correo', session('aviso'));
        $this->assertStringContainsString('¿Olvidaste tu contraseña?', session('aviso'));
        $this->assertSame(1, Pago::count());
        $this->assertNotNull($contrato->cliente->fresh()->idusuariocuenta);
    }

    // ============================================ tarjeta y pago inicial de una reserva

    public function test_el_pago_con_tarjeta_confirmado_crea_la_cuenta_una_sola_vez(): void
    {
        Notification::fake();
        $stripe = new StripeFalso();
        $this->app->instance(StripeService::class, $stripe);
        config(['services.stripe.webhook_secret' => 'whsec_prueba', 'services.stripe.secret' => 'sk_test_no_se_usa']);
        $contrato = $this->contrato();
        $asistente = $this->asistente();

        $this->pagar($contrato, ['metodo' => 'TARJETA', 'monto' => '300'], $asistente);
        $pago = Pago::firstOrFail();
        $this->assertNull($contrato->cliente->fresh()->idusuariocuenta, 'mientras el pago está pendiente todavía no es cliente formal');
        $stripe->pagar('cs_test_1');

        $this->actingAs($asistente)->get(route('pagos.tarjeta.exito', $pago))->assertSessionHas('status_cuenta');
        $this->actingAs($asistente)->get(route('pagos.tarjeta.exito', $pago));

        $this->assertNotNull($contrato->cliente->fresh()->idusuariocuenta);
        $this->assertSame(1, Usuario::where('email', 'clara@correo.com')->count());
        Notification::assertCount(1);
    }

    public function test_un_pago_con_tarjeta_cancelado_no_crea_la_cuenta(): void
    {
        Notification::fake();
        $stripe = new StripeFalso();
        $this->app->instance(StripeService::class, $stripe);
        config(['services.stripe.secret' => 'sk_test_no_se_usa']);
        $contrato = $this->contrato();
        $asistente = $this->asistente();

        $this->pagar($contrato, ['metodo' => 'TARJETA', 'monto' => '300'], $asistente);
        $this->actingAs($asistente)->get(route('pagos.tarjeta.cancelado', Pago::firstOrFail()));

        $this->assertNull($contrato->cliente->fresh()->idusuariocuenta);
        Notification::assertNothingSent();
    }

    public function test_el_pago_inicial_que_viene_de_una_reserva_tambien_crea_la_cuenta(): void
    {
        Notification::fake();
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $cliente = Cliente::create(['nombre' => 'Clara Cliente', 'email' => 'clara@correo.com', 'idusuario' => $ana->idusuario]);
        $this->actingAs($this->asistente())->post(route('reservas.store'), [
            'idpropiedad' => $propiedad->idpropiedad, 'idcliente' => $cliente->idcliente, 'monto' => '1000', 'moneda' => 'USD', 'fechaplazo' => '2026-12-30',
        ])->assertSessionHasNoErrors();
        $this->assertNull($cliente->fresh()->idusuariocuenta, 'una reserva sola todavía no hace al cliente formal');

        $this->actingAs($ana)->post(route('contratos.store'), [
            'idpropiedad' => $propiedad->idpropiedad, 'idcliente' => $cliente->idcliente, 'monto' => '85000', 'fechainicio' => '2026-12-20',
        ])->assertSessionHas('status_cuenta');

        $this->assertNotNull($cliente->fresh()->idusuariocuenta);
        Notification::assertCount(1);
    }

    public function test_un_contrato_sin_pago_inicial_no_crea_la_cuenta(): void
    {
        Notification::fake();
        $ana = $this->agente();
        $propiedad = $this->propiedad([], $ana);
        $cliente = Cliente::create(['nombre' => 'Clara Cliente', 'email' => 'clara@correo.com', 'idusuario' => $ana->idusuario]);

        $this->actingAs($ana)->post(route('contratos.store'), [
            'idpropiedad' => $propiedad->idpropiedad, 'idcliente' => $cliente->idcliente, 'monto' => '85000', 'fechainicio' => '2026-12-20',
        ])->assertSessionHasNoErrors();

        $this->assertNull($cliente->fresh()->idusuariocuenta);
        Notification::assertNothingSent();
    }

    // ============================================ CU4 y CU3 siguen funcionando con estas cuentas

    public function test_el_administrador_ve_y_gestiona_la_cuenta_del_cliente_en_el_cu4(): void
    {
        Notification::fake();
        $contrato = $this->contrato();
        $this->pagar($contrato);
        $cuenta = $contrato->cliente->fresh()->cuenta;
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('usuarios.index'))->assertOk()->assertSee('clara@correo.com');
        $this->actingAs($admin)->get(route('usuarios.edit', $cuenta))->assertOk();
        $this->actingAs($admin)->patch(route('usuarios.estado', $cuenta))->assertSessionHasNoErrors();

        $this->assertSame(Usuario::INACTIVO, $cuenta->fresh()->estado);
        $this->assertNotNull($contrato->cliente->fresh()->idusuariocuenta, 'desactivar la cuenta no borra el cliente');
    }

    public function test_una_cuenta_de_cliente_desactivada_no_puede_entrar(): void
    {
        $cuenta = $this->cliente(['email' => 'inactiva@correo.com', 'estado' => Usuario::INACTIVO]);

        $this->post(route('login.ingresar'), ['email' => 'inactiva@correo.com', 'password' => self::CLAVE])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_el_cliente_puede_recuperar_su_contrasena_con_el_cu3(): void
    {
        Notification::fake();
        $contrato = $this->contrato();
        $this->pagar($contrato);
        $cuenta = $contrato->cliente->fresh()->cuenta;
        auth()->logout();
        $this->flushSession();

        // Recién enviado el enlace de activación el CU3 pide esperar 60 segundos antes de otro enlace.
        $this->post(route('password.email'), ['email' => 'clara@correo.com'])->assertSessionHasErrors('email');

        $this->travel(2)->minutes();
        $this->post(route('password.email'), ['email' => 'clara@correo.com'])->assertSessionHas('status');

        Notification::assertSentTo($cuenta, ResetPassword::class);
    }

    public function test_los_clientes_de_los_datos_de_ejemplo_siguen_sin_cuenta(): void
    {
        $cliente = Cliente::create(['nombre' => 'Prospecto', 'email' => 'prospecto@correo.com']);

        $this->assertNull($cliente->idusuariocuenta);
        $this->assertSame(0, Usuario::where('email', 'prospecto@correo.com')->count());
    }
}
