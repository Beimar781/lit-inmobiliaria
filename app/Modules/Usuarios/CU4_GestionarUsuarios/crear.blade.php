@extends('layouts.app')

@section('titulo', 'Registrar usuario - LIT Inmobiliaria')

@section('contenido')
<a href="{{ route('usuarios.index') }}" class="text-decoration-none">&larr; Volver a usuarios</a>
<h1 class="h3 my-3">Registrar usuario</h1>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('usuarios.store') }}" novalidate>
            @csrf
            @include('usuarios::CU4_GestionarUsuarios.campos')

            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
