@extends('layouts.app')

@section('titulo', 'Contratos - LIT Inmobiliaria')

@php($colores = ['VIGENTE' => 'success', 'FINALIZADO' => 'secondary', 'RESCINDIDO' => 'danger'])

@section('contenido')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Contratos <span class="cu-etiqueta">(CU27)</span></h1>
    <a href="{{ route('contratos.create') }}" class="btn btn-primary">Nuevo contrato</a>
</div>

<form method="GET" action="{{ route('contratos.index') }}" class="row g-2 mb-3">
    <div class="col-sm-5 col-md-3">
        <select name="estado" class="form-select" aria-label="Estado del contrato">
            <option value="">Todos los estados</option>
            @foreach ($estados as $clave => $texto)
                <option value="{{ $clave }}" @selected($estado === $clave)>{{ $texto }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-5 col-md-3">
        <select name="tipo" class="form-select" aria-label="Tipo de contrato">
            <option value="">Todos los tipos</option>
            @foreach ($tipos as $clave => $texto)
                <option value="{{ $clave }}" @selected($tipo === $clave)>{{ $texto }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-2">
        <button type="submit" class="btn btn-outline-secondary w-100">Filtrar</button>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>N.º</th>
                    <th>Propiedad</th>
                    <th>Cliente</th>
                    <th>Tipo</th>
                    <th class="text-end">Monto (USD)</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contratos as $contrato)
                    <tr>
                        <td>{{ $contrato->idcontrato }}</td>
                        <td>{{ $contrato->propiedad->codigo }} &middot; {{ $contrato->propiedad->titulo }}</td>
                        <td>{{ $contrato->cliente->nombre }}</td>
                        <td>{{ $contrato->tipocontrato }}</td>
                        <td class="text-end">{{ number_format($contrato->monto, 2, '.', ',') }}</td>
                        <td>{{ $contrato->fechainicio->format('d/m/Y') }}</td>
                        <td>{{ $contrato->fechafin?->format('d/m/Y') ?? '—' }}</td>
                        <td><span class="badge text-bg-{{ $colores[$contrato->estado] ?? 'secondary' }}">{{ $contrato->estado_etiqueta }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('contratos.show', $contrato) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No hay contratos para mostrar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $contratos->links('pagination::bootstrap-5') }}</div>
@endsection
