<?php

namespace Tests\Feature;

use App\Services\StripeService;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

/**
 * El StripeService REAL, sin internet: se le pone a la librería de Stripe un cliente HTTP falso que anota lo que se enviaría
 * y responde como lo haría Stripe. Así se comprueba la petición (que las demás pruebas no ven porque usan otro doble).
 */
class StripeServiceTest extends TestCase
{
    /** @var array<int, array{metodo: string, url: string, params: array, cabeceras: array}> */
    private array $peticiones = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.secret' => 'sk_test_no_es_una_llave_real', 'services.stripe.webhook_secret' => 'whsec_prueba']);

        $this->peticiones = [];
        $pruebas = $this;
        ApiRequestor::setHttpClient(new class($pruebas) implements ClientInterface {
            public function __construct(private StripeServiceTest $pruebas)
            {
            }

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                $this->pruebas->anotar($method, $absUrl, $params, $headers);

                $cuerpo = str_contains($absUrl, '/v1/checkout/sessions/cs_test_abc')
                    ? ['id' => 'cs_test_abc', 'object' => 'checkout.session', 'payment_status' => 'paid', 'payment_intent' => 'pi_123', 'amount_total' => 30000, 'currency' => 'usd', 'client_reference_id' => '7', 'metadata' => ['idpago' => '7']]
                    : ['id' => 'cs_test_nueva', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_nueva'];

                return [json_encode($cuerpo), 200, []];
            }
        });
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    public function anotar(string $metodo, string $url, array $params, array $cabeceras): void
    {
        $this->peticiones[] = ['metodo' => $metodo, 'url' => $url, 'params' => $params, 'cabeceras' => $cabeceras];
    }

    public function test_la_sesion_de_checkout_se_pide_en_dolares_con_el_monto_en_centavos(): void
    {
        $sesion = (new StripeService())->crearSesionDePago(
            30050, 'Pago contrato 3 - Casa de Equipetrol', '7', ['idpago' => '7', 'idcontrato' => '3'],
            'https://lit.test/pagos/7/tarjeta/exito?session_id={CHECKOUT_SESSION_ID}', 'https://lit.test/pagos/7/tarjeta/cancelado'
        );

        $this->assertSame(['id' => 'cs_test_nueva', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_nueva'], $sesion);

        $peticion = $this->peticiones[0];
        $this->assertSame('post', $peticion['metodo']);
        $this->assertStringEndsWith('/v1/checkout/sessions', $peticion['url']);
        $this->assertContains('Authorization: Bearer sk_test_no_es_una_llave_real', $peticion['cabeceras']);

        $p = $peticion['params'];
        $this->assertSame('payment', $p['mode']);
        $this->assertSame('usd', $p['line_items'][0]['price_data']['currency']);
        $this->assertSame(30050, $p['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame(1, $p['line_items'][0]['quantity']);
        $this->assertSame('Pago contrato 3 - Casa de Equipetrol', $p['line_items'][0]['price_data']['product_data']['name']);
        $this->assertSame('7', $p['client_reference_id']);
        $this->assertSame(['idpago' => '7', 'idcontrato' => '3'], $p['metadata']);
        $this->assertStringContainsString('{CHECKOUT_SESSION_ID}', $p['success_url']);
        $this->assertSame('https://lit.test/pagos/7/tarjeta/cancelado', $p['cancel_url']);
    }

    public function test_la_sesion_se_puede_pedir_en_bolivianos(): void
    {
        (new StripeService())->crearSesionDePago(69650, 'Pago contrato 3 - Casa', '7', ['idpago' => '7'], 'https://lit.test/ok', 'https://lit.test/no', 'BOB');

        $linea = $this->peticiones[0]['params']['line_items'][0];
        $this->assertSame('bob', $linea['price_data']['currency'], 'la moneda va en minúsculas, como pide Stripe');
        $this->assertSame(69650, $linea['price_data']['unit_amount']);
    }

    public function test_la_sesion_se_consulta_a_stripe_y_se_leen_los_datos_del_cobro(): void
    {
        $sesion = (new StripeService())->obtenerSesion('cs_test_abc');

        $this->assertSame('get', $this->peticiones[0]['metodo']);
        $this->assertStringEndsWith('/v1/checkout/sessions/cs_test_abc', $this->peticiones[0]['url']);
        $this->assertSame('cs_test_abc', $sesion['id']);
        $this->assertSame('paid', $sesion['payment_status']);
        $this->assertSame('pi_123', $sesion['payment_intent']);
        $this->assertSame(30000, $sesion['amount_total']);
        $this->assertSame('usd', $sesion['currency']);
        $this->assertSame('7', $sesion['client_reference_id']);
        $this->assertSame(['idpago' => '7'], $sesion['metadata']);
    }

    public function test_sin_la_llave_de_stripe_no_se_intenta_ninguna_llamada(): void
    {
        config(['services.stripe.secret' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('STRIPE_SECRET');
        try {
            (new StripeService())->obtenerSesion('cs_test_abc');
        } finally {
            $this->assertSame([], $this->peticiones, 'sin llave no debe salir ninguna petición');
        }
    }

    public function test_el_webhook_exige_el_secreto_de_firma_configurado(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $this->expectException(\UnexpectedValueException::class);
        (new StripeService())->construirEvento('{}', 't=1,v1=abc');
    }
}
