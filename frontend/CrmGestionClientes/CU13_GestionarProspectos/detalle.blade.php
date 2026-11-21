@extends('layouts.app')

@section('titulo', $cliente->nombre . ' - Prospectos CRM')

@section('contenido')
@php($colores = ['NUEVO' => 'primary', 'CONTACTADO' => 'info', 'EN_NEGOCIACION' => 'warning', 'CERRADO' => 'success', 'DESCARTADO' => 'secondary'])

<a href="{{ route('prospectos.index') }}" class="text-decoration-none">&larr; Volver a prospectos</a>

<div class="d-flex justify-content-between align-items-start my-3">
    <div>
        <h1 class="h3 mb-1">{{ $cliente->nombre }} <span class="cu-etiqueta">(CU13)</span></h1>
        <p class="text-muted mb-0">{{ $cliente->tipocliente }} &middot; registrado el {{ $cliente->fecharegistro?->format('d/m/Y') }}</p>
    </div>
    <span class="badge text-bg-{{ $colores[$cliente->estadoseguimiento] ?? 'secondary' }} fs-6">{{ $cliente->estado_etiqueta }}</span>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="h5 mb-1">Seguimiento de contactos</h2>
            @if ($ultimoContacto)
                <p class="mb-0 small">Último contacto: <strong>{{ $ultimoContacto->fechacontacto->format('d/m/Y H:i') }}</strong> por {{ \App\Models\Seguimiento::MEDIOS[$ultimoContacto->medio] ?? $ultimoContacto->medio }} &mdash; {{ \Illuminate\Support\Str::limit($ultimoContacto->nota, 90) }}</p>
            @else
                <p class="mb-0 small text-muted">Sin seguimientos registrados</p>
            @endif
            @if ($proximoContacto)
                <span class="badge text-bg-warning mt-1">Próximo contacto: {{ \Illuminate\Support\Carbon::parse($proximoContacto)->format('d/m/Y') }}</span>
            @endif
        </div>
        <a href="{{ route('prospectos.seguimientos', $cliente) }}" class="btn btn-primary">Seguimiento</a>
    </div>
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
                    <dt class="col-sm-4">Estado</dt>
                    <dd class="col-sm-8">{{ $estadosCliente[$cliente->estado] ?? $cliente->estado }}</dd>
                    <dt class="col-sm-4">Agente</dt>
                    <dd class="col-sm-8">{{ $cliente->agente?->nombre ?? 'Sin asignar' }}</dd>
                </dl>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h5 mb-0">Inquilino</h2>
                    <a href="{{ route('prospectos.inquilino', $cliente) }}" class="btn btn-sm btn-outline-primary">{{ $cliente->inquilino ? 'Editar inquilino' : 'Inquilino' }}</a>
                </div>
                @if ($cliente->inquilino)
                    @php($inq = $cliente->inquilino)
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Ocupación</dt>
                        <dd class="col-sm-8">{{ $inq->ocupacion }}</dd>
                        <dt class="col-sm-4">Garantía</dt>
                        <dd class="col-sm-8">{{ \App\Models\Inquilino::GARANTIAS[$inq->tipogarantia] ?? $inq->tipogarantia }}@if ($inq->detallegarantia): {{ $inq->detallegarantia }}@endif</dd>
                        <dt class="col-sm-4">Estado</dt>
                        <dd class="col-sm-8">{{ \App\Models\Inquilino::ESTADOS[$inq->estado] ?? $inq->estado }} <span class="text-muted small">(registrado el {{ $inq->fecharegistro->format('d/m/Y') }})</span></dd>
                    </dl>
                @else
                    <p class="text-muted small mb-0">Este cliente aún no está registrado como inquilino.</p>
                @endif
            </div>
        </div>
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5">Estado de seguimiento</h2>
                <form method="POST" action="{{ route('prospectos.seguimiento', $cliente) }}" novalidate>
                    @csrf
                    @method('PATCH')
                    <x-seleccion name="estadoseguimiento" label="Estado" :opciones="$estados" :valor="$cliente->estadoseguimiento" />
                    <x-seleccion name="estado" label="Estado del cliente" :opciones="$estadosCliente" :valor="$cliente->estado" />
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
                            <div class="d-flex flex-wrap gap-1 align-self-start">
                                @if ($interes->propiedad->estadopropiedad === 'DISPONIBLE')
                                    {{-- CU14: agendar una visita de este prospecto --}}
                                    <a href="{{ route('visitas.create', ['propiedad' => $interes->propiedad, 'cliente' => $cliente->idcliente]) }}" class="btn btn-sm btn-outline-success">Agendar visita</a>
                                @endif
                                @if ($interes->propiedad->estadopropiedad !== 'BAJA')
                                    <a href="{{ route('catalogo.detalle', $interes->propiedad) }}" class="btn btn-sm btn-outline-secondary">Ver ficha</a>
                                @endif
                            </div>
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
