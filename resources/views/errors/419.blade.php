@extends('layouts.app')

@section('titulo', 'La página expiró - LIT Inmobiliaria')

@section('contenido')
<div class="text-center py-5">
    <p class="display-1 fw-bold text-secondary mb-0">419</p>
    <h1 class="h3 mb-3">La página expiró</h1>
    <p class="text-muted">Tu sesión expiró o el formulario era demasiado pesado (por ejemplo, imágenes muy grandes). Vuelve a intentarlo.</p>
    <a href="{{ url('/') }}" class="btn btn-primary">Volver al inicio</a>
</div>
@endsection
