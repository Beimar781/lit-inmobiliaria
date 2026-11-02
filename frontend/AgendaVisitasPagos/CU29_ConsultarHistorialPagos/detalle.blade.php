@extends('layouts.app')

@section('titulo', 'Pago ' . $pago->idpago . ' - LIT Inmobiliaria')

@php($colores = ['PAGADO' => 'success', 'PENDIENTE' => 'warning', 'ANULADO' => 'secondary'])
@php($formato = fn ($valor) => number_format((float) $valor, 2, '.', ','))

@section('contenido')
<a href="{{ route('pagos.index', ['contrato' => $pago->idcontrato]) }}" class="text-decoration-none">&larr; Volver al historial</a>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 my-3">
    <div>
        <h1 class="h3 mb-1">Pago N.º {{ $pago->idpago }}</h1>
        <p class="text-muted mb-0">Contrato N.º {{ $contrato->idcontrato }} &middot; {{ $contrato->cliente->nombre }}</p>
    </div>
    <span class="badge text-bg-{{ $colores[$pago->estado] ?? 'secondary' }} fs-6">{{ $pago->estado_etiqueta }}</span>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Fecha</dt>
                    <dd class="col-sm-8">{{ $pago->fechapago->format('d/m/Y H:i') }}</dd>
                    <dt class="col-sm-4">Cliente</dt>
                    <dd class="col-sm-8">{{ $contrato->cliente->nombre }}</dd>
                    <dt class="col-sm-4">Propiedad</dt>
                    <dd class="col-sm-8">{{ $contrato->propiedad->codigo }} &middot; {{ $contrato->propiedad->titulo }}</dd>
                    <dt class="col-sm-4">Contrato</dt>
                    <dd class="col-sm-8">N.º {{ $contrato->idcontrato }} &middot; {{ $contrato->tipocontrato }} &middot; USD {{ $formato($contrato->monto) }}</dd>
                    <dt class="col-sm-4">Monto</dt>
                    <dd class="col-sm-8 fw-bold">{{ $pago->moneda }} {{ $formato($pago->monto) }}</dd>
                    @if ($pago->moneda !== 'USD')
                        <dt class="col-sm-4">Tipo de cambio</dt>
                        <dd class="col-sm-8">
                            {{ rtrim(rtrim(number_format((float) ($pago->tipopago?->cambiodemonedalocal ?? $pago->tipocambio), 4, '.', ''), '0'), '.') }} Bs por USD
                            @if ($pago->tipopago?->fecha) <span class="text-muted small">(del {{ $pago->tipopago->fecha->format('d/m/Y') }})</span> @endif
                        </dd>
                    @endif
                    <dt class="col-sm-4">Equivalente en dólares</dt>
                    <dd class="col-sm-8">USD {{ $formato($pago->montousd) }}</dd>
                    <dt class="col-sm-4">Método de pago</dt>
                    <dd class="col-sm-8">{{ $pago->metodo_etiqueta }}</dd>
                    <dt class="col-sm-4">Registró</dt>
                    <dd class="col-sm-8">{{ $pago->usuario?->nombre ?? '—' }}</dd>
                    <dt class="col-sm-4">Comprobante</dt>
                    <dd class="col-sm-8 mb-0">{{ $pago->comprobante ?: 'Sin comprobante' }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Comprobante</h2>
                @if ($conComprobante)
                    <p class="small text-muted">Se vuelve a emitir con el mismo número.</p>
                    <a href="{{ route('pagos.comprobante', $pago) }}" class="btn btn-primary w-100">Ver comprobante</a>
                @else
                    <p class="text-muted mb-0">Un pago {{ strtolower($pago->estado_etiqueta) }} no tiene comprobante.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
