@extends('layouts.app')

@section('titulo', 'Nuevo contrato - LIT Inmobiliaria')

@section('contenido')
<a href="{{ route('contratos.index') }}" class="text-decoration-none">&larr; Volver a contratos</a>
<h1 class="h3 my-3">Nuevo contrato <span class="cu-etiqueta">(CU27)</span></h1>

<div class="row">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('contratos.store') }}" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="idpropiedad" class="form-label">Propiedad</label>
                        <select id="idpropiedad" name="idpropiedad" class="form-select @error('idpropiedad') is-invalid @enderror">
                            <option value="">— Elige la propiedad —</option>
                            @foreach ($propiedades as $p)
                                <option value="{{ $p->idpropiedad }}"
                                        data-tipo="{{ $p->tipopropiedad }}"
                                        data-precio="{{ $p->precio }}"
                                        data-cliente="{{ $reservadas[$p->idpropiedad] ?? '' }}"
                                        @selected((string) old('idpropiedad', $propiedadElegida) === (string) $p->idpropiedad)>
                                    {{ $p->codigo }} &middot; {{ $p->titulo }} ({{ ucfirst(strtolower($p->estadopropiedad)) }})
                                </option>
                            @endforeach
                        </select>
                        @error('idpropiedad')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Disponibles, o reservadas por el mismo cliente del contrato.</div>
                    </div>

                    <x-seleccion name="idcliente" label="Cliente del CRM" :opciones="$clientes" :valor="$clienteElegido"
                                 vacio="— Elige el cliente —" />

                    <p class="mb-3" id="bloque-tipo" style="display: none">
                        Tipo de contrato: <strong id="tipo-contrato"></strong>
                        <span class="text-muted small">(sale del tipo de operación de la propiedad)</span>
                    </p>

                    <x-campo name="monto" label="Monto del contrato (USD)" type="number" step="0.01" min="0.01"
                             ayuda="Siempre en dólares. Los pagos se descuentan de este monto." />

                    <div class="row">
                        <div class="col-sm-6">
                            <x-campo name="fechainicio" label="Fecha de inicio" type="date" />
                        </div>
                        <div class="col-sm-6">
                            <x-campo name="fechafin" label="Fecha de fin (opcional)" type="date" />
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Guardar contrato</button>
                        <a href="{{ route('contratos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        // Ayudas en pantalla: muestran el tipo de contrato y sugieren el monto y el cliente. El servidor valida todo al guardar.
        var propiedad = document.getElementById('idpropiedad');
        var cliente = document.getElementById('idcliente');
        var monto = document.getElementById('monto');
        var bloque = document.getElementById('bloque-tipo');
        var tipo = document.getElementById('tipo-contrato');

        function actualizar(cambio) {
            var opcion = propiedad.selectedOptions[0];
            if (!opcion || !opcion.value) { bloque.style.display = 'none'; return; }
            tipo.textContent = opcion.dataset.tipo || 'sin definir';
            bloque.style.display = '';
            if (cambio) {
                if (!monto.value && opcion.dataset.precio) { monto.value = opcion.dataset.precio; }
                if (opcion.dataset.cliente) { cliente.value = opcion.dataset.cliente; }
            }
        }

        propiedad.addEventListener('change', function () { actualizar(true); });
        actualizar(false);
    })();
</script>
@endpush
@endsection
