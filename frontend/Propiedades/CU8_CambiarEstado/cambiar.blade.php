@extends('layouts.app')

@section('titulo', 'Cambiar estado - LIT Inmobiliaria')

@section('contenido')
<a href="{{ route('propiedades.index') }}" class="text-decoration-none">&larr; Volver a propiedades</a>
<h1 class="h3 my-3">Cambiar estado de propiedad</h1>

@php($colores = ['DISPONIBLE' => 'success', 'RESERVADO' => 'warning', 'VENDIDO' => 'secondary', 'ALQUILADO' => 'info'])

<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex gap-3 mb-4">
            @if ($propiedad->imagenes->first())
                <img src="{{ asset($propiedad->imagenes->first()->ruta) }}" alt="{{ $propiedad->titulo }}"
                     class="rounded" style="width: 140px; height: 100px; object-fit: cover">
            @endif
            <div>
                <p class="text-muted small mb-1">{{ $propiedad->codigo }}</p>
                <h2 class="h5 mb-1">{{ $propiedad->titulo }}</h2>
                <p class="text-muted small mb-1">
                    {{ $propiedad->categoria?->nombre }} &middot; {{ $propiedad->tipopropiedad }}
                    @if ($propiedad->ubicacion?->zona) &middot; {{ $propiedad->ubicacion->zona }} @endif
                </p>
                <p class="mb-0">Estado actual:
                    <span class="badge text-bg-{{ $colores[$propiedad->estadopropiedad] ?? 'secondary' }}">{{ ucfirst(strtolower($propiedad->estadopropiedad)) }}</span>
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('propiedades.estado.actualizar', $propiedad) }}" novalidate>
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-md-5">
                    <x-seleccion name="estadopropiedad" label="Nuevo estado"
                                 :opciones="collect($estados)->mapWithKeys(fn ($e) => [$e => ucfirst(strtolower($e))])->all()"
                                 :valor="old('estadopropiedad')" vacio="— Elige un estado —" />
                </div>
            </div>

            <div class="mb-3">
                <label for="motivo" class="form-label">Motivo <span class="text-muted">(opcional)</span></label>
                <textarea id="motivo" name="motivo" rows="3"
                          class="form-control @error('motivo') is-invalid @enderror">{{ old('motivo') }}</textarea>
                <div class="form-text">Es obligatorio si una propiedad vendida o alquilada vuelve a quedar disponible.</div>
                @error('motivo')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary">Guardar estado</button>
            <a href="{{ route('propiedades.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
