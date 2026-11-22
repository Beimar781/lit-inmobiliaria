@extends('layouts.app')

@section('titulo', 'Acceso no autorizado - LIT Inmobiliaria')

@section('contenido')
<div class="text-center py-5">
    <p class="display-1 fw-bold text-secondary mb-0">401</p>
    <h1 class="h3 mb-3">Acceso no autorizado</h1>
    <p class="text-muted">Necesitas iniciar sesión para ver esta página.</p>
    <a href="{{ route('login') }}" class="btn btn-primary">Iniciar sesión</a>
</div>
@endsection
