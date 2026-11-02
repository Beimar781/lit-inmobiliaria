@extends('layouts.app')

@section('titulo', 'Historial de pagos - LIT Inmobiliaria')

@php($colores = ['PAGADO' => 'success', 'PENDIENTE' => 'warning', 'ANULADO' => 'secondary'])
@php($formato = fn ($valor) => number_format((float) $valor, 2, '.', ','))

@section('contenido')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Historial de pagos</h1>
    <a href="{{ route('pagos.create') }}" class="btn btn-primary">Registrar pago</a>
</div>

@foreach ($errores as $error)
    <div class="alert alert-warning py-2">{{ $error }}</div>
@endforeach

<form method="GET" action="{{ route('pagos.index') }}" class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-6">
                <label for="contrato" class="form-label small mb-1">Contrato</label>
                <select id="contrato" name="contrato" class="form-select">
                    <option value="">Todos los contratos</option>
                    @foreach ($contratos as $id => $texto)
                        <option value="{{ $id }}" @selected($filtros['contrato'] === $id)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label for="cliente" class="form-label small mb-1">Cliente</label>
                <select id="cliente" name="cliente" class="form-select">
                    <option value="">Todos los clientes</option>
                    @foreach ($clientes as $id => $nombre)
                        <option value="{{ $id }}" @selected($filtros['cliente'] === $id)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-6 col-md-3">
                <label for="desde" class="form-label small mb-1">Desde</label>
                <input type="date" id="desde" name="desde" value="{{ $filtros['desde'] }}" class="form-control">
            </div>
            <div class="col-sm-6 col-md-3">
                <label for="hasta" class="form-label small mb-1">Hasta</label>
                <input type="date" id="hasta" name="hasta" value="{{ $filtros['hasta'] }}" class="form-control">
            </div>
            <div class="col-sm-6 col-md-3">
                <label for="estado" class="form-label small mb-1">Estado del pago</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="">Todos los estados</option>
                    @foreach ($estados as $clave => $texto)
                        <option value="{{ $clave }}" @selected($filtros['estado'] === $clave)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-6 col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-outline-primary flex-fill">Filtrar</button>
                <a href="{{ route('pagos.index') }}" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </div>
    </div>
</form>

<div class="alert alert-light border d-flex flex-wrap justify-content-between gap-2">
    <span>{{ $cantidad }} {{ $cantidad === 1 ? 'pago' : 'pagos' }} en el resultado</span>
    <strong>Total pagado: USD {{ $formato($totalUsd) }}</strong>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>N.º</th>
                    <th>Fecha</th>
                    <th>Contrato</th>
                    <th class="text-end">Monto</th>
                    <th>Método</th>
                    <th class="text-end">Equivale a (USD)</th>
                    <th>Comprobante</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pagos as $pago)
                    <tr>
                        <td>{{ $pago->idpago }}</td>
                        <td>{{ $pago->fechapago->format('d/m/Y') }}</td>
                        <td class="small">
                            N.º {{ $pago->idcontrato }} &middot; {{ $pago->contrato->cliente->nombre }}
                            <div class="text-muted">{{ $pago->contrato->propiedad->codigo }}</div>
                        </td>
                        <td class="text-end">
                            {{ $pago->moneda }} {{ $formato($pago->monto) }}
                            @if ($pago->pagoEnMonedaDelCliente())
                                <div class="text-muted small">Pagó {{ $pago->moneda_cliente === 'BOB' ? 'Bs' : $pago->moneda_cliente }} {{ $formato($pago->monto_cliente) }} (Stripe: {{ $pago->tipoCambioStripeTexto() }})</div>
                            @endif
                        </td>
                        <td>{{ $pago->metodo_etiqueta }}</td>
                        <td class="text-end">{{ $formato($pago->montousd) }}</td>
                        <td>{{ $pago->comprobante ?: '—' }}</td>
                        <td><span class="badge text-bg-{{ $colores[$pago->estado] ?? 'secondary' }}">{{ $pago->estado_etiqueta }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('pagos.show', $pago) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">Sin pagos registrados</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $pagos->links('pagination::bootstrap-5') }}</div>
@endsection
