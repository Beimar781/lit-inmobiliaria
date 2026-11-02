@extends('layouts.app')

@section('titulo', 'Agendar visita - ' . $propiedad->titulo)

@section('contenido')
<a href="{{ route('catalogo.detalle', $propiedad) }}" class="text-decoration-none">&larr; Volver a la ficha de la propiedad</a>

<h1 class="h3 my-3">Agendar visita <span class="cu-etiqueta">(CU14)</span></h1>

<div class="row g-4">
    <div class="col-lg-4 order-lg-2">
        {{-- Paso 2: datos de la propiedad --}}
        <div class="card shadow-sm">
            <div class="card-body">
                <p class="text-muted small mb-1">{{ $propiedad->codigo }}</p>
                <h2 class="h5">{{ $propiedad->titulo }}</h2>
                <p class="text-muted small mb-2">
                    {{ $propiedad->categoria?->nombre }} &middot; {{ $propiedad->tipopropiedad }}
                    @if ($propiedad->ubicacion?->zona) &middot; {{ $propiedad->ubicacion->zona }} @endif
                </p>
                <p class="fw-bold mb-2">USD {{ number_format($propiedad->precio, 0, '.', ',') }}@if ($propiedad->tipopropiedad === 'Alquiler') <small class="text-muted fw-normal">/ mes</small>@endif</p>
                @if ($propiedad->agente)
                    <p class="small mb-0">Agente a cargo: <strong>{{ $propiedad->agente->nombre }}</strong></p>
                @endif
                <p class="small text-muted mt-2 mb-0">Atendemos de 08:00 a 18:00, en bloques de una hora.</p>
            </div>
        </div>
    </div>

    <div class="col-lg-8 order-lg-1">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('visitas.store', $propiedad) }}" novalidate>
                    @csrf

                    {{-- Trampa para robots (no se muestra a las personas) --}}
                    <div style="position: absolute; left: -9999px" aria-hidden="true">
                        <label for="sitio_web">No completes este campo</label>
                        <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
                    </div>

                    <h2 class="h6 text-uppercase text-muted">¿Cuándo?</h2>
                    <div class="row">
                        <div class="col-sm-6">
                            <x-campo name="fecha" label="Fecha" type="date" :valor="request('fecha')" :min="$minimo" />
                        </div>
                        <div class="col-sm-6">
                            <div class="mb-3">
                                <label for="hora" class="form-label">Hora</label>
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

                    <h2 class="h6 text-uppercase text-muted mt-2">{{ $personal ? 'Cliente' : 'Tus datos de contacto' }}</h2>

                    @if ($personal)
                        <x-seleccion name="idcliente" label="Cliente del CRM" :opciones="$clientes" :valor="$clienteElegido"
                                     vacio="— Cliente nuevo (escribe sus datos) —" />
                    @endif

                    <div id="datos-cliente">
                        <x-campo name="nombre" label="Nombre completo" autocomplete="name" />
                        <x-campo name="email" label="Correo electrónico" type="email" autocomplete="email" />
                        <x-campo name="telefono" label="Teléfono" type="tel" autocomplete="tel"
                                 ayuda="Indica el correo o el teléfono (al menos uno)." />
                    </div>

                    <div class="mb-3">
                        <label for="observacion" class="form-label">Observación <span class="text-muted">(opcional)</span></label>
                        <textarea id="observacion" name="observacion" rows="3"
                                  class="form-control @error('observacion') is-invalid @enderror">{{ old('observacion') }}</textarea>
                        @error('observacion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Confirmar visita</button>
                        <a href="{{ route('catalogo.detalle', $propiedad) }}" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        // Horas que el agente ya tiene ocupadas, por fecha. Solo ayuda a elegir: el servidor vuelve a comprobarlo al guardar.
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

        // El Agente o el Administrador: si elige un cliente del CRM, no hace falta escribir sus datos.
        var cliente = document.getElementById('idcliente');
        var datos = document.getElementById('datos-cliente');
        if (cliente) {
            function alternarDatos() { datos.style.display = cliente.value ? 'none' : ''; }
            cliente.addEventListener('change', alternarDatos);
            alternarDatos();
        }
    })();
</script>
@endpush
@endsection
