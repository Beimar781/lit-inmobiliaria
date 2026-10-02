@extends('layouts.app')

@section('titulo', 'Panel - LIT Inmobiliaria')

@section('contenido')
<h1 class="h3 mb-1">Bienvenido, {{ auth()->user()->nombre }}</h1>
<p class="text-muted">Rol: {{ auth()->user()->rol->nombre }}</p>

<div class="row g-3">
    {{-- Usuarios (CU4): solo aparece si la ruta está disponible --}}
    @if (auth()->user()->tieneRol('Administrador') && Route::has('usuarios.index'))
        <div class="col-md-4">
            <a href="{{ route('usuarios.index') }}" class="card text-decoration-none h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Usuarios</h2>
                    <p class="mb-0 text-muted">Gestionar las cuentas de los usuarios del sistema</p>
                </div>
            </a>
        </div>
    @endif

    {{-- CU5, CU6 y CU7 --}}
    @if (auth()->user()->tieneRol('Administrador', 'Agente Inmobiliario') && Route::has('propiedades.index'))
        <div class="col-md-4">
            <a href="{{ route('propiedades.index') }}" class="card text-decoration-none h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Propiedades</h2>
                    <p class="mb-0 text-muted">Registrar, modificar y dar de baja propiedades</p>
                </div>
            </a>
        </div>
    @endif

    {{-- Bitácora del sistema (solo administrador) --}}
    @if (auth()->user()->tieneRol('Administrador') && Route::has('bitacora.index'))
        <div class="col-md-4">
            <a href="{{ route('bitacora.index') }}" class="card text-decoration-none h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5">Bitácora</h2>
                    <p class="mb-0 text-muted">Registro de la actividad de todo el sistema</p>
                </div>
            </a>
        </div>
    @endif
</div>
@endsection
