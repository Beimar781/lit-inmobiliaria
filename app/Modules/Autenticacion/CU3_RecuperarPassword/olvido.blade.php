@extends('layouts.app')

@section('titulo', 'Recuperar contraseña - LIT Inmobiliaria')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-2 text-center">Recuperar contraseña</h1>
                <p class="text-muted small text-center">Ingresa tu correo y te enviaremos un enlace para crear una nueva contraseña.</p>

                <form method="POST" action="{{ route('password.email') }}" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror" autofocus>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Enviar enlace</button>
                </form>

                <div class="text-center mt-3">
                    <a href="{{ route('login') }}">Volver a iniciar sesión</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
