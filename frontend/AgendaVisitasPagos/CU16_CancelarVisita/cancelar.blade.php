@extends('layouts.app')

@section('titulo', 'Cancelar visita - LIT Inmobiliaria')

@section('contenido')
<a href="{{ route('visitas.show', $visita) }}" class="text-decoration-none">&larr; Volver al detalle de la visita</a>

<h1 class="h3 my-3">Cancelar visita</h1>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="alert alert-warning">
            ¿Seguro que deseas cancelar esta visita? Se conservará en el historial como «Cancelada» y el horario del agente quedará libre.
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Propiedad</dt>
                    <dd class="col-sm-8">{{ $visita->propiedad->codigo }} &middot; {{ $visita->propiedad->titulo }}</dd>
                    <dt class="col-sm-4">Cliente</dt>
                    <dd class="col-sm-8">{{ $visita->cliente->nombre }}</dd>
                    <dt class="col-sm-4">Fecha y hora</dt>
                    <dd class="col-sm-8">{{ $visita->fecha->format('d/m/Y') }} a las {{ $visita->hora_corta }}</dd>
                    <dt class="col-sm-4">Estado</dt>
                    <dd class="col-sm-8">{{ $visita->estado_etiqueta }}</dd>
                    <dt class="col-sm-4">Agente</dt>
                    <dd class="col-sm-8 mb-0">{{ $visita->propiedad->agente?->nombre ?? 'Sin asignar' }}</dd>
                </dl>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                {{-- Paso 4: motivo y confirmación explícita --}}
                <form method="POST" action="{{ route('visitas.cancelar.confirmar', $visita) }}" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="motivo" class="form-label">Motivo de la cancelación</label>
                        <textarea id="motivo" name="motivo" rows="3" maxlength="500"
                                  class="form-control @error('motivo') is-invalid @enderror">{{ old('motivo') }}</textarea>
                        @error('motivo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" name="accion" value="cancelar" class="btn btn-danger">Sí, cancelar la visita</button>
                        <button type="submit" name="accion" value="mantener" class="btn btn-outline-secondary">No, mantener la visita</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
