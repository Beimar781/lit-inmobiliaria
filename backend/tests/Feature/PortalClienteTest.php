<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use App\Models\Usuario;
use App\Services\StripeService;
use Carbon\CarbonImmutable;
use Tests\Support\StripeFalso;

/**
 * Portal del cliente «Mis contratos y pagos»: el cliente ve y paga SOLO lo suyo; lo ajeno da 404.
 * La fecha de las pruebas se fija en el martes 15 de diciembre de 2026, hora de Bolivia. Stripe se simula (nunca se llama de verdad).
 */
class PortalClienteTest extends SistemaTestCase
{
    private StripeFalso $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-12-15 10:00:00', 'America/La_Paz'));
        $this->stripe = new StripeFalso();
        $this->app->instance(StripeService::class, $this->stripe);
        config(['services.stripe.webhook_secret' => 'whsec_prueba', 'services.stripe.secret' => 'sk_test_no_se_usa']);
    }

    /** Un cliente con su cuenta de acceso ya creada. @return array{0: Usuario, 1: Cliente} */
    private function clienteConCuenta(string $nombre = 'Clara Cliente'): array
    {
        $cuenta = $this->cliente(['nombre' => $nombre, 'email' => strtolower(str_replace(' ', '', $nombre)) . '@correo.com']);
        $cliente = Cliente::create(['nombre' => $nombre, 'email' => $cuenta->email, 'idusuariocuenta' => $cuenta->idusuario]);

        return [$cuenta, $cliente];
    }

    private function contrato(Cliente $cliente, float $monto = 1000, string $estado = Contrato::VIGENTE, string $titulo = 'Casa de prueba'): Contrato
    {
        return Contrato::create([
            'idpropiedad' => $this->propiedad(['titulo' => $titulo])->idpropiedad, 'idcliente' => $cliente->idcliente,
            'tipocontrato' => 'Venta', 'monto' => $monto, 'fechainicio' => '2026-12-01', 'estado' => $estado,
        ]);
    }

    private function pago(Contrato $contrato, float $monto = 100, string $estado = Pago::PAGADO, ?string $comprobante = null, string $metodo = Pago::EFECTIVO): Pago
    {
        return Pago::create([
            'idcontrato' => $contrato->idcontrato, 'monto' => $monto, 'montousd' => $monto, 'moneda' => 'USD',
            'fechapago' => '2026-12-10 10:00:00', 'estado' => $estado, 'metodo' => $metodo, 'comprobante' => $comprobante,
        ]);
    }

    // ============================================ el cliente solo ve lo suyo

    public function test_el_cliente_ve_sus_contratos_con_monto_pagado_saldo_y_estado(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente, 1000, Contrato::VIGENTE, 'Casa de Clara');
        $this->pago($contrato, 300, Pago::PAGADO, 'CP-2026-000001');
        $this->pago($contrato, 50, Pago::PENDIENTE);
        $this->contrato($cliente, 500, Contrato::FINALIZADO, 'Depto de Clara');

        $this->actingAs($cuenta)->get(route('portal.contratos'))
            ->assertOk()
            ->assertSee('Mis contratos y pagos')
            ->assertSee('Casa de Clara')->assertSee('Depto de Clara')
            ->assertSee('Vigente')->assertSee('Finalizado')
            ->assertSee('USD 1,000.00')->assertSee('USD 300.00')->assertSee('USD 700.00')
            ->assertSee(route('portal.contrato', $contrato), false);
    }

    public function test_el_cliente_no_ve_los_contratos_de_otro_cliente(): void
    {
        [$clara] = $this->clienteConCuenta('Clara Cliente');
        [, $otro] = $this->clienteConCuenta('Otro Cliente');
        $this->contrato($otro, 1000, Contrato::VIGENTE, 'Casa del otro');

        $this->actingAs($clara)->get(route('portal.contratos'))
            ->assertOk()->assertDontSee('Casa del otro')->assertSee('Todavía no tienes contratos registrados.');
    }

    public function test_el_detalle_muestra_los_pagos_y_el_comprobante_solo_de_los_pagados(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente);
        $pagado = $this->pago($contrato, 300, Pago::PAGADO, 'CP-2026-000001');
        $pendiente = $this->pago($contrato, 50, Pago::PENDIENTE, null, Pago::TARJETA);

        $this->actingAs($cuenta)->get(route('portal.contrato', $contrato))
            ->assertOk()
            ->assertSee('Monto del contrato')->assertSee('USD 1,000.00')->assertSee('USD 700.00')
            ->assertSee('Mis pagos')->assertSee('Pagado')->assertSee('Pendiente')
            ->assertSee(route('portal.comprobante', $pagado), false)
            ->assertDontSee(route('portal.comprobante', $pendiente), false);
    }

    public function test_un_contrato_de_otro_cliente_da_404(): void
    {
        [$clara] = $this->clienteConCuenta('Clara Cliente');
        [, $otro] = $this->clienteConCuenta('Otro Cliente');
        $ajeno = $this->contrato($otro);

        $this->actingAs($clara)->get(route('portal.contrato', $ajeno))->assertNotFound();
        $this->actingAs($clara)->post(route('portal.pagar', $ajeno), ['monto' => '100'])->assertNotFound();
        $this->assertSame(0, Pago::count());
        $this->assertSame([], $this->stripe->sesiones);
    }

    public function test_un_contrato_inexistente_da_404(): void
    {
        [$cuenta] = $this->clienteConCuenta();

        $this->actingAs($cuenta)->get('/mis-contratos/9999')->assertNotFound();
    }

    public function test_una_cuenta_de_cliente_sin_cliente_enlazado_ve_un_aviso(): void
    {
        $this->actingAs($this->cliente())->get(route('portal.contratos'))
            ->assertOk()->assertSee('Tu cuenta todavía no está asociada a un cliente');
    }

    // ============================================ comprobantes propios

    public function test_el_cliente_ve_sus_comprobantes_con_el_mismo_numero_y_puede_imprimirlos(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente, 1000, Contrato::VIGENTE, 'Casa de Clara');
        $pago = $this->pago($contrato, 300, Pago::PAGADO, 'CP-2026-000007');

        $this->actingAs($cuenta)->get(route('portal.comprobante', $pago))
            ->assertOk()
            ->assertSee('Comprobante de pago')->assertSee('CP-2026-000007')
            ->assertSee('Clara Cliente')->assertSee('Casa de Clara')->assertSee('USD 300.00')->assertSee('USD 700.00')
            ->assertSee('window.print()', false)
            ->assertSee('Volver a mi contrato')->assertSee(route('portal.contrato', $contrato), false);

        $this->assertSame('CP-2026-000007', $pago->fresh()->comprobante);
    }

    public function test_el_comprobante_de_un_pago_de_otro_cliente_da_404(): void
    {
        [$clara] = $this->clienteConCuenta('Clara Cliente');
        [, $otro] = $this->clienteConCuenta('Otro Cliente');
        $ajeno = $this->pago($this->contrato($otro), 100, Pago::PAGADO, 'CP-2026-000009');

        $this->actingAs($clara)->get(route('portal.comprobante', $ajeno))->assertNotFound()->assertDontSee('CP-2026-000009');
        $this->actingAs($clara)->get('/mis-contratos/pagos/9999/comprobante')->assertNotFound();
        $this->actingAs($clara)->get('/mis-contratos/pagos/abc/comprobante')->assertNotFound();
    }

    public function test_el_comprobante_de_un_pago_pendiente_o_anulado_propio_no_esta_disponible(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente);

        foreach ([Pago::PENDIENTE, Pago::ANULADO] as $estado) {
            $this->actingAs($cuenta)->get(route('portal.comprobante', $this->pago($contrato, 50, $estado)))
                ->assertNotFound()->assertSee('Comprobante no disponible')->assertSee('Volver a mi contrato');
        }
    }

    // ============================================ el cliente no ve nada del personal

    public function test_el_cliente_no_entra_a_ningun_modulo_del_personal(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente);
        $pago = $this->pago($contrato, 100, Pago::PAGADO, 'CP-2026-000001');
        $this->actingAs($cuenta);

        foreach ([
            route('propiedades.index'), route('usuarios.index'), route('bitacora.index'), route('prospectos.index'),
            route('visitas.calendario'), route('reservas.index'), route('reservas.create'), route('contratos.index'), route('contratos.show', $contrato),
            route('pagos.index'), route('pagos.create'), route('pagos.show', $pago), route('pagos.comprobante', $pago),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_el_personal_no_entra_al_portal_del_cliente(): void
    {
        [, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente);

        foreach ([$this->admin(), $this->agente(), $this->asistente()] as $usuario) {
            $this->actingAs($usuario)->get(route('portal.contratos'))->assertForbidden();
            $this->actingAs($usuario)->get(route('portal.contrato', $contrato))->assertForbidden();
            $this->actingAs($usuario)->post(route('portal.pagar', $contrato), ['monto' => '100'])->assertForbidden();
        }
        $this->assertSame(0, Pago::count());
    }

    public function test_el_invitado_va_al_inicio_de_sesion(): void
    {
        [, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente);

        $this->get(route('portal.contratos'))->assertRedirect(route('login'));
        $this->get(route('portal.contrato', $contrato))->assertRedirect(route('login'));
        $this->post(route('portal.pagar', $contrato), ['monto' => '100'])->assertRedirect(route('login'));
    }

    public function test_la_cuenta_desactivada_pierde_el_acceso_al_portal(): void
    {
        [$cuenta] = $this->clienteConCuenta();
        $this->actingAs($cuenta)->get(route('portal.contratos'))->assertOk();

        $cuenta->update(['estado' => Usuario::INACTIVO]);

        $this->get(route('portal.contratos'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // ============================================ pagar con tarjeta

    public function test_el_cliente_paga_con_tarjeta_un_contrato_vigente_con_saldo(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente, 1000, Contrato::VIGENTE, 'Casa de Clara');

        $this->actingAs($cuenta)->get(route('portal.contrato', $contrato))
            ->assertSee('Pagar con tarjeta')->assertSee(route('portal.pagar', $contrato), false);

        $this->actingAs($cuenta)->post(route('portal.pagar', $contrato), ['monto' => '250.50'])
            ->assertRedirect('https://checkout.stripe.test/pay/cs_test_1');

        $pago = Pago::firstOrFail();
        $this->assertSame(Pago::PENDIENTE, $pago->estado);
        $this->assertSame(Pago::TARJETA, $pago->metodo);
        $this->assertSame('USD', $pago->moneda);
        $this->assertSame('250.50', (string) $pago->monto);
        $this->assertSame($cuenta->idusuario, $pago->idusuario);
        $this->assertNull($pago->comprobante);
        $sesion = $this->stripe->ultimaSesion();
        $this->assertSame(25050, $sesion['amount_total']);
        $this->assertSame('usd', $sesion['currency']);
        $this->assertSame("Pago contrato {$contrato->idcontrato} - Casa de Clara", $sesion['descripcion']);
        // Al volver, el cliente va a SU portal (no a las pantallas del personal).
        $this->assertStringEndsWith("/mis-contratos/pagos/{$pago->idpago}/tarjeta/exito?session_id={CHECKOUT_SESSION_ID}", $sesion['success_url']);
        $this->assertStringEndsWith("/mis-contratos/pagos/{$pago->idpago}/tarjeta/cancelado", $sesion['cancel_url']);
    }

    public function test_el_pago_del_cliente_se_confirma_al_volver_de_stripe_y_le_muestra_su_comprobante(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente, 1000);
        $this->actingAs($cuenta)->post(route('portal.pagar', $contrato), ['monto' => '300']);
        $pago = Pago::firstOrFail();
        $this->stripe->pagar('cs_test_1');

        $this->actingAs($cuenta)->get(route('portal.tarjeta.exito', $pago) . '?session_id=cs_test_1')
            ->assertRedirect(route('portal.comprobante', $pago))
            ->assertSessionHas('status', 'Tu pago con tarjeta fue confirmado. Aquí está tu comprobante.');

        $this->assertSame(Pago::PAGADO, $pago->fresh()->estado);
        $this->assertSame('CP-2026-000001', $pago->fresh()->comprobante);
        $this->assertSame(700.0, $contrato->saldoUsd());
        $this->get(route('portal.comprobante', $pago))->assertOk()->assertSee('CP-2026-000001');
        $this->assertSame(1, Bitacora::where('accion', 'PAGO_TARJETA_CONFIRMADO')->count());
    }

    public function test_refrescar_la_pagina_de_exito_no_duplica_el_comprobante(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente);
        $this->actingAs($cuenta)->post(route('portal.pagar', $contrato), ['monto' => '300']);
        $pago = Pago::firstOrFail();
        $this->stripe->pagar('cs_test_1');

        foreach ([1, 2, 3] as $vez) {
            $this->actingAs($cuenta)->get(route('portal.tarjeta.exito', $pago))->assertRedirect(route('portal.comprobante', $pago));
        }

        $this->assertSame(['CP-2026-000001'], Pago::pluck('comprobante')->all());
    }

    public function test_si_el_cliente_cancela_en_stripe_el_pago_queda_anulado_y_puede_reintentar(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente);
        $this->actingAs($cuenta)->post(route('portal.pagar', $contrato), ['monto' => '300']);
        $pago = Pago::firstOrFail();

        $this->actingAs($cuenta)->get(route('portal.tarjeta.cancelado', $pago))
            ->assertRedirect(route('portal.contrato', $contrato))
            ->assertSessionHas('error', 'El pago con tarjeta se canceló y no se cobró nada. Puedes intentarlo de nuevo.');

        $this->assertSame(Pago::ANULADO, $pago->fresh()->estado);
        $this->assertNull($pago->fresh()->comprobante);

        $this->actingAs($cuenta)->post(route('portal.pagar', $contrato), ['monto' => '300'])->assertRedirect('https://checkout.stripe.test/pay/cs_test_2');
    }

    public function test_si_stripe_aun_no_cobro_el_pago_sigue_pendiente_y_vuelve_al_contrato(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente);
        $this->actingAs($cuenta)->post(route('portal.pagar', $contrato), ['monto' => '300']);
        $pago = Pago::firstOrFail();

        $this->actingAs($cuenta)->get(route('portal.tarjeta.exito', $pago))
            ->assertRedirect(route('portal.contrato', $contrato))->assertSessionHas('error');

        $this->assertSame(Pago::PENDIENTE, $pago->fresh()->estado);
    }

    public function test_el_cliente_no_puede_confirmar_ni_cancelar_el_pago_de_otro(): void
    {
        [$clara, $claraCliente] = $this->clienteConCuenta('Clara Cliente');
        [$otro, $otroCliente] = $this->clienteConCuenta('Otro Cliente');
        $this->actingAs($otro)->post(route('portal.pagar', $this->contrato($otroCliente)), ['monto' => '300']);
        $ajeno = Pago::firstOrFail();
        $this->stripe->pagar('cs_test_1');

        $this->actingAs($clara)->get(route('portal.tarjeta.exito', $ajeno))->assertNotFound();
        $this->actingAs($clara)->get(route('portal.tarjeta.cancelado', $ajeno))->assertNotFound();

        $this->assertSame(Pago::PENDIENTE, $ajeno->fresh()->estado);
    }

    public function test_las_paginas_de_retorno_del_portal_solo_sirven_para_pagos_con_tarjeta(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $efectivo = $this->pago($this->contrato($cliente), 100, Pago::PAGADO, 'CP-2026-000001');

        $this->actingAs($cuenta)->get(route('portal.tarjeta.exito', $efectivo))->assertNotFound();
        $this->actingAs($cuenta)->get(route('portal.tarjeta.cancelado', $efectivo))->assertNotFound();
        $this->assertSame(Pago::PAGADO, $efectivo->fresh()->estado);
    }

    public function test_el_pago_del_cliente_tambien_se_confirma_por_el_webhook(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente);
        $this->actingAs($cuenta)->post(route('portal.pagar', $contrato), ['monto' => '300']);
        $pago = Pago::firstOrFail();
        $this->stripe->pagar('cs_test_1');
        $contenido = json_encode(['id' => 'evt_1', 'object' => 'event', 'type' => 'checkout.session.completed',
            'data' => ['object' => array_merge(['object' => 'checkout.session'], $this->stripe->sesiones['cs_test_1'])]]);
        $t = time();

        $this->call('POST', route('stripe.webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=" . hash_hmac('sha256', "{$t}.{$contenido}", 'whsec_prueba'), 'CONTENT_TYPE' => 'application/json',
        ], $contenido)->assertOk();

        $this->assertSame(Pago::PAGADO, $pago->fresh()->estado);
    }

    // ============================================ validaciones del pago del cliente

    public function test_el_monto_del_cliente_debe_ser_valido_y_no_superar_su_saldo(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente, 1000);
        $this->actingAs($cuenta);

        $this->post(route('portal.pagar', $contrato), ['monto' => ''])->assertSessionHasErrors(['monto' => 'Indica cuánto quieres pagar.']);
        $this->post(route('portal.pagar', $contrato), ['monto' => 'abc'])->assertSessionHasErrors(['monto' => 'El monto debe ser un número.']);
        $this->post(route('portal.pagar', $contrato), ['monto' => '0'])->assertSessionHasErrors(['monto' => 'El monto debe ser mayor a cero.']);
        $this->post(route('portal.pagar', $contrato), ['monto' => '0.49'])->assertSessionHasErrors(['monto' => 'El pago con tarjeta debe ser de al menos USD 0.50.']);
        $this->post(route('portal.pagar', $contrato), ['monto' => '10.999'])->assertSessionHasErrors('monto');
        $this->post(route('portal.pagar', $contrato), ['monto' => '1000.01'])
            ->assertSessionHasErrors(['monto' => 'El pago equivale a USD 1,000.01 y supera el saldo del contrato (USD 1,000.00).']);

        $this->assertSame(0, Pago::count());
        $this->assertSame([], $this->stripe->sesiones);
    }

    public function test_el_cliente_no_puede_pagar_un_contrato_que_no_esta_vigente(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente, 1000, Contrato::FINALIZADO);

        $this->actingAs($cuenta)->get(route('portal.contrato', $contrato))->assertDontSee(route('portal.pagar', $contrato), false)->assertSee('ya no se pueden hacer pagos');
        $this->actingAs($cuenta)->post(route('portal.pagar', $contrato), ['monto' => '100'])->assertSessionHasErrors('monto');

        $this->assertSame(0, Pago::count());
    }

    public function test_un_contrato_pagado_por_completo_no_ofrece_pagar(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente, 500);
        $this->pago($contrato, 500, Pago::PAGADO, 'CP-2026-000001');

        $this->actingAs($cuenta)->get(route('portal.contrato', $contrato))
            ->assertSee('pagado por completo')->assertDontSee('name="monto"', false);
    }

    public function test_si_stripe_no_responde_el_pago_queda_anulado_y_se_avisa(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente);
        $this->stripe->fallar = true;

        $this->actingAs($cuenta)->post(route('portal.pagar', $contrato), ['monto' => '100'])
            ->assertSessionHasErrors(['monto' => 'No se pudo conectar con Stripe para iniciar el pago con tarjeta. Intenta de nuevo en unos minutos.']);

        $this->assertSame(Pago::ANULADO, Pago::firstOrFail()->estado);
        $this->assertSame(1000.0, $contrato->saldoUsd());
    }

    public function test_el_formulario_del_portal_pide_solo_el_monto_en_dolares_y_explica_la_pagina_de_stripe(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente);

        $this->actingAs($cuenta)->get(route('portal.contrato', $contrato))
            ->assertSee('Monto a pagar (USD)')
            ->assertDontSee('name="moneda"', false)->assertDontSee('name="tipocambio"', false)
            ->assertSee('puedes pagar en dólares o en bolivianos con el tipo de cambio de Stripe');
    }

    public function test_el_portal_siempre_cobra_en_dolares_aunque_se_envie_otra_moneda(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente);

        $this->actingAs($cuenta)->post(route('portal.pagar', $contrato), ['monto' => '100', 'moneda' => 'BOB', 'tipocambio' => '6.96']);

        $pago = Pago::firstOrFail();
        $this->assertSame('USD', $pago->moneda);
        $this->assertNull($pago->tipocambio);
        $this->assertSame('100.00', (string) $pago->montousd);
        $this->assertSame('usd', $this->stripe->ultimaSesion()['currency']);
    }

    public function test_el_cliente_que_paga_en_bolivianos_en_stripe_ve_en_su_portal_los_bolivianos_los_dolares_y_su_comprobante(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente, 1000);

        $this->actingAs($cuenta)->post(route('portal.pagar', $contrato), ['monto' => '100'])
            ->assertRedirect('https://checkout.stripe.test/pay/cs_test_1');
        $pago = Pago::firstOrFail();
        $this->stripe->pagarEnMonedaLocal('cs_test_1', 70250);

        $this->actingAs($cuenta)->get(route('portal.tarjeta.exito', $pago))->assertRedirect(route('portal.comprobante', $pago));

        $pago = $pago->fresh();
        $this->assertSame(Pago::PAGADO, $pago->estado);
        $this->assertSame('USD', $pago->moneda);
        $this->assertSame('BOB', $pago->moneda_cliente);
        $this->assertSame(100.0, $contrato->totalPagadoUsd());

        $this->get(route('portal.contrato', $contrato))
            ->assertSee('USD 100.00')->assertSee('Pagaste Bs 702.50')->assertSee('tipo de cambio de Stripe: 7.025');
        $this->get(route('portal.comprobante', $pago))
            ->assertSee('Pagado por el cliente en bolivianos')->assertSee('Bs 702.50')->assertSee('7.025 Bs por USD')->assertSee('USD 100.00');
    }

    public function test_el_portal_valida_el_monto_y_el_minimo_en_dolares(): void
    {
        [$cuenta, $cliente] = $this->clienteConCuenta();
        $contrato = $this->contrato($cliente, 100);
        $this->actingAs($cuenta);

        $this->post(route('portal.pagar', $contrato), ['monto' => '0.49'])
            ->assertSessionHasErrors(['monto' => 'El pago con tarjeta debe ser de al menos USD 0.50.']);
        $this->post(route('portal.pagar', $contrato), ['monto' => '100.01'])
            ->assertSessionHasErrors(['monto' => 'El pago equivale a USD 100.01 y supera el saldo del contrato (USD 100.00).']);

        $this->assertSame(0, Pago::count());
        $this->assertSame([], $this->stripe->sesiones);
    }
}
