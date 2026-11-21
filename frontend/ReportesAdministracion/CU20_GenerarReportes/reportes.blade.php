@extends('layouts.app')

@section('titulo', 'Reportes - LIT Inmobiliaria')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Reportes <span class="cu-etiqueta">(CU20)</span></h1>
</div>

<form method="GET" action="{{ route('reportes.generar') }}" class="card card-body shadow-sm mb-4" id="form-reporte">
    <div class="row g-3">
        <div class="col-md-3">
            <label for="tipo" class="form-label">Tipo de reporte</label>
            <select name="tipo" id="tipo" class="form-select">
                <option value="">— Elige el tipo —</option>
                @foreach ($tipos as $clave => $nombre)
                    <option value="{{ $clave }}" @selected(($criterios?->tipo) === $clave)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label for="desde" class="form-label">Desde</label>
            <input type="date" name="desde" id="desde" value="{{ $criterios?->desde?->format('Y-m-d') }}" class="form-control">
        </div>
        <div class="col-md-2">
            <label for="hasta" class="form-label">Hasta</label>
            <input type="date" name="hasta" id="hasta" value="{{ $criterios?->hasta?->format('Y-m-d') }}" class="form-control">
        </div>
        <div class="col-md-2">
            <label for="estado" class="form-label">Estado</label>
            <select name="estado" id="estado" class="form-select">
                <option value="">Todos</option>
                @foreach ($estadosPorTipo as $tipo => $estados)
                    @foreach ($estados as $clave => $nombre)
                        <option value="{{ $clave }}" data-tipo="{{ $tipo }}" @selected(($criterios?->estado) === $clave && $criterios?->tipo === $tipo)>{{ $nombre }}</option>
                    @endforeach
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label for="agrupacion" class="form-label">Agrupar</label>
            <select name="agrupacion" id="agrupacion" class="form-select">
                @foreach ($agrupaciones as $clave => $nombre)
                    <option value="{{ $clave }}" @if ($clave === 'categoria') data-solo="propiedades" @endif @selected(($criterios?->agrupacion ?? 'ninguna') === $clave)>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        @if ($esAdmin)
            <div class="col-md-3">
                <label for="idagente" class="form-label">Agente</label>
                <select name="idagente" id="idagente" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($agentes as $id => $nombre)
                        <option value="{{ $id }}" @selected(($criterios?->idagente) === $id)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="col-md-3" id="bloque-zona">
            <label for="zona" class="form-label">Zona (propiedades y visitas)</label>
            <select name="zona" id="zona" class="form-select">
                <option value="">Todas</option>
                @foreach ($zonas as $zona)
                    <option value="{{ $zona }}" @selected(($criterios?->zona) === $zona)>{{ $zona }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 align-self-end">
            <button type="submit" class="btn btn-primary">Generar reporte</button>
            <a href="{{ route('reportes.index') }}" class="btn btn-outline-secondary">Limpiar</a>
        </div>
    </div>
</form>

@if ($error)
    <div class="alert alert-danger">{{ $error }}</div>
@endif

@if ($resultado)
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">{{ $resultado->titulo }}</h2>
                    <p class="small text-muted mb-0">{{ implode(' · ', $filtros) }}</p>
                </div>
                @unless ($resultado->estaVacio())
                    <div class="d-flex gap-2">
                        <a href="{{ route('reportes.exportar', ['formato' => 'pdf'] + $parametros) }}" class="btn btn-outline-danger btn-sm">Exportar PDF</a>
                        <a href="{{ route('reportes.exportar', ['formato' => 'xlsx'] + $parametros) }}" class="btn btn-outline-success btn-sm">Exportar Excel</a>
                    </div>
                @endunless
            </div>

            @if ($resultado->estaVacio())
                <p class="text-muted mb-0">Sin resultados para el reporte</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                @foreach ($resultado->columnas as $i => $columna)
                                    <th class="{{ in_array($i, $resultado->columnasNumericas, true) ? 'text-end' : '' }}">{{ $columna }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($resultado->filas as $fila)
                                <tr>
                                    @foreach ($fila as $i => $valor)
                                        <td class="{{ in_array($i, $resultado->columnasNumericas, true) ? 'text-end' : '' }}">{{ $resultado->formatear($i, $valor) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="{{ max(1, count($resultado->columnas) - 1) }}">Total de filas</td>
                                <td class="text-end">{{ $resultado->totales['cantidad'] }}</td>
                            </tr>
                            @if ($resultado->totales['suma'] !== null)
                                <tr>
                                    <td colspan="{{ max(1, count($resultado->columnas) - 1) }}">{{ $resultado->totales['etiquetaSuma'] }}</td>
                                    <td class="text-end">{{ number_format($resultado->totales['suma'], 2, '.', ',') }}</td>
                                </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endif
@endsection

@push('scripts')
    <script>
        (function () {
            var tipo = document.getElementById('tipo');
            var estado = document.getElementById('estado');
            var agrupacion = document.getElementById('agrupacion');
            var zona = document.getElementById('bloque-zona');

            function filtrar(select, atributo, valor) {
                Array.prototype.forEach.call(select.options, function (o) {
                    var propio = o.getAttribute(atributo);
                    var oculto = propio !== null && propio !== valor;
                    o.hidden = oculto;
                    o.disabled = oculto;
                    if (oculto && o.selected) { select.value = ''; }
                });
            }

            function actualizar() {
                filtrar(estado, 'data-tipo', tipo.value);
                filtrar(agrupacion, 'data-solo', tipo.value);
                if (agrupacion.value === '') { agrupacion.value = 'ninguna'; }
                zona.style.display = (tipo.value === '' || tipo.value === 'propiedades' || tipo.value === 'visitas') ? '' : 'none';
            }

            tipo.addEventListener('change', actualizar);
            actualizar();
        })();
    </script>
@endpush