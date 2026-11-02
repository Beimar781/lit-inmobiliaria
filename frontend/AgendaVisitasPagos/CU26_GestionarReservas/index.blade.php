@extends('layouts.app')

@section('titulo', 'Reservas - LIT Inmobiliaria')

@php($colores = ['VIGENTE' => 'success', 'CANCELADA' => 'secondary', 'CONCRETADA' => 'primary', 'VENCIDA' => 'warning'])
@php($formato = fn ($valor) => number_format((float) $valor, 2, '.', ','))

@section('contenido')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Reservas de propiedades</h1>
    @if ($puedeCobrar)
        <a href="{{ route('reservas.create') }}" class="btn btn-primary">Nueva reserva</a>
    @endif
</div>

<p class="text-muted small">
    La reserva se paga en efectivo después de una visita. Si el cliente no formaliza la compra, se le devuelve en efectivo lo pagado
    menos el {{ rtrim(rtrim(number_format($retencion, 2), '0'), '.') }} % que retiene la empresa. Si la formaliza, se descuenta del contrato.
</p>

<form method="GET" action="{{ route('reservas.index') }}" class="row g-2 mb-3">
    <div class="col-sm-6 col-md-3">
        <select name="estado" class="form-select" aria-label="Estado de la reserva">
            <option value="">Reservas activas</option>
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
                    <th>Plazo</th>
                    <th>Pagó</th>
                    <th>Estado</th>
                    @if ($esAdmin) <th>Agente</th> @endif
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reservas as $reserva)
                    @php($pendiente = $reserva->devolucionPendiente())
                    @php($calculo = $pendiente ? $dinero->calcularDevolucion($reserva) : null)
                    <tr>
                        <td>{{ $reserva->idreserva }}</td>
                        <td>{{ $reserva->propiedad->codigo }} &middot; {{ $reserva->propiedad->titulo }}</td>
                        <td>{{ $reserva->cliente->nombre }}</td>
                        <td>{{ $reserva->fechaplazo->format('d/m/Y') }}</td>
                        <td class="small">
                            @if ($reserva->cobro)
                                {{ $reserva->cobro->moneda }} {{ $formato($reserva->cobro->monto) }}
                                @if ($reserva->cobro->moneda !== 'USD') <div class="text-muted">= USD {{ $formato($reserva->cobro->montousd) }}</div> @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge text-bg-{{ $colores[$reserva->estado] ?? 'secondary' }}">{{ $reserva->estado_etiqueta }}</span>
                            @if ($pendiente)
                                <div class="small text-danger mt-1">Devolución pendiente: {{ $calculo['moneda'] }} {{ $formato($calculo['monto']) }}</div>
                            @elseif ($reserva->devolucion)
                                <div class="small text-muted mt-1">Devuelto: {{ $reserva->devolucion->moneda }} {{ $formato($reserva->devolucion->monto) }}</div>
                            @elseif ($reserva->estado === 'CONCRETADA' && $reserva->cobro)
                                <div class="small text-muted mt-1">Descontada del contrato</div>
                            @endif
                        </td>
                        @if ($esAdmin)
                            <td>{{ $reserva->propiedad->agente?->nombre ?? 'Sin asignar' }}</td>
                        @endif
                        <td class="text-end">
                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                @if ($reserva->estado === 'VIGENTE')
                                    @if ($puedeContratar)
                                        <a href="{{ route('contratos.create', ['reserva' => $reserva->idreserva]) }}" class="btn btn-sm btn-outline-primary">Crear contrato</a>
                                    @endif
                                    @if ($puedeCobrar)
                                        <form method="POST" action="{{ route('reservas.cancelar', $reserva) }}"
                                              onsubmit="return confirm('¿Cancelar esta reserva? La propiedad volverá a estar disponible y habrá que devolver el dinero al cliente.')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Cancelar reserva</button>
                                        </form>
                                    @endif
                                @endif
                                @if ($pendiente && $puedeCobrar)
                                    <form method="POST" action="{{ route('reservas.devolver', $reserva) }}"
                                          onsubmit="return confirm('¿Devolver {{ $calculo['moneda'] }} {{ $formato($calculo['monto']) }} en efectivo al cliente? La empresa retiene USD {{ $formato($calculo['retenido_usd']) }}.')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-warning">Registrar devolución</button>
                                    </form>
                                @endif
                                @if ($puedeCobrar && $reserva->cobro)
                                    <a href="{{ route('reservas.recibo', $reserva->cobro) }}" class="btn btn-sm btn-outline-secondary">Recibo</a>
                                @endif
                                @if ($puedeCobrar && $reserva->devolucion)
                                    <a href="{{ route('reservas.recibo', $reserva->devolucion) }}" class="btn btn-sm btn-outline-secondary">Recibo de devolución</a>
                                @endif
                            </div>
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
