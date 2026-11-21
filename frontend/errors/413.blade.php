@extends('layouts.app')

@section('titulo', 'Archivos demasiado grandes - LIT Inmobiliaria')

@section('contenido')
<div class="text-center py-5">
    <p class="display-1 fw-bold text-secondary mb-0">413</p>
    <h1 class="h3 mb-3">Los archivos son demasiado grandes</h1>
    <p class="text-muted">Lo que intentaste subir supera el tamaño permitido. Cada imagen puede pesar como máximo 4 MB.</p>
    <a href="javascript:history.back()" class="btn btn-primary">Volver</a>
</div>
@endsection