@extends('layouts.app')

@section('titulo', 'Registrar cliente interesado - LIT Inmobiliaria')

@section('contenido')
<a href="{{ route('prospectos.index') }}" class="text-decoration-none">&larr; Volver a prospectos</a>
<h1 class="h3 my-3">Registrar cliente interesado</h1>

<div class="card shadow-sm">
    <div class="card-body">
        <p class="text-muted">Para quien te contactó por WhatsApp, redes o llamada. Se guarda como prospecto del CRM.</p>

        <form method="POST" action="{{ route('prospectos.store') }}" novalidate>
            @csrf

            <div class="row">
                <div class="col-md-8">
                    <x-seleccion name="idpropiedad" label="Propiedad de interés" :opciones="$propiedades"
                                 :valor="$propiedadElegida" vacio="— Elige una propiedad —" />
                </div>
                <div class="col-md-4">
                    <x-seleccion name="tipocliente" label="Tipo de cliente" :opciones="$tipos" vacio="— Elige —" />
                </div>
            </div>

            <x-campo name="nombre" label="Nombre completo" />
            <div class="row">
                <div class="col-md-6"><x-campo name="email" label="Correo electrónico" type="email" /></div>
                <div class="col-md-6"><x-campo name="telefono" label="Teléfono" type="tel" /></div>
            </div>
            <p class="form-text mt-n2 mb-3">Indica al menos el correo o el teléfono.</p>

            <div class="mb-3">
                <label for="mensaje" class="form-label">Mensaje <span class="text-muted">(opcional)</span></label>
                <textarea id="mensaje" name="mensaje" rows="3"
                          class="form-control @error('mensaje') is-invalid @enderror">{{ old('mensaje') }}</textarea>
                @error('mensaje')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary">Registrar cliente</button>
            <a href="{{ route('prospectos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
