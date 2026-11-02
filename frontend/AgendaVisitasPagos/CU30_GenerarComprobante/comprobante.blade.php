@extends('layouts.app')

@section('titulo', 'Comprobante ' . $pago->comprobante . ' - LIT Inmobiliaria')

@php($formato = fn ($valor) => number_format((float) $valor, 2, '.', ','))

@push('estilos')
<style>
    @media print {
        nav.navbar, .no-imprimir, .alert { display: none !important; }
        body { background: #fff !important; }
        main.container { max-width: 100% !important; padding: 0 !important; }
        .comprobante { box-shadow: none !important; border: 1px solid #000 !important; }
    }
</style>
@endpush

@section('contenido')
<div class="no-imprimir mb-3 d-flex flex-wrap gap-2">
    <a href="{{ $volver ?? route('pagos.create', ['contrato' => $contrato->idcontrato]) }}" class="btn btn-outline-secondary">&larr; {{ $textoVolver ?? 'Volver al contrato' }}</a>
    <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir o guardar como PDF</button>
</div>

<div class="card shadow-sm comprobante mx-auto" style="max-width: 680px">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h1 class="h4 mb-0">LIT Inmobiliaria</h1>
                <p class="text-muted mb-0">Comprobante de pago</p>
            </div>
            <div class="text-end">
                <div class="fw-bold fs-5">{{ $pago->comprobante }}</div>
                <div class="small text-muted">{{ $pago->fechapago->format('d/m/Y H:i') }}</div>
            </div>
        </div>

        <dl class="row mb-3">
            <dt class="col-sm-4">Cliente</dt>
            <dd class="col-sm-8">{{ $contrato->cliente->nombre }}</dd>
            <dt class="col-sm-4">Propiedad</dt>
            <dd class="col-sm-8">{{ $contrato->propiedad->codigo }} &middot; {{ $contrato->propiedad->titulo }}</dd>
            <dt class="col-sm-4">Contrato</dt>
            <dd class="col-sm-8">N.º {{ $contrato->idcontrato }} &middot; {{ $contrato->tipocontrato }} &middot; USD {{ $formato($contrato->monto) }}</dd>
            <dt class="col-sm-4">Método de pago</dt>
            <dd class="col-sm-8">{{ $pago->metodo_etiqueta }}</dd>
            <dt class="col-sm-4">Monto recibido</dt>
            <dd class="col-sm-8 fw-bold">{{ $pago->moneda }} {{ $formato($pago->monto) }}</dd>
            @if ($pago->moneda !== 'USD')
                <dt class="col-sm-4">Tipo de cambio</dt>
                <dd class="col-sm-8">{{ rtrim(rtrim(number_format((float) ($pago->tipopago?->cambiodemonedalocal ?? $pago->tipocambio), 4, '.', ''), '0'), '.') }} Bs por USD</dd>
                <dt class="col-sm-4">Equivalente</dt>
                <dd class="col-sm-8">USD {{ $formato($pago->montousd) }}</dd>
            @endif
            <dt class="col-sm-4">Saldo restante</dt>
            <dd class="col-sm-8">USD {{ $formato($saldoRestante) }}</dd>
            <dt class="col-sm-4">Atendió</dt>
            <dd class="col-sm-8">{{ $pago->usuario?->nombre ?? '—' }}</dd>
        </dl>

        <div class="row text-center mt-5 pt-3 small">
            <div class="col-6"><div class="border-top pt-1">Firma del cliente</div></div>
            <div class="col-6"><div class="border-top pt-1">Firma de la inmobiliaria</div></div>
        </div>
    </div>
</div>
@endsection
