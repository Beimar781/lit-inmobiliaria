@extends('layouts.app')

@section('titulo', 'Detalle de la visita - LIT Inmobiliaria')

@php($colores = ['PROGRAMADA' => 'primary', 'REAGENDADA' => 'warning', 'CANCELADA' => 'secondary', 'REALIZADA' => 'success'])

@section('contenido')
<a href="{{ route('visitas.calendario', ['fecha' => $visita->fecha->format('Y-m-d')]) }}" class="text-decoration-none">&larr; Volver al calendario</a>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 my-3">
    <div>
        <h1 class="h3 mb-1">Visita del {{ $visita->fecha->format('d/m/Y') }} a las {{ $visita->hora_corta }} <span class="cu-etiqueta">(CU17)</span></h1>
        <p class="text-muted mb-0">{{ $visita->propiedad->codigo }} &middot; {{ $visita->propiedad->titulo }}</p>
    </div>
    <span class="badge text-bg-{{ $colores[$visita->estado] ?? 'secondary' }} fs-6">{{ $visita->estado_etiqueta }}</span>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Propiedad</dt>
                    <dd class="col-sm-8">
                        {{ $visita->propiedad->codigo }} &middot; {{ $visita->propiedad->titulo }}
                        @if ($visita->propiedad->estadopropiedad !== 'BAJA')
                            <a href="{{ route('catalogo.detalle', $visita->propiedad) }}" class="ms-1 small">Ver ficha</a>
                        @endif
                    </dd>
                    <dt class="col-sm-4">Cliente</dt>
                    <dd class="col-sm-8">{{ $visita->cliente->nombre }}</dd>
                    <dt class="col-sm-4">Contacto</dt>
                    <dd class="col-sm-8">
                        {{ $visita->cliente->email ?: '—' }}<br>
                        {{ $visita->cliente->telefono ?: '—' }}
                    </dd>
                    <dt class="col-sm-4">Fecha</dt>
                    <dd class="col-sm-8">{{ $visita->fecha->format('d/m/Y') }}</dd>
                    <dt class="col-sm-4">Hora</dt>
                    <dd class="col-sm-8">{{ $visita->hora_corta }}</dd>
                    <dt class="col-sm-4">Estado</dt>
                    <dd class="col-sm-8">{{ $visita->estado_etiqueta }}</dd>
                    <dt class="col-sm-4">Agente</dt>
                    <dd class="col-sm-8">{{ $visita->propiedad->agente?->nombre ?? 'Sin asignar' }}</dd>
                    <dt class="col-sm-4">Observaciones</dt>
                    <dd class="col-sm-8">{{ $visita->observacion ?: '—' }}</dd>
                    @if ($visita->motivo)
                        <dt class="col-sm-4">Motivo</dt>
                        <dd class="col-sm-8">{{ $visita->motivo }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Acciones</h2>

                @if ($visita->estaActiva())
                    <div class="d-grid gap-2">
                        {{-- CU15 y CU16: solo se ofrecen cuando esas pantallas existen --}}
                        @if ($puedeReagendar)
                            <a href="{{ route('visitas.reagendar', $visita) }}" class="btn btn-outline-primary">Reagendar</a>
                        @endif
                        @if ($puedeCancelar)
                            <a href="{{ route('visitas.cancelar', $visita) }}" class="btn btn-outline-danger">Cancelar visita</a>
                        @endif

                        <form method="POST" action="{{ route('visitas.realizada', $visita) }}"
                              onsubmit="return confirm('¿Marcar esta visita como realizada?')">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-outline-success w-100">Marcar como realizada</button>
                        </form>
                    </div>
                @else
                    <p class="text-muted mb-0">Esta visita está {{ strtolower($visita->estado_etiqueta) }}: ya no tiene acciones disponibles.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
