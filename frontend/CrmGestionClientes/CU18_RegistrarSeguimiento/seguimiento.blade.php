@extends('layouts.app')

@section('titulo', 'Seguimiento de ' . $cliente->nombre . ' - LIT Inmobiliaria')

@section('contenido')
<a href="{{ route('prospectos.show', $cliente) }}" class="text-decoration-none">&larr; Volver al prospecto</a>

<h1 class="h3 my-3">Seguimiento de {{ $cliente->nombre }} <span class="cu-etiqueta">(CU18)</span></h1>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Registrar contacto</h2>
                <form method="POST" action="{{ route('prospectos.seguimientos.guardar', $cliente) }}" novalidate>
                    @csrf
                    <x-seleccion name="medio" label="Medio de contacto" :opciones="$medios" vacio="— Elige un medio —" />

                    <x-campo name="fechacontacto" label="Fecha y hora del contacto" type="datetime-local"
                             :valor="now()->format('Y-m-d\TH:i')" />

                    <div class="mb-3">
                        <label for="nota" class="form-label">Nota</label>
                        <textarea id="nota" name="nota" rows="4" maxlength="2000"
                                  class="form-control @error('nota') is-invalid @enderror">{{ old('nota') }}</textarea>
                        @error('nota')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    @if (count($propiedades) > 0)
                        <x-seleccion name="idpropiedad" label="Propiedad tratada (opcional)" :opciones="$propiedades" vacio="— Ninguna —" />
                    @endif

                    <x-campo name="proximocontacto" label="Próximo contacto (opcional)" type="date" />

                    <button type="submit" class="btn btn-primary">Registrar seguimiento</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">Historial de contactos</h2>

                @forelse ($seguimientos as $s)
                    <div class="border rounded p-3 mb-2">
                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold">{{ $medios[$s->medio] ?? $s->medio }}</span>
                            <span class="text-muted small">{{ $s->fechacontacto->format('d/m/Y H:i') }}</span>
                        </div>
                        <p class="mb-1 mt-1" style="white-space: pre-line">{{ $s->nota }}</p>
                        <p class="small text-muted mb-0">
                            Registrado por {{ $s->usuario->nombre }}
                            @if ($s->propiedad) &middot; {{ $s->propiedad->codigo }} {{ $s->propiedad->titulo }} @endif
                        </p>
                        @if ($s->proximocontacto)
                            <p class="small mb-0"><span class="badge text-bg-warning">Próximo contacto: {{ $s->proximocontacto->format('d/m/Y') }}</span></p>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mb-0">Sin seguimientos registrados</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection