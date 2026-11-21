@extends('layouts.app')

@section('titulo', 'Categorías - LIT Inmobiliaria')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Categorías de propiedad <span class="cu-etiqueta">(CU23)</span></h1>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">{{ $categoria ? 'Editar categoría' : 'Nueva categoría' }}</h2>
                <form method="POST" action="{{ $categoria ? route('categorias.update', $categoria) : route('categorias.store') }}" novalidate>
                    @csrf
                    @if ($categoria) @method('PUT') @endif

                    <x-campo name="nombre" label="Nombre" :valor="$categoria?->nombre" maxlength="100" />

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción (opcional)</label>
                        <textarea id="descripcion" name="descripcion" rows="3" maxlength="500"
                                  class="form-control @error('descripcion') is-invalid @enderror">{{ old('descripcion', $categoria?->descripcion) }}</textarea>
                        @error('descripcion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary">{{ $categoria ? 'Guardar cambios' : 'Registrar categoría' }}</button>
                    @if ($categoria)
                        <a href="{{ route('categorias.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    @endif
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th class="text-center">Propiedades</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categorias as $c)
                            <tr>
                                <td>{{ $c->nombre }}</td>
                                <td>{{ $c->descripcion }}</td>
                                <td class="text-center">{{ $c->propiedades_count }}</td>
                                <td class="text-end">
                                    <a href="{{ route('categorias.edit', $c) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                    <form method="POST" action="{{ route('categorias.destroy', $c) }}" class="d-inline"
                                          onsubmit="return confirm('¿Eliminar esta categoría?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No hay categorías registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection