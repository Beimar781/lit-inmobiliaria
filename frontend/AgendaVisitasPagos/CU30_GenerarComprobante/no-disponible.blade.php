@extends('layouts.app')

@section('titulo', 'Comprobante no disponible - LIT Inmobiliaria')

@section('contenido')
<div class="text-center py-5">
    <h1 class="h3 mb-2">Comprobante no disponible</h1>
    <p class="text-muted">{{ $motivo }} Solo los pagos registrados y no anulados tienen comprobante.</p>
    <a href="{{ $volver ?? route('pagos.create') }}" class="btn btn-primary">{{ $textoVolver ?? 'Registrar pago' }}</a>
</div>
@endsection
