@extends('layouts.app')

@section('titulo', 'Reseña de la propiedad - LIT Inmobiliaria')

@section('contenido')
<a href="{{ route('portal.contratos') }}" class="text-decoration-none">&larr; Mis contratos y pagos</a>

<h1 class="h3 my-3">Reseña de la propiedad <span class="cu-etiqueta">(CU25)</span></h1>

<div class="card shadow-sm mb-3" style="max-width: 640px;">
    <div class="card-body">
        <span class="badge text-bg-dark me-2">{{ $propiedad->codigo }}</span>
        <strong>{{ $propiedad->titulo }}</strong>
    </div>
</div>

<div class="card shadow-sm" style="max-width: 640px;">
    <div class="card-body">
        @if ($resena)
            <h2 class="h5">Tu reseña</h2>
            <p class="fs-4 text-warning mb-1" aria-label="{{ $resena->puntuacion }} de 5">{{ str_repeat('★', $resena->puntuacion) }}<span class="text-muted">{{ str_repeat('★', 5 - $resena->puntuacion) }}</span></p>
            <p class="mb-1">{{ $resena->comentario ?: 'Sin comentario.' }}</p>
            <p class="small text-muted mb-0">Publicada el {{ $resena->fecha->format('d/m/Y') }}</p>
        @else
            <h2 class="h5">Califica esta propiedad</h2>
            <form method="POST" action="{{ route('resenas.guardar', $propiedad) }}" novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label d-block">Puntuación</label>
                    @foreach ([1, 2, 3, 4, 5] as $n)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input @error('puntuacion') is-invalid @enderror" type="radio" name="puntuacion"
                                   id="puntuacion{{ $n }}" value="{{ $n }}" @checked((string) old('puntuacion') === (string) $n)>
                            <label class="form-check-label" for="puntuacion{{ $n }}">{{ $n }} <span class="text-warning">★</span></label>
                        </div>
                    @endforeach
                    @error('puntuacion')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="comentario" class="form-label">Comentario (opcional)</label>
                    <textarea id="comentario" name="comentario" rows="4" maxlength="1000"
                              class="form-control @error('comentario') is-invalid @enderror">{{ old('comentario') }}</textarea>
                    @error('comentario')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">Publicar reseña</button>
                <a href="{{ route('portal.contratos') }}" class="btn btn-outline-secondary">Cancelar</a>
            </form>
        @endif
    </div>
</div>
@endsection