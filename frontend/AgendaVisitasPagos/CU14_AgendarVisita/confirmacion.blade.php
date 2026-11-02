@extends('layouts.app')

@section('titulo', 'Visita agendada - LIT Inmobiliaria')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="alert alert-success">
            <h1 class="h4 mb-1">¡Visita agendada! <span class="cu-etiqueta">(CU14)</span></h1>
            <p class="mb-0">
                {{ $personal ? 'La visita quedó registrada.' : 'Recibimos tu solicitud. El agente a cargo se pondrá en contacto contigo.' }}
            </p>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h5 mb-3">Resumen de la visita</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Propiedad</dt>
                    <dd class="col-sm-8">{{ $visita->propiedad->codigo }} &middot; {{ $visita->propiedad->titulo }}</dd>
                    <dt class="col-sm-4">Fecha</dt>
                    <dd class="col-sm-8">{{ $visita->fecha->format('d/m/Y') }}</dd>
                    <dt class="col-sm-4">Hora</dt>
                    <dd class="col-sm-8">{{ $visita->hora_corta }}</dd>
                    <dt class="col-sm-4">Estado</dt>
                    <dd class="col-sm-8"><span class="badge text-bg-primary">{{ $visita->estado_etiqueta }}</span></dd>
                    <dt class="col-sm-4">Cliente</dt>
                    <dd class="col-sm-8">{{ $visita->cliente->nombre }}</dd>
                    @if ($visita->propiedad->agente)
                        <dt class="col-sm-4">Agente</dt>
                        <dd class="col-sm-8">{{ $visita->propiedad->agente->nombre }}</dd>
                    @endif
                    @if ($visita->observacion)
                        <dt class="col-sm-4">Observación</dt>
                        <dd class="col-sm-8">{{ $visita->observacion }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('catalogo.detalle', $visita->propiedad) }}" class="btn btn-outline-secondary">Volver a la ficha</a>
            @if ($personal)
                @if (Route::has('visitas.calendario'))
                    <a href="{{ route('visitas.calendario', ['fecha' => $visita->fecha->format('Y-m-d')]) }}" class="btn btn-primary">Ver calendario de visitas</a>
                @endif
                <a href="{{ route('propiedades.index') }}" class="btn btn-outline-primary">Ir al inventario</a>
            @else
                <a href="{{ route('catalogo.index') }}" class="btn btn-primary">Ver más propiedades</a>
            @endif
        </div>
    </div>
</div>
@endsection
