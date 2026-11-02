@extends('layouts.app')

@section('titulo', 'Nueva reserva - LIT Inmobiliaria')

@section('contenido')
<a href="{{ route('reservas.index') }}" class="text-decoration-none">&larr; Volver a reservas</a>
<h1 class="h3 my-3">Nueva reserva <span class="cu-etiqueta">(CU26)</span></h1>

<div class="row">
    <div class="col-lg-7">
        <div class="alert alert-info small">
            La reserva se paga <strong>en efectivo</strong>, con o sin una visita previa.
            Mínimo: {{ rtrim(rtrim(number_format($minimoPorcentaje, 2), '0'), '.') }} % del precio de la propiedad.
            Si el cliente no formaliza la compra, se le devuelve lo pagado menos el {{ rtrim(rtrim(number_format($retencion, 2), '0'), '.') }} %.
            Si la formaliza, ese dinero es el primer pago del contrato.
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('reservas.store') }}" novalidate>
                    @csrf

                    @if ($visitas->isNotEmpty())
                        {{-- Opcional: una visita realizada llena la propiedad y el cliente --}}
                        <div class="mb-3">
                            <label for="idvisita" class="form-label">Visita realizada <span class="text-muted">(opcional)</span></label>
                            <select id="idvisita" name="idvisita" class="form-select @error('idvisita') is-invalid @enderror">
                                <option value="">— Sin visita previa —</option>
                                @foreach ($visitas as $v)
                                    <option value="{{ $v->idvisita }}" data-propiedad="{{ $v->idpropiedad }}" data-cliente="{{ $v->idcliente }}"
                                            @selected((string) old('idvisita', $visitaElegida) === (string) $v->idvisita)>
                                        {{ $v->cliente->nombre }} &middot; {{ $v->propiedad->codigo }} {{ $v->propiedad->titulo }} &middot; visita del {{ $v->fecha->format('d/m/Y') }}
                                    </option>
                                @endforeach
                            </select>
                            @error('idvisita')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Al elegir una visita se llenan la propiedad y el cliente.</div>
                        </div>
                    @endif

                    <x-seleccion name="idpropiedad" label="Propiedad disponible" :opciones="$propiedades" :valor="$propiedadElegida"
                                 vacio="— Elige la propiedad —" />

                    <x-seleccion name="idcliente" label="Cliente del CRM" :opciones="$clientes" :valor="$clienteElegido"
                                 vacio="— Elige el cliente —" />
                    <div class="form-text mb-3 mt-n2">
                        ¿El cliente no está en la lista? Regístralo primero en <a href="{{ route('prospectos.index') }}">Prospectos CRM</a>.
                    </div>

                    <h2 class="h6 text-uppercase text-muted mt-2">Pago en efectivo</h2>
                    <div class="row">
                        <div class="col-sm-5">
                            <x-campo name="monto" label="Monto que paga" type="number" step="0.01" min="0.01" />
                        </div>
                        <div class="col-sm-3">
                            <x-seleccion name="moneda" label="Moneda" :opciones="['USD' => 'Dólares (USD)', 'BOB' => 'Bolivianos (BOB)']" valor="USD" />
                        </div>
                        <div class="col-sm-4" id="bloque-tipocambio">
                            <x-campo name="tipocambio" label="Tipo de cambio del día" type="number" step="0.0001" min="0.0001" :valor="$tipoCambioSugerido"
                                     ayuda="Dólar oficial de hoy. Confírmalo antes de guardar." />
                        </div>
                    </div>

                    <x-campo name="fechaplazo" label="Plazo de la reserva (hasta qué día)" type="date" :min="$minimo"
                             ayuda="La propiedad queda reservada hasta el final de ese día. Después vuelve a estar disponible." />

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Cobrar y guardar reserva</button>
                        <a href="{{ route('reservas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        // El tipo de cambio solo se pide cuando el pago es en bolivianos.
        var moneda = document.getElementById('moneda');
        var bloque = document.getElementById('bloque-tipocambio');
        function alternar() { bloque.style.display = moneda.value === 'BOB' ? '' : 'none'; }
        moneda.addEventListener('change', alternar);
        alternar();

        // Al elegir una visita realizada se llenan la propiedad y el cliente (si están en las listas).
        var visita = document.getElementById('idvisita');
        if (visita) {
            visita.addEventListener('change', function () {
                var opcion = visita.selectedOptions[0];
                if (!opcion || !opcion.value) { return; }
                document.getElementById('idpropiedad').value = opcion.dataset.propiedad;
                document.getElementById('idcliente').value = opcion.dataset.cliente;
            });
        }
    })();
</script>
@endpush
@endsection
