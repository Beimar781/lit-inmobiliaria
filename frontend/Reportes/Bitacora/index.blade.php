@extends('layouts.app')

@section('titulo', 'Bitácora - LIT Inmobiliaria')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-0">Bitácora del sistema</h1>
        <p class="text-muted small mb-0">Registro de la actividad de todos los usuarios.</p>
    </div>
</div>

<form method="GET" action="{{ route('bitacora.index') }}" class="card card-body shadow-sm mb-3">
    <div class="row g-2">
        <div class="col-md-3">
            <label class="form-label small mb-1">Usuario</label>
            <select name="idusuario" class="form-select form-select-sm">
                <option value="">Todos</option>
                @foreach ($usuarios as $id => $nombre)
                    <option value="{{ $id }}" @selected((string) request('idusuario') === (string) $id)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Módulo</label>
            <select name="modulo" class="form-select form-select-sm">
                <option value="">Todos</option>
                @foreach ($modulos as $modulo)
                    <option value="{{ $modulo }}" @selected(request('modulo') === $modulo)>{{ $modulo }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">Acción</label>
            <select name="accion" class="form-select form-select-sm">
                <option value="">Todas</option>
                @foreach ($acciones as $clave => $etiqueta)
                    <option value="{{ $clave }}" @selected(request('accion') === $clave)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Desde</label>
            <input type="date" name="desde" value="{{ request('desde') }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Hasta</label>
            <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-8">
            <input type="text" name="q" value="{{ $busqueda }}" class="form-control form-control-sm"
                   placeholder="Buscar en la descripción o por IP">
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary flex-fill">Filtrar</button>
            <a href="{{ route('bitacora.index') }}" class="btn btn-sm btn-outline-secondary flex-fill">Limpiar</a>
        </div>
    </div>
</form>

@php
    $color = function (string $accion): string {
        return match (true) {
            str_contains($accion, 'FALLIDO') => 'danger',
            str_contains($accion, 'BAJA'), str_contains($accion, 'DESACTIVADO') => 'warning',
            str_contains($accion, 'REGISTRADA'), str_contains($accion, 'CREADO'), str_contains($accion, 'ACTIVADO'), $accion === 'INICIO_SESION' => 'success',
            $accion === 'CIERRE_SESION' => 'secondary',
            default => 'primary',
        };
    };
@endphp

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="white-space: nowrap">Fecha y hora</th>
                    <th>Usuario</th>
                    <th>Módulo</th>
                    <th>Acción</th>
                    <th>Descripción</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($registros as $r)
                    <tr>
                        <td style="white-space: nowrap">{{ $r->fecha->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $r->usuario?->nombre ?? 'Visitante / sistema' }}</td>
                        <td>{{ $r->modulo }}</td>
                        <td>
                            <span class="badge text-bg-{{ $color($r->accion) }}">
                                {{ $acciones[$r->accion] ?? $r->accion }}
                            </span>
                        </td>
                        <td>
                            {{ $r->descripcion }}
                            @if ($r->detalle)
                                <details class="mt-1">
                                    <summary class="small text-primary" style="cursor: pointer">Ver detalle</summary>
                                    <pre class="small bg-light border rounded p-2 mb-0 mt-1" style="white-space: pre-wrap">{{ json_encode($r->detalle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $r->ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay registros con esos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $registros->links('pagination::bootstrap-5') }}</div>
@endsection
