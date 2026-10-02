@extends('layouts.app')

@section('titulo', 'Página no encontrada - LIT Inmobiliaria')

@section('contenido')
<div class="text-center py-5">
    <p class="display-1 fw-bold text-secondary mb-0">404</p>
    <h1 class="h3 mb-3">Página no encontrada</h1>
    <p class="text-muted">La página que buscas no existe o ya no está disponible.</p>
    <a href="{{ url('/') }}" class="btn btn-primary">Volver al inicio</a>
</div>
@endsection
