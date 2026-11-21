@extends('layouts.app')

@section('titulo', 'Propietarios - LIT Inmobiliaria')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Propietarios <span class="cu-etiqueta">(CU22)</span></h1>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Nuevo propietario</h2>
                <form method="POST" action="{{ route('propietarios.store') }}" novalidate>
                    @csrf
                    @include('reportesadministracion::CU22_GestionarPropietarios.campos', ['propietario' => null])
                    <button type="submit" class="btn btn-primary">Registrar propietario</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <form method="GET" action="{{ route('propietarios.index') }}" class="row g-2 mb-3">
            <div class="col-md-8">
                <input type="text" name="q" value="{{ $busqueda }}" class="form-control" placeholder="Buscar por nombre, correo o teléfono">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-outline-secondary w-100">Buscar</button>
            </div>
        </form>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Teléfono</th>
                            <th>Correo</th>
                            <th class="text-center">Propiedades</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($propietarios as $p)
                            <tr>
                                <td>{{ $p->nombre }}</td>
                                <td>{{ $p->telefono }}</td>
                                <td>{{ $p->email }}</td>
                                <td class="text-center">{{ $p->propiedades_count }}</td>
                                <td class="text-end">
                                    <a href="{{ route('propietarios.edit', $p) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                    @if (auth()->user()->tieneRol(\App\Models\Rol::ADMINISTRADOR))
                                        <form method="POST" action="{{ route('propietarios.destroy', $p) }}" class="d-inline"
                                              onsubmit="return confirm('¿Eliminar a este propietario?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No se encontraron propietarios.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">{{ $propietarios->links('pagination::bootstrap-5') }}</div>
    </div>
</div>
@endsection