@extends('layouts.app')

@section('titulo', 'Mis contratos y pagos - LIT Inmobiliaria')

@php($colores = ['VIGENTE' => 'success', 'FINALIZADO' => 'secondary', 'RESCINDIDO' => 'danger'])
@php($formato = fn ($valor) => number_format((float) $valor, 2, '.', ','))

@section('contenido')
<h1 class="h3 mb-1">Mis contratos y pagos</h1>
<p class="text-muted">Aquí ves tus contratos, lo que has pagado y lo que falta, y puedes pagar con tarjeta.</p>

@forelse ($contratos as $contrato)
    @php($pagado = $contrato->totalPagadoUsd())
    @php($saldo = $contrato->saldoUsd())
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <p class="text-muted small mb-1">Contrato N.º {{ $contrato->idcontrato }} &middot; {{ $contrato->tipocontrato }}</p>
                    <h2 class="h5 mb-1">{{ $contrato->propiedad->titulo }}</h2>
                    <p class="text-muted small mb-0">{{ $contrato->propiedad->codigo }}</p>
                </div>
                <span class="badge text-bg-{{ $colores[$contrato->estado] ?? 'secondary' }}">{{ $contrato->estado_etiqueta }}</span>
            </div>

            <div class="row text-center mt-3 g-2">
                <div class="col-4"><div class="small text-muted">Monto</div><div class="fw-semibold">USD {{ $formato($contrato->monto) }}</div></div>
                <div class="col-4"><div class="small text-muted">Pagado</div><div class="fw-semibold">USD {{ $formato($pagado) }}</div></div>
                <div class="col-4"><div class="small text-muted">Saldo</div><div class="fw-semibold">USD {{ $formato($saldo) }}</div></div>
            </div>

            <div class="mt-3 text-end">
                <a href="{{ route('portal.contrato', $contrato) }}" class="btn btn-primary btn-sm">Ver detalle y pagos</a>
            </div>
        </div>
    </div>
@empty
    <div class="alert alert-light border">
        @if ($cliente)
            Todavía no tienes contratos registrados.
        @else
            Tu cuenta todavía no está asociada a un cliente. Comunícate con la inmobiliaria.
        @endif
    </div>
@endforelse
@endsection
