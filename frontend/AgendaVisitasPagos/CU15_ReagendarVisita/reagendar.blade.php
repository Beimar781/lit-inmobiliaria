@extends('layouts.app')

@section('titulo', 'Reagendar visita - LIT Inmobiliaria')

@section('contenido')
<a href="{{ route('visitas.show', $visita) }}" class="text-decoration-none">&larr; Volver al detalle de la visita</a>

<h1 class="h3 my-3">Reagendar visita</h1>

<div class="row g-4">
    <div class="col-lg-4 order-lg-2">
        {{-- Paso 4: fecha y hora actuales --}}
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-muted">Visita actual</h2>
                <p class="h5 mb-1">{{ $visita->fecha->format('d/m/Y') }} &middot; {{ $visita->hora_corta }}</p>
                <span class="badge text-bg-{{ $visita->estado === 'REAGENDADA' ? 'warning' : 'primary' }} mb-3">{{ $visita->estado_etiqueta }}</span>
                <dl class="mb-0 small">
                    <dt>Propiedad</dt>
                    <dd>{{ $visita->propiedad->codigo }} &middot; {{ $visita->propiedad->titulo }}</dd>
                    <dt>Cliente</dt>
                    <dd>{{ $visita->cliente->nombre }}</dd>
                    <dt>Agente</dt>
                    <dd class="mb-0">{{ $visita->propiedad->agente?->nombre ?? 'Sin asignar' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-8 order-lg-1">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('visitas.reagendar.guardar', $visita) }}" novalidate>
                    @csrf
                    @method('PUT')

                    <h2 class="h6 text-uppercase text-muted">Nueva fecha y hora</h2>
                    <div class="row">
                        <div class="col-sm-6">
                            <x-campo name="fecha" label="Nueva fecha" type="date" :min="$minimo" />
                        </div>
                        <div class="col-sm-6">
                            <div class="mb-3">
                                <label for="hora" class="form-label">Nueva hora</label>
                                <select id="hora" name="hora" class="form-select @error('hora') is-invalid @enderror">
                                    <option value="">— Elige la hora —</option>
                                    @foreach ($horas as $hora)
                                        <option value="{{ $hora }}" @selected(old('hora') === $hora)>{{ $hora }}</option>
                                    @endforeach
                                </select>
                                @error('hora')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text" id="ayuda-horas"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="motivo" class="form-label">Motivo del cambio</label>
                        <textarea id="motivo" name="motivo" rows="3" maxlength="500"
                                  class="form-control @error('motivo') is-invalid @enderror">{{ old('motivo') }}</textarea>
                        @error('motivo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Reagendar visita</button>
                        <a href="{{ route('visitas.show', $visita) }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        // Horas ocupadas del agente por fecha (sin contar esta misma visita). Solo ayuda: el servidor vuelve a comprobarlo.
        var ocupadas = @json($ocupadas);
        var fecha = document.getElementById('fecha');
        var hora = document.getElementById('hora');
        var ayuda = document.getElementById('ayuda-horas');

        function marcarOcupadas() {
            var dia = ocupadas[fecha.value] || [];
            Array.prototype.forEach.call(hora.options, function (opcion) {
                if (!opcion.value) { return; }
                var ocupada = dia.indexOf(opcion.value) !== -1;
                opcion.disabled = ocupada;
                opcion.textContent = opcion.value + (ocupada ? ' (ocupado)' : '');
            });
            if (hora.selectedOptions.length && hora.selectedOptions[0].disabled) { hora.value = ''; }
            ayuda.textContent = dia.length ? 'Las horas marcadas como ocupadas no se pueden elegir.' : '';
        }

        fecha.addEventListener('change', marcarOcupadas);
        marcarOcupadas();
    })();
</script>
@endpush
@endsection
