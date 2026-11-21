@extends('layouts.app')

@section('titulo', 'Inquilino de ' . $cliente->nombre . ' - LIT Inmobiliaria')

@section('contenido')
<a href="{{ route('prospectos.show', $cliente) }}" class="text-decoration-none">&larr; Volver al prospecto</a>

<h1 class="h3 my-3">{{ $inquilino ? 'Editar inquilino' : 'Registrar inquilino' }}: {{ $cliente->nombre }} <span class="cu-etiqueta">(CU24)</span></h1>

<div class="card shadow-sm" style="max-width: 640px;">
    <div class="card-body">
        <form method="POST" action="{{ route('prospectos.inquilino.guardar', $cliente) }}" novalidate>
            @csrf

            <x-campo name="ocupacion" label="Ocupación" :valor="$inquilino?->ocupacion" maxlength="100" />

            <x-seleccion name="tipogarantia" label="Tipo de garantía" :opciones="$garantias" :valor="$inquilino?->tipogarantia" vacio="— Elige el tipo —" />

            <div class="mb-3">
                <label for="detallegarantia" class="form-label">Detalle de la garantía</label>
                <textarea id="detallegarantia" name="detallegarantia" rows="3" maxlength="2000"
                          class="form-control @error('detallegarantia') is-invalid @enderror">{{ old('detallegarantia', $inquilino?->detallegarantia) }}</textarea>
                <div class="form-text">Obligatorio salvo que el tipo sea «Ninguna».</div>
                @error('detallegarantia')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            @if ($inquilino)
                <x-seleccion name="estado" label="Estado" :opciones="$estados" :valor="$inquilino->estado" />
            @endif

            <button type="submit" class="btn btn-primary">{{ $inquilino ? 'Guardar cambios' : 'Registrar inquilino' }}</button>
            <a href="{{ route('prospectos.show', $cliente) }}" class="btn btn-outline-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection