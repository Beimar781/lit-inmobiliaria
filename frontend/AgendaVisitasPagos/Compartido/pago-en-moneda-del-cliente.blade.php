{{-- Pago con tarjeta que el cliente pagó en bolivianos en la página de Stripe (Adaptive Pricing). El pago se registra en dólares;
     aquí va lo que el cliente realmente pagó y el tipo de cambio que aplicó Stripe (no el tipo de cambio manual de la empresa).
     Necesita $pago y $formato. --}}
@if ($pago->pagoEnMonedaDelCliente())
    <dt class="col-sm-4">Pagado por el cliente en {{ $pago->moneda_cliente === 'BOB' ? 'bolivianos' : $pago->moneda_cliente }}</dt>
    <dd class="col-sm-8">{{ $pago->moneda_cliente === 'BOB' ? 'Bs' : $pago->moneda_cliente }} {{ $formato($pago->monto_cliente) }}</dd>
    <dt class="col-sm-4">Tipo de cambio aplicado por Stripe</dt>
    <dd class="col-sm-8">
        {{ $pago->tipoCambioStripeTexto() }} {{ $pago->moneda_cliente === 'BOB' ? 'Bs' : $pago->moneda_cliente }} por USD
        <div class="text-muted small">Es el tipo de cambio de Stripe, no el tipo de cambio manual de la empresa.</div>
    </dd>
    <dt class="col-sm-4">Equivalente en dólares</dt>
    <dd class="col-sm-8">USD {{ $formato($pago->montousd) }} <span class="text-muted small">(es lo que se descuenta del contrato)</span></dd>
@endif
