@extends('layouts.app')

@section('titulo', 'Reporte no permitido - LIT Inmobiliaria')

@section('contenido')
<div class="text-center py-5">
    <p class="display-1 fw-bold text-secondary mb-0">403</p>
    <h1 class="h3 mb-3">Este reporte no está permitido para tu rol.</h1>
    <a href="{{ route('reportes.index') }}" class="btn btn-primary">Volver a reportes</a>
</div>
@endsection