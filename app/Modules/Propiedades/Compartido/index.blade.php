@extends('layouts.app')

@section('titulo', 'Propiedades - LIT Inmobiliaria')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Propiedades</h1>
    <a href="{{ route('propiedades.create') }}" class="btn btn-primary">Registrar propiedad</a>
</div>

<form method="GET" action="{{ route('propiedades.index') }}" class="row g-2 mb-4">
    <div class="col-md-4">
        <input type="text" name="q" value="{{ $busqueda }}" class="form-control" placeholder="Buscar por título">
    </div>
    <div class="col-md-3">
        <select name="tipo" class="form-select">
            <option value="">Todos los tipos</option>
            @foreach (\App\Models\Propiedad::TIPOS as $tipo)
                <option value="{{ $tipo }}" @selected(request('tipo') === $tipo)>{{ $tipo }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="estado" class="form-select">
            <option value="">Todos los estados</option>
            @foreach (['DISPONIBLE', 'RESERVADO', 'VENDIDO', 'ALQUILADO'] as $estado)
                <option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ ucfirst(strtolower($estado)) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-outline-secondary w-100">Filtrar</button>
    </div>
</form>

@php($colores = ['DISPONIBLE' => 'success', 'RESERVADO' => 'warning', 'VENDIDO' => 'secondary', 'ALQUILADO' => 'info'])

<div class="row g-3">
    @forelse ($propiedades as $p)
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm">
                @if ($p->imagenPrincipal)
                    <img src="{{ asset($p->imagenPrincipal->ruta) }}" class="card-img-top" alt="{{ $p->titulo }}"
                         style="height: 180px; object-fit: cover">
                @else
                    <div class="bg-secondary-subtle text-center text-muted d-flex align-items-center justify-content-center"
                         style="height: 180px">Sin imagen</div>
                @endif

                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <h2 class="h6 mb-1">{{ $p->titulo }}</h2>
                        <span class="badge text-bg-{{ $colores[$p->estadopropiedad] ?? 'secondary' }}">
                            {{ ucfirst(strtolower($p->estadopropiedad)) }}
                        </span>
                    </div>
                    <p class="text-muted small mb-1">
                        {{ $p->categoria?->nombre }} &middot; {{ $p->tipopropiedad }}
                        @if ($p->ubicacion?->zona) &middot; {{ $p->ubicacion->zona }} @endif
                    </p>
                    <p class="fw-bold mb-0">$ {{ number_format($p->precio, 0, ".", ",") }}@if($p->tipopropiedad === 'Alquiler') <small class="text-muted fw-normal">/ mes</small>@endif</p>
                </div>

                <div class="card-footer bg-white d-flex gap-2">
                    <a href="{{ route('propiedades.edit', $p) }}" class="btn btn-sm btn-outline-primary">Modificar</a>
                    @if (auth()->user()->tieneRol('Administrador'))
                        <a href="{{ route('propiedades.baja', $p) }}" class="btn btn-sm btn-outline-danger">Dar de baja</a>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="alert alert-light border text-center mb-0">No hay propiedades para mostrar.</div>
        </div>
    @endforelse
</div>

<div class="mt-4">{{ $propiedades->links('pagination::bootstrap-5') }}</div>
@endsection
