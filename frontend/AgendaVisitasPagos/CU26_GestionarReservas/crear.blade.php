@extends('layouts.app')

@section('titulo', 'Nueva reserva - LIT Inmobiliaria')

@section('contenido')
<a href="{{ route('reservas.index') }}" class="text-decoration-none">&larr; Volver a reservas</a>
<h1 class="h3 my-3">Nueva reserva</h1>

<div class="row">
    <div class="col-lg-7">
        <div class="alert alert-info small">
            La reserva se hace <strong>después de una visita realizada</strong> y se paga <strong>en efectivo</strong>.
            Mínimo: {{ rtrim(rtrim(number_format($minimoPorcentaje, 2), '0'), '.') }} % del precio de la propiedad.
            Si el cliente no formaliza la compra, se le devuelve lo pagado menos el {{ rtrim(rtrim(number_format($retencion, 2), '0'), '.') }} %.
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('reservas.store') }}" novalidate>
                    @csrf

                    <x-seleccion name="idvisita" label="Visita realizada (de ella salen la propiedad y el cliente)" :opciones="$visitas" :valor="$visitaElegida"
                                 vacio="— Elige la visita —" />
                    @if (empty($visitas))
                        <div class="form-text mb-3 mt-n2">
                            No hay visitas realizadas de propiedades disponibles. Marca la visita como realizada en el
                            @if (Route::has('visitas.calendario')) <a href="{{ route('visitas.calendario') }}">calendario de visitas</a>@else calendario de visitas @endif.
                        </div>
                    @endif

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
    })();
</script>
@endpush
@endsection
