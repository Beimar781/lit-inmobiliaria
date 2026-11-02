@extends('layouts.app')

@section('titulo', 'Reservas - LIT Inmobiliaria')

@php($colores = ['VIGENTE' => 'success', 'CANCELADA' => 'secondary', 'CONCRETADA' => 'primary', 'VENCIDA' => 'warning'])

@section('contenido')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Reservas de propiedades</h1>
    <a href="{{ route('reservas.create') }}" class="btn btn-primary">Nueva reserva</a>
</div>

<form method="GET" action="{{ route('reservas.index') }}" class="row g-2 mb-3">
    <div class="col-sm-6 col-md-3">
        <select name="estado" class="form-select" aria-label="Estado de la reserva">
            <option value="">Todos los estados</option>
            @foreach ($estados as $clave => $texto)
                <option value="{{ $clave }}" @selected($estado === $clave)>{{ $texto }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-3 col-md-2">
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
                    <th>Reservada el</th>
                    <th>Plazo</th>
                    <th>Estado</th>
                    @if ($esAdmin) <th>Agente</th> @endif
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reservas as $reserva)
                    <tr>
                        <td>{{ $reserva->idreserva }}</td>
                        <td>{{ $reserva->propiedad->codigo }} &middot; {{ $reserva->propiedad->titulo }}</td>
                        <td>{{ $reserva->cliente->nombre }}</td>
                        <td>{{ $reserva->fechareserva->format('d/m/Y') }}</td>
                        <td>{{ $reserva->fechaplazo->format('d/m/Y') }}</td>
                        <td><span class="badge text-bg-{{ $colores[$reserva->estado] ?? 'secondary' }}">{{ $reserva->estado_etiqueta }}</span></td>
                        @if ($esAdmin)
                            <td>{{ $reserva->propiedad->agente?->nombre ?? 'Sin asignar' }}</td>
                        @endif
                        <td class="text-end">
                            @if ($reserva->estado === 'VIGENTE')
                                <div class="d-flex flex-wrap gap-1 justify-content-end">
                                    @if ($puedeContratar)
                                        <a href="{{ route('contratos.create', ['reserva' => $reserva->idreserva]) }}" class="btn btn-sm btn-outline-primary">Crear contrato</a>
                                    @endif
                                    <form method="POST" action="{{ route('reservas.cancelar', $reserva) }}"
                                          onsubmit="return confirm('¿Cancelar esta reserva? La propiedad volverá a estar disponible.')">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Cancelar reserva</button>
                                    </form>
                                </div>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $esAdmin ? 8 : 7 }}" class="text-center text-muted py-4">No hay reservas para mostrar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $reservas->links('pagination::bootstrap-5') }}</div>
@endsection
