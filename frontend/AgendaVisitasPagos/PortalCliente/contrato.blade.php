@extends('layouts.app')

@section('titulo', 'Contrato ' . $contrato->idcontrato . ' - Mis contratos y pagos')

@php($colores = ['VIGENTE' => 'success', 'FINALIZADO' => 'secondary', 'RESCINDIDO' => 'danger'])
@php($coloresPago = ['PAGADO' => 'success', 'PENDIENTE' => 'warning', 'ANULADO' => 'secondary'])
@php($formato = fn ($valor) => number_format((float) $valor, 2, '.', ','))

@section('contenido')
<a href="{{ route('portal.contratos') }}" class="text-decoration-none">&larr; Mis contratos y pagos</a>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 my-3">
    <div>
        <h1 class="h3 mb-1">Contrato N.º {{ $contrato->idcontrato }} &middot; {{ $contrato->tipocontrato }}</h1>
        <p class="text-muted mb-0">{{ $contrato->propiedad->codigo }} &middot; {{ $contrato->propiedad->titulo }}</p>
    </div>
    <span class="badge text-bg-{{ $colores[$contrato->estado] ?? 'secondary' }} fs-6">{{ $contrato->estado_etiqueta }}</span>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Monto del contrato</dt>
                    <dd class="col-sm-8">USD {{ $formato($contrato->monto) }}</dd>
                    <dt class="col-sm-4">Pagado</dt>
                    <dd class="col-sm-8">USD {{ $formato($pagado) }}</dd>
                    <dt class="col-sm-4">Saldo</dt>
                    <dd class="col-sm-8 fw-bold">USD {{ $formato($saldo) }}</dd>
                    <dt class="col-sm-4">Inicio</dt>
                    <dd class="col-sm-8">{{ $contrato->fechainicio->format('d/m/Y') }}</dd>
                    <dt class="col-sm-4">Fin</dt>
                    <dd class="col-sm-8 mb-0">{{ $contrato->fechafin?->format('d/m/Y') ?? '—' }}</dd>
                </dl>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Mis pagos</h2>
                @forelse ($contrato->pagos->sortByDesc('idpago') as $pago)
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom py-2">
                        <span>
                            {{ $pago->fechapago->format('d/m/Y') }} &middot; {{ $pago->moneda }} {{ $formato($pago->monto) }}
                            <span class="text-muted small">&middot; {{ $pago->metodo_etiqueta }}</span>
                        </span>
                        <span class="d-flex align-items-center gap-2">
                            <span class="badge text-bg-{{ $coloresPago[$pago->estado] ?? 'secondary' }}">{{ $pago->estado_etiqueta }}</span>
                            @if ($pago->estado === 'PAGADO')
                                <a href="{{ route('portal.comprobante', $pago) }}" class="btn btn-sm btn-outline-primary">Comprobante</a>
                            @endif
                        </span>
                    </div>
                @empty
                    <p class="text-muted mb-0">Todavía no tienes pagos registrados.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Pagar con tarjeta</h2>
                @if ($contrato->estado === 'VIGENTE' && $saldo > 0)
                    <p class="small text-muted">Se abre la página segura de Stripe para escribir tu tarjeta. Stripe cobra en dólares: si pagas en bolivianos, se cobra su equivalente con el tipo de cambio que escribas.</p>
                    <form method="POST" action="{{ route('portal.pagar', $contrato) }}" novalidate>
                        @csrf
                        <x-seleccion name="moneda" label="Moneda" :opciones="['USD' => 'Dólares (USD)', 'BOB' => 'Bolivianos (BOB)']" valor="USD" />
                        <x-campo name="monto" label="Monto a pagar" type="number" step="0.01" min="0.01" :valor="$saldo"
                                 ayuda="Mínimo USD {{ number_format($minimo, 2) }}. No puede superar tu saldo (USD {{ $formato($saldo) }})." />
                        <div id="bloque-tipocambio">
                            <x-campo name="tipocambio" label="Tipo de cambio del día" type="number" step="0.0001" min="0.0001" :valor="$tipoCambioSugerido"
                                     ayuda="Dólar oficial de hoy (Bs por USD). Confírmalo antes de pagar." />
                        </div>
                        <p class="small text-muted" id="equivalente"></p>
                        <button type="submit" class="btn btn-primary w-100">Pagar con tarjeta</button>
                    </form>
                    @push('scripts')
                    <script>
                        (function () {
                            var moneda = document.getElementById('moneda'), monto = document.getElementById('monto'), cambio = document.getElementById('tipocambio');
                            var bloque = document.getElementById('bloque-tipocambio'), equivalente = document.getElementById('equivalente');
                            // El tipo de cambio solo se pide en bolivianos; el equivalente en dólares es una ayuda (el servidor lo calcula al pagar).
                            function actualizar() {
                                var bob = moneda.value === 'BOB';
                                bloque.style.display = bob ? '' : 'none';
                                var m = parseFloat(monto.value), t = parseFloat(cambio.value);
                                equivalente.textContent = bob && m > 0 && t > 0 ? 'Se cobrará USD ' + (Math.round(m / t * 100) / 100).toFixed(2) : '';
                            }
                            [moneda, monto, cambio].forEach(function (c) { c.addEventListener('input', actualizar); c.addEventListener('change', actualizar); });
                            actualizar();
                        })();
                    </script>
                    @endpush
                @elseif ($contrato->estado === 'VIGENTE')
                    <p class="text-success mb-0">Este contrato está pagado por completo.</p>
                @else
                    <p class="text-muted mb-0">Este contrato está {{ strtolower($contrato->estado_etiqueta) }}: ya no se pueden hacer pagos.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
