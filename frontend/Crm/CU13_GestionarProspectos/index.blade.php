@extends('layouts.app')

@section('titulo', 'Prospectos CRM - LIT Inmobiliaria')

@section('contenido')
@php($colores = ['NUEVO' => 'primary', 'CONTACTADO' => 'info', 'EN_NEGOCIACION' => 'warning', 'CERRADO' => 'success', 'DESCARTADO' => 'secondary'])

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Prospectos CRM</h1>
    @if (auth()->user()->tieneRol('Agente Inmobiliario'))
        <a href="{{ route('prospectos.create') }}" class="btn btn-primary">Registrar cliente interesado</a>
    @endif
</div>

<form method="GET" action="{{ route('prospectos.index') }}" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="q" value="{{ $busqueda }}" class="form-control" placeholder="Buscar por nombre, correo o teléfono">
    </div>
    <div class="col-md-3">
        <select name="estado" class="form-select">
            <option value="">Todos los estados</option>
            @foreach ($estados as $clave => $texto)
                <option value="{{ $clave }}" @selected(request('estado') === $clave)>{{ $texto }}</option>
            @endforeach
        </select>
    </div>
    @if ($esAdmin)
        <div class="col-md-3">
            <select name="agente" class="form-select">
                <option value="">Todos los agentes</option>
                <option value="sin" @selected(request('agente') === 'sin')>Sin asignar</option>
                @foreach ($agentes as $id => $nombre)
                    <option value="{{ $id }}" @selected(request('agente') == $id)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
    @endif
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
                    <th>Contacto</th>
                    <th>Tipo</th>
                    <th>Intereses</th>
                    <th>Seguimiento</th>
                    @if ($esAdmin) <th>Agente</th> @endif
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($prospectos as $c)
                    <tr>
                        <td>{{ $c->nombre }}</td>
                        <td class="small">
                            @if ($c->email) <div>{{ $c->email }}</div> @endif
                            @if ($c->telefono) <div class="text-muted">{{ $c->telefono }}</div> @endif
                        </td>
                        <td>{{ $c->tipocliente }}</td>
                        <td>{{ $c->intereses_count }}</td>
                        <td><span class="badge text-bg-{{ $colores[$c->estadoseguimiento] ?? 'secondary' }}">{{ $c->estado_etiqueta }}</span></td>
                        @if ($esAdmin)
                            <td>{!! $c->agente ? e($c->agente->nombre) : '<span class="text-muted">Sin asignar</span>' !!}</td>
                        @endif
                        <td class="text-end">
                            <a href="{{ route('prospectos.show', $c) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $esAdmin ? 7 : 6 }}" class="text-center text-muted py-4">No hay prospectos para mostrar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $prospectos->links('pagination::bootstrap-5') }}</div>
@endsection
