@extends('layouts.app')

@section('titulo', 'Acción no permitida - LIT Inmobiliaria')

@section('contenido')
<div class="text-center py-5">
    <p class="display-1 fw-bold text-secondary mb-0">405</p>
    <h1 class="h3 mb-3">Acción no permitida</h1>
    <p class="text-muted">Esta dirección no se puede abrir de esa forma. Vuelve al inicio y usa los botones del sistema.</p>
    <a href="{{ url('/') }}" class="btn btn-primary">Volver al inicio</a>
</div>
@endsection
