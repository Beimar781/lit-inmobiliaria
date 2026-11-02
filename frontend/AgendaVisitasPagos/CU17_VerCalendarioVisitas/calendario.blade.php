@extends('layouts.app')

@section('titulo', 'Calendario de visitas - LIT Inmobiliaria')

@php($colores = ['PROGRAMADA' => 'primary', 'REAGENDADA' => 'warning', 'CANCELADA' => 'secondary', 'REALIZADA' => 'success'])

@push('estilos')
<style>
    /* Calendario hecho solo con CSS: 7 columnas en pantallas grandes; en celular, una lista con los días que tienen visitas */
    .cal-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 1px; background: #dee2e6; border: 1px solid #dee2e6; }
    .cal-cab { background: #f8f9fa; text-align: center; font-weight: 600; font-size: .85rem; padding: .35rem 0; }
    .cal-dia { background: #fff; min-height: 120px; padding: .3rem; min-width: 0; }
    .cal-semana .cal-dia { min-height: 260px; }
    .cal-dia.fuera { background: #f8f9fa; color: #adb5bd; }
    .cal-dia.hoy { box-shadow: inset 0 0 0 2px #0d6efd; }
    .cal-num { font-size: .85rem; font-weight: 600; }
    .cal-nombre { display: none; }
    .cal-visita { display: block; font-size: .75rem; line-height: 1.25; padding: .2rem .3rem; margin-top: .2rem; border-left: 3px solid; border-radius: 3px; background: #f1f5ff; color: #212529; text-decoration: none; overflow: hidden; }
    .cal-visita:hover { background: #e2ebff; }
    .cal-visita.estado-PROGRAMADA { border-color: #0d6efd; }
    .cal-visita.estado-REAGENDADA { border-color: #ffc107; background: #fff9e6; }
    .cal-visita.estado-CANCELADA { border-color: #6c757d; background: #f1f3f5; text-decoration: line-through; color: #6c757d; }
    .cal-visita.estado-REALIZADA { border-color: #198754; background: #eaf7ef; }
    @media (max-width: 767.98px) {
        .cal-grid { display: block; background: none; border: 0; }
        .cal-cab { display: none; }
        .cal-dia { min-height: 0; margin-bottom: .5rem; border: 1px solid #dee2e6; border-radius: .375rem; }
        .cal-semana .cal-dia { min-height: 0; }
        .cal-dia.sin-visitas { display: none; }
        .cal-nombre { display: inline; }
        .cal-visita { font-size: .85rem; }
    }
</style>
@endpush

@section('contenido')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Calendario de visitas</h1>
    <div class="btn-group" role="group" aria-label="Vista del calendario">
        <a href="{{ $enlaceMes }}" class="btn btn-sm {{ $vista === 'mes' ? 'btn-primary' : 'btn-outline-primary' }}">Mes</a>
        <a href="{{ $enlaceSemana }}" class="btn btn-sm {{ $vista === 'semana' ? 'btn-primary' : 'btn-outline-primary' }}">Semana</a>
    </div>
</div>

@foreach ($errores as $error)
    <div class="alert alert-warning py-2">{{ $error }}</div>
@endforeach

{{-- Filtros: estado y, para el administrador, agente --}}
<form method="GET" action="{{ route('visitas.calendario') }}" class="row g-2 mb-3">
    @foreach ($filtros as $nombre => $valor)
        <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
    @endforeach
    <div class="col-sm-5 col-md-3">
        <select name="estado" class="form-select" aria-label="Estado de la visita">
            <option value="">Todos los estados</option>
            @foreach ($estados as $clave => $texto)
                <option value="{{ $clave }}" @selected($estado === $clave)>{{ $texto }}</option>
            @endforeach
        </select>
    </div>
    @if ($esAdmin)
        <div class="col-sm-5 col-md-3">
            <select name="agente" class="form-select" aria-label="Agente">
                <option value="">Todos los agentes</option>
                @foreach ($agentes as $id => $nombre)
                    <option value="{{ $id }}" @selected($idAgente === $id)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="col-sm-2 col-md-2">
        <button type="submit" class="btn btn-outline-secondary w-100">Filtrar</button>
    </div>
</form>

{{-- Navegación del período --}}
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <div class="btn-group">
        <a href="{{ $enlaceAnterior }}" class="btn btn-outline-secondary btn-sm" aria-label="{{ $vista === 'mes' ? 'Mes anterior' : 'Semana anterior' }}">&larr; {{ $vista === 'mes' ? 'Mes anterior' : 'Semana anterior' }}</a>
        <a href="{{ $enlaceHoy }}" class="btn btn-outline-secondary btn-sm">Hoy</a>
        <a href="{{ $enlaceSiguiente }}" class="btn btn-outline-secondary btn-sm" aria-label="{{ $vista === 'mes' ? 'Mes siguiente' : 'Semana siguiente' }}">{{ $vista === 'mes' ? 'Mes siguiente' : 'Semana siguiente' }} &rarr;</a>
    </div>
    <h2 class="h5 mb-0">{{ $titulo }}</h2>
</div>

@if ($totalPeriodo === 0)
    <div class="alert alert-light border text-center">Sin visitas programadas</div>
@endif

<div @class(['cal-grid', 'cal-semana' => $vista === 'semana'])>
    @foreach ($dias as $nombreDia)
        <div class="cal-cab">{{ mb_substr($nombreDia, 0, 3) }}</div>
    @endforeach

    @foreach ($semanas as $semana)
        @foreach ($semana as $dia)
            @php($visitasDia = $porDia->get($dia->toDateString(), collect()))
            @php($fueraDelPeriodo = $dia->lt($inicioPeriodo) || $dia->gt($finPeriodo))
            <div @class(['cal-dia', 'fuera' => $fueraDelPeriodo, 'hoy' => $dia->isSameDay($hoy), 'sin-visitas' => $visitasDia->isEmpty()])>
                <div class="cal-num">
                    <span class="cal-nombre">{{ $dias[$dia->dayOfWeekIso] }}</span>
                    {{ $dia->day }}
                </div>
                @foreach ($visitasDia as $visita)
                    <a href="{{ route('visitas.show', $visita) }}" class="cal-visita estado-{{ $visita->estado }}"
                       title="{{ $visita->estado_etiqueta }}">
                        <strong>{{ $visita->hora_corta }}</strong>
                        {{ $visita->propiedad->codigo }} &middot; {{ $visita->propiedad->titulo }}<br>
                        {{ $visita->cliente->nombre }}
                        <span class="badge text-bg-{{ $colores[$visita->estado] ?? 'secondary' }}">{{ $visita->estado_etiqueta }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    @endforeach
</div>
@endsection
