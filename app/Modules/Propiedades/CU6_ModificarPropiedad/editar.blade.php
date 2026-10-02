@extends('layouts.app')

@section('titulo', 'Modificar propiedad - LIT Inmobiliaria')

@section('contenido')
<a href="{{ route('propiedades.index') }}" class="text-decoration-none">&larr; Volver a propiedades</a>
<h1 class="h3 my-3">Modificar propiedad</h1>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('propiedades.update', $propiedad) }}" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PUT')
            @include('propiedades::Compartido.campos')

            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a href="{{ route('propiedades.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
