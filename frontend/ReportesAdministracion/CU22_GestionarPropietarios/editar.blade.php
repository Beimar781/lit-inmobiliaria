@extends('layouts.app')

@section('titulo', 'Editar propietario - LIT Inmobiliaria')

@section('contenido')
<h1 class="h3 mb-3">Editar propietario <span class="cu-etiqueta">(CU22)</span></h1>

<div class="card shadow-sm" style="max-width: 560px;">
    <div class="card-body">
        <form method="POST" action="{{ route('propietarios.update', $propietario) }}" novalidate>
            @csrf
            @method('PUT')
            @include('reportesadministracion::CU22_GestionarPropietarios.campos')
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a href="{{ route('propietarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection