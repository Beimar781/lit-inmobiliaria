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
 * CU28 con tarjeta (Stripe Checkout, modo de prueba). Ninguna prueba llama a Stripe: se usa un doble (tests/Support/StripeFalso.php).
 * La fecha de las pruebas se fija en el martes 15 de diciembre de 2026, hora de Bolivia.
 */
class CU28_PagoConTarjetaTest extends SistemaTestCase
{
    private const SECRETO = 'whsec_secreto_de_prueba';

    private StripeFalso $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-12-15 10:00:00', 'America/La_Paz'));
        $this->stripe = new StripeFalso();
        $this->app->instance(StripeService::class, $this->stripe);
        config(['services.stripe.webhook_secret' => self::SECRETO, 'services.stripe.secret' => 'sk_test_no_se_usa']);
    }

    private function contrato(float $monto = 1000, string $estado = Contrato::VIGENTE): Contrato
    {
        return Contrato::create([
            'idpropiedad' => $this->propiedad(['titulo' => 'Casa de Equipetrol'])->idpropiedad,
            'idcliente' => Cliente::create(['nombre' => 'Clara Cliente', 'email' => uniqid() . '@correo.com'])->idcliente,
            'tipocontrato' => 'Venta', 'monto' => $monto, 'fechainicio' => '2026-12-01', 'estado' => $estado,
        ]);
    }

    private function pagarConTarjeta(Usuario $actor, Contrato $contrato, array $cambios = [])
    {
        return $this->actingAs($actor)->post(route('pagos.store'), array_merge([
            'idcontrato' => $contrato->idcontrato, 'monto' => '300', 'moneda' => 'USD', 'fechapago' => '2026-12-15', 'metodo' => 'TARJETA',
        ], $cambios));
    }

    /** Inicia un pago con tarjeta y devuelve el pago pendiente. */
    private function pendiente(Contrato $contrato, string $monto = '300'): Pago
    {
        $this->pagarConTarjeta($this->asistente(), $contrato, ['monto' => $monto])->assertSessionHasNoErrors();

        return Pago::orderByDesc('idpago')->firstOrFail();
    }

    private function firma(string $contenido, string $secreto = self::SECRETO): string
    {
        $t = time();

        return "t={$t},v1=" . hash_hmac('sha256', "{$t}.{$contenido}", $secreto);
    }

    private function evento(array $sesion, string $tipo = 'checkout.session.completed'): string
    {
        return json_encode(['id' => 'evt_test_1', 'object' => 'event', 'type' => $tipo, 'data' => ['object' => array_merge(['object' => 'checkout.session'], $sesion)]]);
    }

    private function webhook(string $contenido, ?string $firma)
    {
        return $this->call('POST', route('stripe.webhook'), [], [], [], $firma ? ['HTTP_STRIPE_SIGNATURE' => $firma, 'CONTENT_TYPE' => 'application/json'] : ['CONTENT_TYPE' => 'application/json'], $contenido);
    }

    // ============================================ paso 1 y 2: iniciar el pago

    public function test_el_pago_con_tarjeta_se_guarda_pendiente_y_manda_al_cliente_a_stripe(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato(1000);

        $respuesta = $this->pagarConTarjeta($asistente, $contrato);

        $pago = Pago::firstOrFail();
        $respuesta->assertRedirect('https://checkout.stripe.test/pay/cs_test_1');
        $this->assertSame(Pago::PENDIENTE, $pago->estado);
        $this->assertSame(Pago::TARJETA, $pago->metodo);
        $this->assertSame('USD', $pago->moneda);
        $this->assertSame('300.00', (string) $pago->monto);
        $this->assertSame('300.00', (string) $pago->montousd);
        $this->assertSame('cs_test_1', $pago->stripe_session_id);
        $this->assertNull($pago->stripe_payment_intent);
        $this->assertNull($pago->comprobante, 'todavía no hay comprobante: el pago no está confirmado');
        $this->assertSame($asistente->idusuario, $pago->idusuario);
        $this->assertSame(0.0, $contrato->totalPagadoUsd(), 'un pago pendiente no descuenta el saldo');
        $this->assertSame(1000.0, $contrato->saldoUsd());
    }

    public function test_la_sesion_de_stripe_se_crea_con_los_datos_del_pago(): void
    {
        $contrato = $this->contrato(1000);
        $this->pagarConTarjeta($this->asistente(), $contrato, ['monto' => '300.50']);

        $pago = Pago::firstOrFail();
        $sesion = $this->stripe->ultimaSesion();
        $this->assertSame(30050, $sesion['amount_total'], 'el monto va en centavos');
        $this->assertSame('usd', $sesion['currency']);
        $this->assertSame("Pago contrato {$contrato->idcontrato} - Casa de Equipetrol", $sesion['descripcion']);
        $this->assertSame((string) $pago->idpago, $sesion['client_reference_id']);
        $this->assertSame((string) $pago->idpago, $sesion['metadata']['idpago']);
        $this->assertSame((string) $contrato->idcontrato, $sesion['metadata']['idcontrato']);
        $this->assertStringEndsWith("/pagos/{$pago->idpago}/tarjeta/exito?session_id={CHECKOUT_SESSION_ID}", $sesion['success_url']);
        $this->assertStringEndsWith("/pagos/{$pago->idpago}/tarjeta/cancelado", $sesion['cancel_url']);
    }

    public function test_iniciar_el_pago_queda_en_la_bitacora_sin_comprobante(): void
    {
        $this->pagarConTarjeta($this->asistente(), $this->contrato());

        $registro = Bitacora::where('accion', 'PAGO_TARJETA_INICIADO')->firstOrFail();
        $this->assertSame('Pagos', $registro->modulo);
        $this->assertSame('cs_test_1', $registro->detalle['stripe_session_id']);
        $this->assertSame(0, Bitacora::where('accion', 'COMPROBANTE_GENERADO')->count());
    }

    // ============================================ paso 3: el cliente paga y vuelve

    public function test_al_volver_de_stripe_se_confirma_el_pago_consultando_a_stripe_y_se_genera_el_comprobante(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato(1000);
        $pago = $this->pendiente($contrato);
        $this->stripe->pagar('cs_test_1');

        $this->actingAs($asistente)->get(route('pagos.tarjeta.exito', $pago) . '?session_id=cs_test_1')
            ->assertRedirect(route('pagos.comprobante', $pago))
            ->assertSessionHas('status', 'Pago con tarjeta confirmado. Su comprobante está listo para imprimir.');

        $pago->refresh();
        $this->assertSame(Pago::PAGADO, $pago->estado);
        $this->assertSame('pi_test_cs_test_1', $pago->stripe_payment_intent);
        $this->assertSame('CP-2026-000001', $pago->comprobante);
        $this->assertSame(300.0, $contrato->totalPagadoUsd());
        $this->assertSame(700.0, $contrato->saldoUsd());

        $this->get(route('pagos.comprobante', $pago))->assertOk()->assertSee('CP-2026-000001')->assertSee('Tarjeta')->assertSee('USD 300.00');
        $this->assertSame(1, Bitacora::where('accion', 'PAGO_TARJETA_CONFIRMADO')->count());
    }

    public function test_si_stripe_dice_que_todavia_no_se_cobro_el_pago_sigue_pendiente(): void
    {
        $asistente = $this->asistente();
        $pago = $this->pendiente($this->contrato());

        $this->actingAs($asistente)->get(route('pagos.tarjeta.exito', $pago) . '?session_id=cs_test_1')
            ->assertRedirect(route('pagos.create', ['contrato' => $pago->idcontrato]))
            ->assertSessionHas('error');

        $this->assertSame(Pago::PENDIENTE, $pago->fresh()->estado);
        $this->assertNull($pago->fresh()->comprobante);
    }

    public function test_no_se_confia_en_los_parametros_de_la_url(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato();
        $primero = $this->pendiente($contrato);
        $segundo = $this->pendiente($contrato, '100');
        $this->stripe->pagar('cs_test_2');   // solo el segundo se cobró

        // Aunque la URL del primero traiga la sesión del segundo (que sí está pagada), se usa la sesión guardada en el pago.
        $this->actingAs($asistente)->get(route('pagos.tarjeta.exito', $primero) . '?session_id=cs_test_2&payment_status=paid')
            ->assertSessionHas('error');

        $this->assertSame(Pago::PENDIENTE, $primero->fresh()->estado);
        $this->assertSame(Pago::PENDIENTE, $segundo->fresh()->estado);
    }

    public function test_si_los_datos_de_stripe_no_coinciden_con_el_pago_no_se_confirma(): void
    {
        $asistente = $this->asistente();
        $pago = $this->pendiente($this->contrato(), '300');
        $this->stripe->pagar('cs_test_1');
        $this->stripe->sesiones['cs_test_1']['amount_total'] = 100; // Stripe cobró un monto distinto

        $this->actingAs($asistente)->get(route('pagos.tarjeta.exito', $pago))->assertSessionHas('error');

        $this->assertSame(Pago::PENDIENTE, $pago->fresh()->estado);
        $this->assertSame(1, Bitacora::where('accion', 'PAGO_TARJETA_DISCREPANCIA')->count());
    }

    public function test_una_falla_al_consultar_a_stripe_avisa_y_no_cambia_el_pago(): void
    {
        $asistente = $this->asistente();
        $pago = $this->pendiente($this->contrato());
        $this->stripe->pagar('cs_test_1');
        $this->stripe->fallarAlConsultar = true;

        $this->actingAs($asistente)->get(route('pagos.tarjeta.exito', $pago))->assertSessionHas('error');

        $this->assertSame(Pago::PENDIENTE, $pago->fresh()->estado);
    }

    // ============================================ paso 4: cancelar

    public function test_si_el_cliente_cancela_el_pago_queda_anulado_sin_comprobante(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato(1000);
        $pago = $this->pendiente($contrato);

        $this->actingAs($asistente)->get(route('pagos.tarjeta.cancelado', $pago))
            ->assertRedirect(route('pagos.create', ['contrato' => $contrato->idcontrato]))
            ->assertSessionHas('error', 'El pago con tarjeta se canceló y no se cobró nada. Puedes intentarlo de nuevo.');

        $this->assertSame(Pago::ANULADO, $pago->fresh()->estado);
        $this->assertNull($pago->fresh()->comprobante);
        $this->assertSame(1000.0, $contrato->saldoUsd());
        $this->get(route('pagos.comprobante', $pago))->assertNotFound()->assertSee('Comprobante no disponible');
        $this->assertSame(1, Bitacora::where('accion', 'PAGO_TARJETA_CANCELADO')->count());
    }

    public function test_despues_de_cancelar_se_puede_intentar_de_nuevo(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato(1000);
        $primero = $this->pendiente($contrato);
        $this->actingAs($asistente)->get(route('pagos.tarjeta.cancelado', $primero));

        $segundo = $this->pendiente($contrato);
        $this->stripe->pagar('cs_test_2');
        $this->actingAs($asistente)->get(route('pagos.tarjeta.exito', $segundo))->assertRedirect(route('pagos.comprobante', $segundo));

        $this->assertSame(Pago::ANULADO, $primero->fresh()->estado);
        $this->assertSame(Pago::PAGADO, $segundo->fresh()->estado);
        $this->assertSame(700.0, $contrato->saldoUsd());
    }

    public function test_cancelar_no_anula_un_pago_que_ya_estaba_pagado(): void
    {
        $asistente = $this->asistente();
        $pago = $this->pendiente($this->contrato());
        $this->stripe->pagar('cs_test_1');
        $this->actingAs($asistente)->get(route('pagos.tarjeta.exito', $pago));

        $this->actingAs($asistente)->get(route('pagos.tarjeta.cancelado', $pago))->assertRedirect(route('pagos.comprobante', $pago));

        $this->assertSame(Pago::PAGADO, $pago->fresh()->estado);
        $this->assertSame(0, Bitacora::where('accion', 'PAGO_TARJETA_CANCELADO')->count());
    }

    // ============================================ paso 5: webhook

    public function test_el_webhook_con_firma_valida_confirma_el_pago_y_genera_el_comprobante(): void
    {
        $pago = $this->pendiente($this->contrato());
        $this->stripe->pagar('cs_test_1');
        $contenido = $this->evento($this->stripe->sesiones['cs_test_1']);

        $this->webhook($contenido, $this->firma($contenido))->assertOk()->assertJson(['recibido' => true]);

        $pago->refresh();
        $this->assertSame(Pago::PAGADO, $pago->estado);
        $this->assertSame('pi_test_cs_test_1', $pago->stripe_payment_intent);
        $this->assertSame('CP-2026-000001', $pago->comprobante);
    }

    public function test_el_webhook_con_firma_invalida_se_rechaza_y_no_toca_nada(): void
    {
        $pago = $this->pendiente($this->contrato());
        $this->stripe->pagar('cs_test_1');
        $contenido = $this->evento($this->stripe->sesiones['cs_test_1']);

        $this->webhook($contenido, $this->firma($contenido, 'whsec_otro_secreto'))->assertStatus(400);
        $this->webhook($contenido, 't=123,v1=abc')->assertStatus(400);
        $this->webhook($contenido, null)->assertStatus(400);
        $this->webhook('esto no es json', $this->firma('esto no es json'))->assertStatus(400);

        $this->assertSame(Pago::PENDIENTE, $pago->fresh()->estado);
        $this->assertNull($pago->fresh()->comprobante);
    }

    public function test_el_webhook_con_la_firma_de_otro_contenido_se_rechaza(): void
    {
        $pago = $this->pendiente($this->contrato());
        $this->stripe->pagar('cs_test_1');
        $original = $this->evento($this->stripe->sesiones['cs_test_1']);
        $alterado = str_replace('"amount_total":30000', '"amount_total":1', $original);

        $this->webhook($alterado, $this->firma($original))->assertStatus(400);
        $this->assertSame(Pago::PENDIENTE, $pago->fresh()->estado);
    }

    public function test_el_webhook_no_necesita_sesion_ni_token_csrf(): void
    {
        // Ningún usuario con sesión iniciada y sin token: la firma es lo único que cuenta.
        $this->pendiente($this->contrato());
        $this->stripe->pagar('cs_test_1');
        $contenido = $this->evento($this->stripe->sesiones['cs_test_1']);
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->webhook($contenido, $this->firma($contenido))->assertOk();
    }

    public function test_el_webhook_ignora_otros_eventos_y_pagos_que_no_son_de_este_sistema(): void
    {
        $pago = $this->pendiente($this->contrato());
        $this->stripe->pagar('cs_test_1');

        $otroTipo = $this->evento($this->stripe->sesiones['cs_test_1'], 'payment_intent.created');
        $this->webhook($otroTipo, $this->firma($otroTipo))->assertOk();
        $this->assertSame(Pago::PENDIENTE, $pago->fresh()->estado);

        $ajeno = $this->evento(['id' => 'cs_test_999', 'payment_status' => 'paid', 'amount_total' => 30000, 'currency' => 'usd', 'client_reference_id' => '9999']);
        $this->webhook($ajeno, $this->firma($ajeno))->assertOk();
        $this->assertSame(Pago::PENDIENTE, $pago->fresh()->estado);
    }

    public function test_el_webhook_con_un_cobro_que_no_coincide_no_confirma(): void
    {
        $pago = $this->pendiente($this->contrato(), '300');
        $sesion = array_merge($this->stripe->sesiones['cs_test_1'], ['payment_status' => 'paid', 'amount_total' => 100]);
        $contenido = $this->evento($sesion);

        $this->webhook($contenido, $this->firma($contenido))->assertOk();

        $this->assertSame(Pago::PENDIENTE, $pago->fresh()->estado);
    }

    // ============================================ idempotencia

    public function test_recibir_el_webhook_y_refrescar_la_pagina_de_exito_no_duplica_el_comprobante(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato(1000);
        $pago = $this->pendiente($contrato);
        $this->stripe->pagar('cs_test_1');
        $contenido = $this->evento($this->stripe->sesiones['cs_test_1']);

        // webhook dos veces, y la página de éxito tres veces
        $this->webhook($contenido, $this->firma($contenido))->assertOk();
        $this->webhook($contenido, $this->firma($contenido))->assertOk();
        foreach ([1, 2, 3] as $vez) {
            $this->actingAs($asistente)->get(route('pagos.tarjeta.exito', $pago))->assertRedirect(route('pagos.comprobante', $pago));
        }

        $this->assertSame(1, Pago::count());
        $this->assertSame('CP-2026-000001', $pago->fresh()->comprobante);
        $this->assertSame(1, Bitacora::where('accion', 'PAGO_TARJETA_CONFIRMADO')->count());
        $this->assertSame(1, Bitacora::where('accion', 'COMPROBANTE_GENERADO')->count());
        $this->assertSame(300.0, $contrato->totalPagadoUsd(), 'el saldo se descuenta una sola vez');
    }

    public function test_la_pagina_de_exito_antes_que_el_webhook_tambien_es_segura(): void
    {
        $asistente = $this->asistente();
        $pago = $this->pendiente($this->contrato());
        $this->stripe->pagar('cs_test_1');
        $this->actingAs($asistente)->get(route('pagos.tarjeta.exito', $pago));
        $contenido = $this->evento($this->stripe->sesiones['cs_test_1']);

        $this->webhook($contenido, $this->firma($contenido))->assertOk();

        $this->assertSame(['CP-2026-000001'], Pago::pluck('comprobante')->all());
    }

    // ============================================ validaciones del cobro con tarjeta

    // ============================================ tarjeta en dólares o en bolivianos (Stripe siempre cobra en USD)

    public function test_la_tarjeta_en_bolivianos_cobra_en_dolares_con_el_tipo_de_cambio_manual_del_dia(): void
    {
        $contrato = $this->contrato(1000);

        $this->pagarConTarjeta($this->asistente(), $contrato, ['moneda' => 'BOB', 'tipocambio' => '6.96', 'monto' => '696.50'])
            ->assertRedirect('https://checkout.stripe.test/pay/cs_test_1');

        $pago = Pago::firstOrFail();
        $this->assertSame(Pago::PENDIENTE, $pago->estado);
        $this->assertSame(Pago::TARJETA, $pago->metodo);
        $this->assertSame('BOB', $pago->moneda);
        $this->assertSame('696.50', (string) $pago->monto, 'se guarda el monto original en bolivianos');
        $this->assertSame('6.9600', (string) $pago->tipocambio);
        $this->assertSame('100.07', (string) $pago->montousd);
        $this->assertSame('6.9600', (string) $pago->tipopago->cambiodemonedalocal);
        $this->assertSame('2026-12-15', $pago->tipopago->fecha->format('Y-m-d'), 'fecha del tipo de cambio = día del pago');
        $sesion = $this->stripe->ultimaSesion();
        $this->assertSame('usd', $sesion['currency'], 'Stripe nunca cobra en bolivianos');
        $this->assertSame(10007, $sesion['amount_total'], 'se cobran los centavos del equivalente en USD, no los de los bolivianos');
        $this->assertSame(0.0, $contrato->totalPagadoUsd(), 'pendiente: todavía no descuenta el saldo');
    }

    public function test_el_pago_con_tarjeta_en_bolivianos_se_confirma_al_volver_de_stripe_y_queda_pagado_con_su_montousd(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato(1000);
        $this->pagarConTarjeta($asistente, $contrato, ['moneda' => 'BOB', 'tipocambio' => '6.96', 'monto' => '696']);
        $pago = Pago::firstOrFail();
        $this->stripe->pagar('cs_test_1');

        $this->actingAs($asistente)->get(route('pagos.tarjeta.exito', $pago))->assertRedirect(route('pagos.comprobante', $pago));

        $pago = $pago->fresh();
        $this->assertSame(Pago::PAGADO, $pago->estado);
        $this->assertSame('696.00', (string) $pago->monto);
        $this->assertSame('BOB', $pago->moneda);
        $this->assertSame('100.00', (string) $pago->montousd);
        $this->assertSame('CP-2026-000001', $pago->comprobante);
        $this->assertSame(100.0, $contrato->totalPagadoUsd());
        $this->assertSame(900.0, $contrato->saldoUsd());
    }

    public function test_el_comprobante_de_un_pago_con_tarjeta_en_bolivianos_muestra_los_bolivianos_el_tipo_de_cambio_y_los_dolares(): void
    {
        $asistente = $this->asistente();
        $this->pagarConTarjeta($asistente, $this->contrato(1000), ['moneda' => 'BOB', 'tipocambio' => '6.96', 'monto' => '696.50']);
        $pago = Pago::firstOrFail();
        $this->stripe->pagar('cs_test_1');
        $this->actingAs($asistente)->get(route('pagos.tarjeta.exito', $pago));

        $this->get(route('pagos.comprobante', $pago))->assertOk()
            ->assertSee('BOB 696.50')->assertSee('6.96 Bs por USD')->assertSee('USD 100.07')->assertSee('Tarjeta');
    }

    public function test_el_webhook_deja_pagado_un_pago_en_bolivianos_solo_si_stripe_cobro_el_equivalente_en_dolares(): void
    {
        $contrato = $this->contrato(1000);
        $this->pagarConTarjeta($this->asistente(), $contrato, ['moneda' => 'BOB', 'tipocambio' => '6.96', 'monto' => '696']);
        $pago = Pago::firstOrFail();
        $this->stripe->pagar('cs_test_1');
        $sesion = $this->stripe->sesiones['cs_test_1'];

        // Cobrado en bolivianos: no coincide, aunque el número sea el de los dólares.
        $enBob = $this->evento(array_merge($sesion, ['currency' => 'bob']));
        $this->webhook($enBob, $this->firma($enBob))->assertOk();
        $this->assertSame(Pago::PENDIENTE, $pago->fresh()->estado);

        // Cobrado en dólares, pero por el número de los bolivianos (69 600 centavos): tampoco coincide.
        $montoDeBs = $this->evento(array_merge($sesion, ['amount_total' => 69600]));
        $this->webhook($montoDeBs, $this->firma($montoDeBs))->assertOk();
        $this->assertSame(Pago::PENDIENTE, $pago->fresh()->estado);
        $this->assertSame(0.0, $contrato->totalPagadoUsd());

        // Cobrado en dólares por el equivalente (10 000 centavos): el pago queda PAGADO con su montousd.
        $buena = $this->evento($sesion);
        $this->webhook($buena, $this->firma($buena))->assertOk();
        $pago = $pago->fresh();
        $this->assertSame(Pago::PAGADO, $pago->estado);
        $this->assertSame('100.00', (string) $pago->montousd);
        $this->assertSame('696.00', (string) $pago->monto);
        $this->assertSame('CP-2026-000001', $pago->comprobante);
        $this->assertSame(100.0, $contrato->totalPagadoUsd());
    }

    public function test_la_tarjeta_en_bolivianos_exige_el_tipo_de_cambio(): void
    {
        $this->pagarConTarjeta($this->asistente(), $this->contrato(), ['moneda' => 'BOB', 'tipocambio' => '', 'monto' => '696'])
            ->assertSessionHasErrors(['tipocambio' => 'Indica el tipo de cambio oficial del día para el pago en bolivianos.']);

        $this->assertSame(0, Pago::count());
        $this->assertSame([], $this->stripe->sesiones);
    }

    public function test_el_equivalente_en_dolares_se_redondea_a_dos_decimales_y_eso_es_lo_que_cobra_stripe(): void
    {
        $contrato = $this->contrato(1000);
        $asistente = $this->asistente();

        // [bolivianos, tipo de cambio, USD esperado, centavos que se le piden a Stripe]
        $casos = [
            ['100', '6.96', '14.37', 1437],  // 14,3678 sube
            ['50', '6.96', '7.18', 718],     // 7,1839 baja
            ['10.50', '6.9650', '1.51', 151], // con 4 decimales de tipo de cambio: 1,5075 sube
            ['3.48', '6.96', '0.50', 50],    // justo el mínimo de Stripe
        ];

        foreach ($casos as [$bolivianos, $cambio, $usd, $centavos]) {
            $this->pagarConTarjeta($asistente, $contrato, ['moneda' => 'BOB', 'tipocambio' => $cambio, 'monto' => $bolivianos])
                ->assertSessionHasNoErrors();

            $this->assertSame($usd, (string) Pago::orderByDesc('idpago')->firstOrFail()->montousd, "{$bolivianos} Bs a {$cambio}");
            $this->assertSame($centavos, $this->stripe->ultimaSesion()['amount_total'], "{$bolivianos} Bs a {$cambio}");
            $this->assertSame('usd', $this->stripe->ultimaSesion()['currency']);
        }
    }

    public function test_el_minimo_de_stripe_se_mide_por_el_equivalente_en_dolares(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato();

        // 3 Bs a 6,96 son USD 0,43: menos del mínimo de 0,50.
        $this->pagarConTarjeta($asistente, $contrato, ['moneda' => 'BOB', 'tipocambio' => '6.96', 'monto' => '3'])
            ->assertSessionHasErrors(['monto' => 'El pago con tarjeta debe ser de al menos USD 0.50 (equivale a USD 0.43).']);
        $this->assertSame(0, Pago::count());
        $this->assertSame([], $this->stripe->sesiones);

        // 3,47 Bs son USD 0,4985 -> 0,50: se acepta, porque el redondeo ya llega al mínimo.
        $this->pagarConTarjeta($asistente, $contrato, ['moneda' => 'BOB', 'tipocambio' => '6.96', 'monto' => '3.47'])
            ->assertSessionHasNoErrors();
        $this->assertSame('0.50', (string) Pago::firstOrFail()->montousd);
    }

    public function test_la_tarjeta_en_bolivianos_no_puede_superar_el_saldo_en_dolares(): void
    {
        $this->pagarConTarjeta($this->asistente(), $this->contrato(100), ['moneda' => 'BOB', 'tipocambio' => '6.96', 'monto' => '700'])
            ->assertSessionHasErrors(['monto' => 'El pago equivale a USD 100.57 y supera el saldo del contrato (USD 100.00).']);

        $this->assertSame(0, Pago::count());
        $this->assertSame([], $this->stripe->sesiones);
    }

    public function test_el_formulario_ofrece_la_tarjeta_en_cualquier_moneda_y_deja_los_dolares_preseleccionados(): void
    {
        $contrato = $this->contrato();

        $this->actingAs($this->asistente())->get(route('pagos.create', ['contrato' => $contrato->idcontrato]))
            ->assertOk()
            ->assertSee('<option value="USD" selected>', false)
            ->assertDontSee('<option value="BOB" selected>', false)
            ->assertSee('<option value="TARJETA"', false)
            ->assertSee('>Tarjeta</option>', false)
            ->assertDontSee('solo en dólares', false)
            ->assertDontSee('Solo en dólares')
            ->assertSee('Stripe cobra en dólares');
    }

    public function test_el_formulario_sugiere_el_ultimo_tipo_de_cambio_usado(): void
    {
        $asistente = $this->asistente();
        $this->pagarConTarjeta($asistente, $this->contrato(), ['moneda' => 'BOB', 'tipocambio' => '6.9650', 'monto' => '696']);
        $otro = $this->contrato();

        $this->actingAs($asistente)->get(route('pagos.create', ['contrato' => $otro->idcontrato]))
            ->assertSee('name="tipocambio"', false)->assertSee('value="6.965"', false)
            ->assertSee('<option value="USD" selected>', false);
    }

    public function test_el_monto_minimo_con_tarjeta_es_cincuenta_centavos(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato();

        $this->pagarConTarjeta($asistente, $contrato, ['monto' => '0.49'])
            ->assertSessionHasErrors(['monto' => 'El pago con tarjeta debe ser de al menos USD 0.50.']);
        $this->assertSame(0, Pago::count());

        $this->pagarConTarjeta($asistente, $contrato, ['monto' => '0.50'])->assertRedirect('https://checkout.stripe.test/pay/cs_test_1');
        $this->assertSame(50, $this->stripe->ultimaSesion()['amount_total']);
    }

    public function test_el_monto_con_tarjeta_no_puede_superar_el_saldo(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato(1000);
        $this->pagarConTarjeta($asistente, $contrato, ['monto' => '1000.01'])
            ->assertSessionHasErrors(['monto' => 'El pago equivale a USD 1,000.01 y supera el saldo del contrato (USD 1,000.00).']);

        $this->assertSame(0, Pago::count());
        $this->assertSame([], $this->stripe->sesiones);
    }

    public function test_no_se_paga_con_tarjeta_un_contrato_no_vigente(): void
    {
        $this->pagarConTarjeta($this->asistente(), $this->contrato(1000, Contrato::FINALIZADO))
            ->assertSessionHasErrors('idcontrato');

        $this->assertSame(0, Pago::count());
    }

    public function test_si_stripe_no_responde_el_pago_queda_anulado_y_se_avisa(): void
    {
        $this->stripe->fallar = true;
        $contrato = $this->contrato();

        $this->pagarConTarjeta($this->asistente(), $contrato)
            ->assertSessionHasErrors(['metodo' => 'No se pudo conectar con Stripe para iniciar el pago con tarjeta. Intenta de nuevo en unos minutos.']);

        $this->assertSame(Pago::ANULADO, Pago::firstOrFail()->estado);
        $this->assertSame(1000.0, $contrato->saldoUsd());
        $this->assertSame(1, Bitacora::where('accion', 'PAGO_TARJETA_FALLIDO')->count());
    }

    // ============================================ la pantalla y los accesos

    public function test_el_formulario_ofrece_la_tarjeta_y_explica_como_se_cobra(): void
    {
        $contrato = $this->contrato();

        $this->actingAs($this->asistente())->get(route('pagos.create', ['contrato' => $contrato->idcontrato]))
            ->assertOk()
            ->assertSee('<option value="TARJETA"', false)
            ->assertDontSee('<option value="TARJETA" disabled>', false)
            ->assertSee('Se abre la página segura de Stripe');
    }

    public function test_un_pago_pendiente_con_tarjeta_aparece_en_los_pagos_del_contrato_sin_comprobante(): void
    {
        $asistente = $this->asistente();
        $contrato = $this->contrato();
        $this->pendiente($contrato);
        $this->flushSession();

        $this->actingAs($asistente)->get(route('pagos.create', ['contrato' => $contrato->idcontrato]))
            ->assertSee('Pendiente')->assertDontSee('CP-2026');
    }

    public function test_el_agente_el_cliente_y_el_invitado_no_usan_las_paginas_de_retorno(): void
    {
        $pago = $this->pendiente($this->contrato());

        auth()->logout();
        $this->flushSession();
        $this->get(route('pagos.tarjeta.exito', $pago))->assertRedirect(route('login'));
        $this->get(route('pagos.tarjeta.cancelado', $pago))->assertRedirect(route('login'));

        foreach ([$this->agente(), $this->cliente()] as $usuario) {
            $this->actingAs($usuario)->get(route('pagos.tarjeta.exito', $pago))->assertForbidden();
            $this->actingAs($usuario)->get(route('pagos.tarjeta.cancelado', $pago))->assertForbidden();
            $this->pagarConTarjeta($usuario, $this->contrato())->assertForbidden();
        }
        $this->assertSame(Pago::PENDIENTE, $pago->fresh()->estado);
    }

    public function test_las_paginas_de_retorno_solo_sirven_para_pagos_con_tarjeta(): void
    {
        $contrato = $this->contrato();
        $efectivo = Pago::create(['idcontrato' => $contrato->idcontrato, 'monto' => 10, 'montousd' => 10, 'moneda' => 'USD', 'fechapago' => '2026-12-15 10:00:00', 'estado' => Pago::PAGADO, 'metodo' => Pago::EFECTIVO]);

        $this->actingAs($this->asistente())->get(route('pagos.tarjeta.exito', $efectivo))->assertNotFound();
        $this->actingAs($this->asistente())->get(route('pagos.tarjeta.cancelado', $efectivo))->assertNotFound();
        $this->assertSame(Pago::PAGADO, $efectivo->fresh()->estado);
    }

    public function test_las_llaves_de_stripe_vienen_de_la_configuracion_y_no_estan_en_el_codigo(): void
    {
        $ejemplo = file_get_contents(base_path('.env.example'));
        $this->assertMatchesRegularExpression('/^STRIPE_SECRET=$/m', $ejemplo, 'el .env.example no debe traer ninguna llave');
        $this->assertMatchesRegularExpression('/^STRIPE_WEBHOOK_SECRET=$/m', $ejemplo);
        $this->assertArrayHasKey('secret', config('services.stripe'));
        $this->assertArrayHasKey('webhook_secret', config('services.stripe'));
    }
}
