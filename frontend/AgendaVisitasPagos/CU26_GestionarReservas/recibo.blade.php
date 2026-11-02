@extends('layouts.app')

@section('titulo', 'Recibo ' . $movimiento->comprobante . ' - LIT Inmobiliaria')

@php($formato = fn ($valor) => number_format((float) $valor, 2, '.', ','))
@php($esCobro = $movimiento->tipo === 'COBRO')

@push('estilos')
<style>
    @media print {
        nav.navbar, .no-imprimir, .alert { display: none !important; }
        body { background: #fff !important; }
        .recibo { box-shadow: none !important; border: 1px solid #000 !important; }
    }
</style>
@endpush

@section('contenido')
<div class="no-imprimir mb-3 d-flex flex-wrap gap-2">
    <a href="{{ route('reservas.index') }}" class="btn btn-outline-secondary">&larr; Volver a reservas</a>
    <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir o guardar como PDF</button>
</div>

<div class="card shadow-sm recibo mx-auto" style="max-width: 640px">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h1 class="h4 mb-0">LIT Inmobiliaria</h1>
                <p class="text-muted mb-0">{{ $esCobro ? 'Recibo de reserva' : 'Recibo de devolución de reserva' }}</p>
            </div>
            <div class="text-end">
                <div class="fw-bold fs-5">{{ $movimiento->comprobante }}</div>
                <div class="small text-muted">{{ $movimiento->fecha->format('d/m/Y H:i') }}</div>
            </div>
        </div>

        <dl class="row mb-3">
            <dt class="col-sm-4">Cliente</dt>
            <dd class="col-sm-8">{{ $reserva->cliente->nombre }}</dd>
            <dt class="col-sm-4">Propiedad</dt>
            <dd class="col-sm-8">{{ $reserva->propiedad->codigo }} &middot; {{ $reserva->propiedad->titulo }}</dd>
            <dt class="col-sm-4">Reserva</dt>
            <dd class="col-sm-8">N.º {{ $reserva->idreserva }} &middot; plazo hasta el {{ $reserva->fechaplazo->format('d/m/Y') }}</dd>
            <dt class="col-sm-4">Forma de pago</dt>
            <dd class="col-sm-8">Efectivo</dd>
            <dt class="col-sm-4">{{ $esCobro ? 'Monto recibido' : 'Monto devuelto' }}</dt>
            <dd class="col-sm-8 fw-bold">{{ $movimiento->moneda }} {{ $formato($movimiento->monto) }}</dd>
            @if ($movimiento->moneda !== 'USD')
                <dt class="col-sm-4">Tipo de cambio</dt>
                <dd class="col-sm-8">{{ rtrim(rtrim(number_format((float) $movimiento->tipocambio, 4, '.', ''), '0'), '.') }} Bs por USD</dd>
                <dt class="col-sm-4">Equivalente</dt>
                <dd class="col-sm-8">USD {{ $formato($movimiento->montousd) }}</dd>
            @endif
            @if (! $esCobro)
                <dt class="col-sm-4">Retenido por la empresa</dt>
                <dd class="col-sm-8">USD {{ $formato($retenidoUsd) }} ({{ rtrim(rtrim(number_format((float) $movimiento->porcentajeretencion, 2), '0'), '.') }} %)</dd>
                <dt class="col-sm-4">Motivo</dt>
                <dd class="col-sm-8">El cliente no formalizó la compra: la reserva {{ $reserva->estado === 'VENCIDA' ? 'venció' : 'se canceló' }}.</dd>
            @endif
            <dt class="col-sm-4">Atendió</dt>
            <dd class="col-sm-8">{{ $movimiento->usuario?->nombre ?? '—' }}</dd>
        </dl>

        @if ($esCobro)
            <p class="small text-muted mb-4">
                Si el cliente no formaliza la compra antes del plazo, se le devolverá en efectivo este monto menos el
                {{ rtrim(rtrim(number_format((float) config('reservas.retencion'), 2), '0'), '.') }} % que retiene la empresa. Si la formaliza, este monto se toma como el primer pago del contrato.
            </p>
        @endif

        <div class="row text-center mt-5 pt-3 small">
            <div class="col-6"><div class="border-top pt-1">Firma del cliente</div></div>
            <div class="col-6"><div class="border-top pt-1">Firma de la inmobiliaria</div></div>
        </div>
    </div>
</div>
@endsection
