@extends('layouts.app')

@section('titulo', 'Dar de baja propiedad - LIT Inmobiliaria')

@section('contenido')
<a href="{{ route('propiedades.index') }}" class="text-decoration-none">&larr; Volver a propiedades</a>
<h1 class="h3 my-3">Dar de baja propiedad</h1>

@error('propiedad')
    <div class="alert alert-danger">{{ $message }}</div>
@enderror

<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex gap-3 mb-3">
            @if ($propiedad->imagenes->first())
                <img src="{{ asset($propiedad->imagenes->first()->ruta) }}" alt="{{ $propiedad->titulo }}"
                     class="rounded" style="width: 140px; height: 100px; object-fit: cover">
            @endif
            <div>
                <h2 class="h5 mb-1">{{ $propiedad->titulo }}</h2>
                <p class="text-muted small mb-1">
                    {{ $propiedad->categoria?->nombre }} &middot; {{ $propiedad->tipopropiedad }}
                    @if ($propiedad->ubicacion?->zona) &middot; {{ $propiedad->ubicacion->zona }} @endif
                </p>
                <p class="mb-0">Estado actual: <strong>{{ ucfirst(strtolower($propiedad->estadopropiedad)) }}</strong></p>
            </div>
        </div>

        <div class="alert alert-warning">
            La propiedad dejará de aparecer en el catálogo. Su historial se conserva.
        </div>

        <form method="POST" action="{{ route('propiedades.destroy', $propiedad) }}"
              onsubmit="return confirm('¿Confirmas dar de baja esta propiedad?')" novalidate>
            @csrf
            @method('DELETE')

            <div class="mb-3">
                <label for="motivo" class="form-label">Motivo de la baja</label>
                <textarea id="motivo" name="motivo" rows="3"
                          class="form-control @error('motivo') is-invalid @enderror">{{ old('motivo') }}</textarea>
                @error('motivo')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-danger">Confirmar baja</button>
            <a href="{{ route('propiedades.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
