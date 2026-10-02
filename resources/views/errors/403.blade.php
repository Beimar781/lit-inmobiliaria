@extends('layouts.app')

@section('titulo', 'Acceso denegado - LIT Inmobiliaria')

@section('contenido')
<div class="text-center py-5">
    <p class="display-1 fw-bold text-secondary mb-0">403</p>
    <h1 class="h3 mb-3">Acceso denegado</h1>
    <p class="text-muted">No tienes permiso para acceder a esta sección.</p>
    <a href="{{ url('/') }}" class="btn btn-primary">Volver al inicio</a>
</div>
@endsection
