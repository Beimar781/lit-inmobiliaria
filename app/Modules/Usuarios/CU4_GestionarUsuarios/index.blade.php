@extends('layouts.app')

@section('titulo', 'Usuarios - LIT Inmobiliaria')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Usuarios</h1>
    <a href="{{ route('usuarios.create') }}" class="btn btn-primary">Registrar usuario</a>
</div>

<form method="GET" action="{{ route('usuarios.index') }}" class="row g-2 mb-3">
    <div class="col-md-5">
        <input type="text" name="q" value="{{ $busqueda }}" class="form-control" placeholder="Buscar por nombre o correo">
    </div>
    <div class="col-md-3">
        <select name="idrol" class="form-select">
            <option value="">Todos los roles</option>
            @foreach ($roles as $id => $nombre)
                <option value="{{ $id }}" @selected((string) request('idrol') === (string) $id)>{{ $nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-outline-secondary w-100">Filtrar</button>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Teléfono</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($usuarios as $u)
                    <tr>
                        <td>{{ $u->nombre }}</td>
                        <td>{{ $u->email }}</td>
                        <td>{{ $u->telefono }}</td>
                        <td>{{ $u->rol->nombre }}</td>
                        <td>
                            <span class="badge {{ $u->estaActivo() ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $u->estado }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('usuarios.edit', $u) }}" class="btn btn-sm btn-outline-primary">Editar</a>

                            @if ($u->idusuario !== auth()->id())
                                <form method="POST" action="{{ route('usuarios.estado', $u) }}" class="d-inline"
                                      onsubmit="return confirm('¿Cambiar el estado de este usuario?')">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm {{ $u->estaActivo() ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                        {{ $u->estaActivo() ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No se encontraron usuarios.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $usuarios->links('pagination::bootstrap-5') }}</div>
@endsection
