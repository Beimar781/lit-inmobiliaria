@extends('layouts.app')

@section('titulo', 'Nueva contraseña - LIT Inmobiliaria')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-4 text-center">Nueva contraseña</h1>

                <form method="POST" action="{{ route('password.update') }}" novalidate>
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input type="email" id="email" name="email" value="{{ old('email', $email) }}"
                               class="form-control @error('email') is-invalid @enderror" readonly>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Nueva contraseña</label>
                        <x-password-input name="password" :autofocus="true" />
                        <div class="form-text">Mínimo 8 caracteres, con letras y números.</div>
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                        <x-password-input name="password_confirmation" />
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Guardar contraseña</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
