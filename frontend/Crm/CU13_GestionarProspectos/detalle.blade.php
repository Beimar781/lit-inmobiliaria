@extends('layouts.app')

@section('titulo', $cliente->nombre . ' - Prospectos CRM')

@section('contenido')
@php($colores = ['NUEVO' => 'primary', 'CONTACTADO' => 'info', 'EN_NEGOCIACION' => 'warning', 'CERRADO' => 'success', 'DESCARTADO' => 'secondary'])

<a href="{{ route('prospectos.index') }}" class="text-decoration-none">&larr; Volver a prospectos</a>

<div class="d-flex justify-content-between align-items-start my-3">
    <div>
        <h1 class="h3 mb-1">{{ $cliente->nombre }}</h1>
        <p class="text-muted mb-0">{{ $cliente->tipocliente }} &middot; registrado el {{ $cliente->fecharegistro?->format('d/m/Y') }}</p>
    </div>
    <span class="badge text-bg-{{ $colores[$cliente->estadoseguimiento] ?? 'secondary' }} fs-6">{{ $cliente->estado_etiqueta }}</span>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5">Datos de contacto</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Correo</dt>
                    <dd class="col-sm-8">{{ $cliente->email ?: '—' }}</dd>
                    <dt class="col-sm-4">Teléfono</dt>
                    <dd class="col-sm-8">{{ $cliente->telefono ?: '—' }}</dd>
                    <dt class="col-sm-4">Agente</dt>
                    <dd class="col-sm-8">{{ $cliente->agente?->nombre ?? 'Sin asignar' }}</dd>
                </dl>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5">Estado de seguimiento</h2>
                <form method="POST" action="{{ route('prospectos.seguimiento', $cliente) }}" novalidate>
                    @csrf
                    @method('PATCH')
                    <x-seleccion name="estadoseguimiento" label="Estado" :opciones="$estados" :valor="$cliente->estadoseguimiento" />
                    <button type="submit" class="btn btn-primary btn-sm">Guardar seguimiento</button>
                </form>
            </div>
        </div>

        @if ($esAdmin)
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5">Reasignar a otro agente</h2>
                    <form method="POST" action="{{ route('prospectos.asignar', $cliente) }}" novalidate>
                        @csrf
                        @method('PATCH')
                        <x-seleccion name="idusuario" label="Agente" :opciones="$agentes" :valor="$cliente->idusuario" vacio="— Elige un agente —" />
                        <button type="submit" class="btn btn-outline-primary btn-sm">Asignar</button>
                    </form>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">Propiedades de interés</h2>

                @forelse ($cliente->intereses->sortByDesc('idinteres') as $interes)
                    <div class="border rounded p-3 mb-2">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted small mb-0">{{ $interes->propiedad->codigo }} &middot; {{ $interes->fecha?->format('d/m/Y') }}</p>
                                <p class="fw-semibold mb-0">{{ $interes->propiedad->titulo }}</p>
                                <p class="small text-muted mb-0">{{ ucfirst(strtolower($interes->propiedad->estadopropiedad)) }}</p>
                            </div>
                            @if ($interes->propiedad->estadopropiedad !== 'BAJA')
                                <a href="{{ route('catalogo.detalle', $interes->propiedad) }}" class="btn btn-sm btn-outline-secondary align-self-start">Ver ficha</a>
                            @endif
                        </div>
                        @if ($interes->mensaje)
                            <p class="mb-0 mt-2 fst-italic">«{{ $interes->mensaje }}»</p>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mb-0">Este prospecto aún no tiene propiedades de interés.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
