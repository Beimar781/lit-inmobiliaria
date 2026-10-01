@extends('layouts.app')

@section('titulo', 'Iniciar sesión - LIT Inmobiliaria')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-4 text-center">Iniciar sesión</h1>

                <form method="POST" action="{{ route('login.ingresar') }}" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror" autofocus>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <x-password-input name="password" />
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="recordar" name="recordar" value="1">
                        <label class="form-check-label" for="recordar">Recordarme</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                </form>

                <div class="text-center mt-3">
                    <a href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
