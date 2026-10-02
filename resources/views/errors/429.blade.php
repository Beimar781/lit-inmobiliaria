@extends('layouts.app')

@section('titulo', 'Demasiados intentos - LIT Inmobiliaria')

@section('contenido')
<div class="text-center py-5">
    <p class="display-1 fw-bold text-secondary mb-0">429</p>
    <h1 class="h3 mb-3">Demasiados intentos</h1>
    <p class="text-muted">Espera un momento antes de volver a intentarlo.</p>
    <a href="{{ url('/') }}" class="btn btn-primary">Volver al inicio</a>
</div>
@endsection
