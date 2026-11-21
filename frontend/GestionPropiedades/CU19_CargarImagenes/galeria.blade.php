@extends('layouts.app')

@section('titulo', 'Imágenes de la propiedad - LIT Inmobiliaria')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Imágenes de la propiedad <span class="cu-etiqueta">(CU19)</span></h1>
    <a href="{{ route('propiedades.index') }}" class="btn btn-outline-secondary">Volver al inventario</a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <span class="badge text-bg-dark me-2">{{ $propiedad->codigo }}</span>
            <strong>{{ $propiedad->titulo }}</strong>
        </div>
        <span class="badge text-bg-primary fs-6">{{ $imagenes->count() }} de {{ $maximo }} imágenes</span>
    </div>
</div>

<div class="row g-3 mb-4">
    @forelse ($imagenes as $imagen)
        <div class="col-6 col-md-3">
            <div class="card h-100 shadow-sm {{ $imagen->portada ? 'border-primary' : '' }}">
                <img src="{{ asset($imagen->ruta) }}" alt="{{ $imagen->nombre }}" class="card-img-top" style="height: 150px; object-fit: cover;">
                <div class="card-body p-2 text-center">
                    @if ($imagen->portada)
                        <span class="badge text-bg-primary mb-2">Portada</span>
                    @else
                        <form method="POST" action="{{ route('propiedades.imagenes.portada', [$propiedad, $imagen]) }}" class="mb-2">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-sm btn-outline-primary">Usar como portada</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('propiedades.imagenes.eliminar', [$propiedad, $imagen]) }}"
                          onsubmit="return confirm('¿Eliminar esta imagen?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><p class="text-muted">Esta propiedad todavía no tiene imágenes.</p></div>
    @endforelse
</div>

<div class="card shadow-sm" style="max-width: 640px;">
    <div class="card-body">
        <h2 class="h5">Subir imágenes</h2>
        <form method="POST" action="{{ route('propiedades.imagenes.subir', $propiedad) }}" enctype="multipart/form-data" novalidate>
            @csrf
            <div class="mb-3">
                <input type="file" id="imagenes" name="imagenes[]" multiple accept=".jpg,.jpeg,.png,.webp"
                       class="form-control @if ($errors->has('imagenes') || $errors->has('imagenes.*')) is-invalid @endif">
                <div class="form-text">JPG, PNG o WebP, hasta 4 MB cada una. Máximo {{ $maximo }} imágenes por propiedad.</div>
                @error('imagenes')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                @foreach (collect($errors->get('imagenes.*'))->flatten()->unique() as $mensaje)
                    <div class="invalid-feedback d-block">{{ $mensaje }}</div>
                @endforeach
            </div>
            <button type="submit" class="btn btn-primary" @disabled($imagenes->count() >= $maximo)>Subir imágenes</button>
        </form>
    </div>
</div>
@endsection