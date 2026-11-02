@extends('layouts.app')

@section('titulo', 'Panel - LIT Inmobiliaria')

@section('contenido')
<h1 class="h3 mb-1">Bienvenido, {{ auth()->user()->nombre }}</h1>
<p class="text-muted">Rol: {{ auth()->user()->rol->nombre }}</p>

@if ($sinOpciones)
    <div class="alert alert-info">
        Por ahora tu rol no tiene opciones disponibles en esta versión del sistema.
    </div>
@endif

{{-- Un bloque por paquete del documento, con sus casos de uso adentro (config/paquetes.php) --}}
<div class="row g-3">
    @foreach ($paquetes as $paquete)
        <div class="col-12 col-md-6 col-xl-4">
            <section class="card h-100 shadow-sm" aria-labelledby="paquete-{{ $paquete['codigo'] }}">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0" id="paquete-{{ $paquete['codigo'] }}">
                        <span class="badge text-bg-dark me-1">{{ $paquete['codigo'] }}</span>
                        {{ $paquete['nombre'] }}
                    </h2>
                </div>

                <ul class="list-group list-group-flush">
                    @foreach ($paquete['casos'] as $caso)
                        <li class="list-group-item">
                            @if (($caso['accion'] ?? null) === 'cerrar_sesion')
                                {{-- CU2 no tiene página propia: es la acción de cerrar sesión --}}
                                <form method="POST" action="{{ route($caso['ruta']) }}" onsubmit="return confirm('¿Deseas cerrar sesión?')">
                                    @csrf
                                    <button type="submit" class="btn btn-link p-0 text-start text-decoration-none">
                                        <strong>{{ $caso['codigo'] }}</strong> {{ $caso['nombre'] }}
                                    </button>
                                </form>
                            @elseif (($caso['accion'] ?? null) === 'nota')
                                <span class="text-body-secondary"><strong>{{ $caso['codigo'] }}</strong> {{ $caso['nombre'] }}</span>
                            @else
                                <a href="{{ route($caso['ruta']) }}" class="text-decoration-none">
                                    <strong>{{ $caso['codigo'] }}</strong> {{ $caso['nombre'] }}
                                </a>
                            @endif
                            @isset($caso['nota'])
                                <div class="small text-muted">{{ $caso['nota'] }}</div>
                            @endisset
                        </li>
                    @endforeach

                    @if ($paquete['administracion'])
                        <li class="list-group-item bg-light small text-uppercase text-muted fw-semibold">Administración</li>
                        @foreach ($paquete['administracion'] as $funcion)
                            <li class="list-group-item">
                                <a href="{{ route($funcion['ruta']) }}" class="text-decoration-none">{{ $funcion['nombre'] }}</a>
                                <div class="small text-muted">{{ $funcion['descripcion'] }}</div>
                            </li>
                        @endforeach
                    @endif

                    @if ($paquete['portal'])
                        <li class="list-group-item bg-light small text-uppercase text-muted fw-semibold">Mi cuenta</li>
                        @foreach ($paquete['portal'] as $funcion)
                            <li class="list-group-item">
                                <a href="{{ route($funcion['ruta']) }}" class="text-decoration-none">{{ $funcion['nombre'] }}</a>
                                <div class="small text-muted">{{ $funcion['descripcion'] }}</div>
                            </li>
                        @endforeach
                    @endif

                    @if (! $paquete['casos'] && ! $paquete['administracion'] && ! $paquete['portal'])
                        <li class="list-group-item text-muted small">Sus casos de uso aún no están disponibles.</li>
                    @endif
                </ul>
            </section>
        </div>
    @endforeach
</div>
@endsection
