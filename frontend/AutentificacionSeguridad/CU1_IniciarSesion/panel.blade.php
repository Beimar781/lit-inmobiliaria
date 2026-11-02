@extends('layouts.app')

@section('titulo', 'Panel - LIT Inmobiliaria')

@section('contenido')
<h1 class="h3 mb-1">Bienvenido, {{ auth()->user()->nombre }}</h1>
<p class="text-muted">Rol: {{ auth()->user()->rol->nombre }}</p>

@if (! $tarjetas)
    <div class="alert alert-info">
        Por ahora tu rol no tiene opciones disponibles en esta versión del sistema.
    </div>
@endif

<div class="row g-3">
    @foreach ($tarjetas as $tarjeta)
        <div class="col-md-4">
            <a href="{{ route($tarjeta['ruta']) }}" class="card text-decoration-none h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5">{{ $tarjeta['titulo'] }}</h2>
                    <p class="mb-0 text-muted">{{ $tarjeta['descripcion'] }}</p>
                </div>
            </a>
        </div>
    @endforeach
</div>
@endsection
