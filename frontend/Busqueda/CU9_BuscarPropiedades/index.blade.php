@extends('layouts.app')

@section('titulo', 'Catálogo de propiedades - LIT Inmobiliaria')

@section('contenido')
<h1 class="h3 mb-1">Catálogo de propiedades</h1>
<p class="text-muted">Propiedades disponibles en Santa Cruz de la Sierra, dentro del 4.º anillo.</p>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- CU9: búsqueda por palabra clave (el código LIT-0007 también funciona, aunque no se muestra aquí) · CU10: filtros avanzados --}}
<form method="GET" action="{{ route('catalogo.index') }}" class="card card-body shadow-sm mb-4" novalidate>
    <div class="row g-2 mb-3">
        <div class="col-md-9">
            <input type="text" name="q" value="{{ $busqueda }}" class="form-control @error('q') is-invalid @enderror"
                   placeholder="Busca por título, zona o dirección">
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100">Buscar</button>
        </div>
    </div>

    @include('busqueda::CU10_FiltrarPropiedades.filtros')
</form>

@if ($filtros->hay() || $busqueda !== '')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        @if ($busqueda !== '')
            <span class="badge text-bg-secondary">Búsqueda: {{ $busqueda }}</span>
        @endif
        @foreach ($filtros->activos() as $activo)
            <span class="badge text-bg-light border">{{ $activo }}</span>
        @endforeach
        <a href="{{ route('catalogo.index') }}" class="small">Limpiar filtros</a>
    </div>
@endif

<div class="row g-3">
    @forelse ($propiedades as $p)
        <div class="col-md-6 col-lg-4">
            <a href="{{ route('catalogo.detalle', $p) }}" class="card h-100 shadow-sm text-decoration-none text-body">
                @if ($p->imagenPrincipal)
                    <img src="{{ asset($p->imagenPrincipal->ruta) }}" class="card-img-top" alt="{{ $p->titulo }}" loading="lazy"
                         style="height: 190px; object-fit: cover">
                @else
                    <div class="bg-secondary-subtle text-center text-muted d-flex align-items-center justify-content-center"
                         style="height: 190px">Sin imagen</div>
                @endif

                <div class="card-body">
                    <h2 class="h6 mb-1">{{ $p->titulo }}</h2>
                    <p class="text-muted small mb-2">
                        {{ $p->categoria?->nombre }} &middot; {{ $p->tipopropiedad }}
                        @if ($p->ubicacion?->zona) &middot; {{ $p->ubicacion->zona }} @endif
                    </p>
                    <p class="fw-bold mb-0">USD {{ number_format($p->precio, 0, '.', ',') }}@if ($p->tipopropiedad === 'Alquiler') <small class="text-muted fw-normal">/ mes</small>@endif</p>
                </div>
            </a>
        </div>
    @empty
        <div class="col-12">
            <div class="alert alert-light border text-center mb-0">
                @if ($busqueda !== '' || $filtros->hay())
                    No encontramos propiedades que coincidan con tu búsqueda.
                    <a href="{{ route('catalogo.index') }}">Ver todo el catálogo</a>
                @else
                    Por ahora no hay propiedades disponibles. Vuelve pronto.
                @endif
            </div>
        </div>
    @endforelse
</div>

<div class="mt-4">{{ $propiedades->links('pagination::bootstrap-5') }}</div>
@endsection
