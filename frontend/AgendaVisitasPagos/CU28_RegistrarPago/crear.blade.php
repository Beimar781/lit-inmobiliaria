@extends('layouts.app')

@section('titulo', 'Registrar pago - LIT Inmobiliaria')

@php($formato = fn ($valor) => number_format((float) $valor, 2, '.', ','))
@php($coloresPago = ['PAGADO' => 'success', 'PENDIENTE' => 'warning', 'ANULADO' => 'secondary'])

@section('contenido')
<h1 class="h3 mb-3">Registrar pago</h1>

{{-- Paso 2: elegir el contrato vigente --}}
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('pagos.create') }}" class="row g-2 align-items-end">
            <div class="col-md-9">
                <label for="contrato" class="form-label mb-1">Contrato vigente</label>
                <select id="contrato" name="contrato" class="form-select">
                    <option value="">— Elige el contrato —</option>
                    @foreach ($contratos as $id => $texto)
                        <option value="{{ $id }}" @selected((string) request('contrato', old('idcontrato')) === (string) $id)>{{ $texto }}</option>
                    @endforeach
                </select>
                @if (empty($contratos))
                    <div class="form-text">No hay contratos vigentes con saldo por pagar.</div>
                @endif
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary w-100">Ver contrato</button>
            </div>
        </form>
    </div>
</div>

@if (is_string(request()->query('contrato')) && request()->query('contrato') !== '' && ! $contrato)
    <div class="alert alert-danger">El contrato elegido no existe.</div>
@endif

@if ($contrato)
    @if ($contrato->estado !== 'VIGENTE')
        <div class="alert alert-warning">Este contrato está {{ strtolower($contrato->estado_etiqueta) }}: solo se pueden registrar pagos en un contrato vigente.</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-5">
            {{-- Paso 3: datos del contrato y pagos ya registrados --}}
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5">Contrato N.º {{ $contrato->idcontrato }} &middot; {{ $contrato->tipocontrato }}</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-5">Cliente</dt>
                        <dd class="col-7">{{ $contrato->cliente->nombre }}</dd>
                        <dt class="col-5">Propiedad</dt>
                        <dd class="col-7">{{ $contrato->propiedad->codigo }} &middot; {{ $contrato->propiedad->titulo }}</dd>
                        <dt class="col-5">Monto del contrato</dt>
                        <dd class="col-7">USD {{ $formato($contrato->monto) }}</dd>
                        <dt class="col-5">Pagado</dt>
                        <dd class="col-7">USD {{ $formato($pagado) }}</dd>
                        <dt class="col-5">Saldo</dt>
                        <dd class="col-7 fw-bold">USD {{ $formato($saldo) }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h2 class="h6 mb-0">Pagos ya registrados</h2>
                        @if (Route::has('pagos.index'))
                            <a href="{{ route('pagos.index', ['contrato' => $contrato->idcontrato]) }}" class="small">Ver historial</a>
                        @endif
                    </div>
                    @forelse ($contrato->pagos->sortByDesc('idpago') as $p)
                        <div class="d-flex justify-content-between border-bottom py-2 small">
                            <span>
                                {{ $p->fechapago->format('d/m/Y') }} &middot; {{ $p->moneda }} {{ $formato($p->monto) }}
                                @if ($p->comprobante) <span class="text-muted">&middot; {{ $p->comprobante }}</span> @endif
                            </span>
                            <span class="badge text-bg-{{ $coloresPago[$p->estado] ?? 'secondary' }}">{{ $p->estado_etiqueta }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Todavía no hay pagos registrados.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            @if ($contrato->estado === 'VIGENTE' && $saldo > 0)
                <div class="card shadow-sm">
                    <div class="card-body">
                        <form method="POST" action="{{ route('pagos.store') }}" novalidate>
                            @csrf
                            <input type="hidden" name="idcontrato" value="{{ $contrato->idcontrato }}">
                            @error('idcontrato')
                                <div class="alert alert-danger">{{ $message }}</div>
                            @enderror

                            <div class="row">
                                <div class="col-sm-5">
                                    <x-campo name="monto" label="Monto que se paga" type="number" step="0.01" min="0.01" />
                                </div>
                                <div class="col-sm-3">
                                    <x-seleccion name="moneda" label="Moneda" :opciones="['USD' => 'Dólares (USD)', 'BOB' => 'Bolivianos (BOB)']" valor="USD" />
                                </div>
                                <div class="col-sm-4" id="bloque-tipocambio">
                                    <x-campo name="tipocambio" label="Tipo de cambio del día" type="number" step="0.0001" min="0.0001" :valor="$tipoCambioSugerido"
                                             ayuda="Dólar oficial del día del pago. Confírmalo antes de guardar." />
                                </div>
                            </div>
                            <p class="small text-muted" id="equivalente"></p>

                            <div class="row">
                                <div class="col-sm-6">
                                    <x-campo name="fechapago" label="Fecha del pago" type="date" :valor="$hoy" :max="$hoy" />
                                </div>
                                <div class="col-sm-6">
                                    <div class="mb-3">
                                        <label for="metodo" class="form-label">Método de pago</label>
                                        <select id="metodo" name="metodo" class="form-select @error('metodo') is-invalid @enderror">
                                            <option value="EFECTIVO" @selected(old('metodo', 'EFECTIVO') === 'EFECTIVO')>Efectivo</option>
                                            <option value="TARJETA" @selected(old('metodo') === 'TARJETA')>Tarjeta</option>
                                        </select>
                                        @error('metodo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text" id="ayuda-tarjeta" style="display: none">Se abre la página segura de Stripe para escribir la tarjeta. Stripe cobra en dólares: un pago en bolivianos se cobra por su equivalente con el tipo de cambio de arriba. Mínimo USD 0.50.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-primary" id="boton-registrar">Registrar pago</button>
                                <a href="{{ route('pagos.create') }}" class="btn btn-outline-secondary">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            @elseif ($contrato->estado === 'VIGENTE')
                <div class="alert alert-success">Este contrato ya está pagado por completo.</div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        (function () {
            var moneda = document.getElementById('moneda');
            var monto = document.getElementById('monto');
            var cambio = document.getElementById('tipocambio');
            var bloque = document.getElementById('bloque-tipocambio');
            var equivalente = document.getElementById('equivalente');
            if (!moneda) { return; }

            // El tipo de cambio solo se pide en bolivianos; el equivalente en dólares es una ayuda (el servidor lo calcula al guardar).
            var metodo = document.getElementById('metodo');
            var ayudaTarjeta = document.getElementById('ayuda-tarjeta');

            function actualizar() {
                ayudaTarjeta.style.display = metodo.value === 'TARJETA' ? '' : 'none';
                var bob = moneda.value === 'BOB';
                bloque.style.display = bob ? '' : 'none';
                var m = parseFloat(monto.value), t = parseFloat(cambio.value);
                equivalente.textContent = bob && m > 0 && t > 0 ? 'Equivale a USD ' + (Math.round(m / t * 100) / 100).toFixed(2) : '';
            }
            [moneda, monto, cambio, metodo].forEach(function (campo) { campo.addEventListener('input', actualizar); campo.addEventListener('change', actualizar); });
            actualizar();
        })();
    </script>
    @endpush
@endif
@endsection
