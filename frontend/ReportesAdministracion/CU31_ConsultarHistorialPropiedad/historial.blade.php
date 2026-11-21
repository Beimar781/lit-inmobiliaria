@extends('layouts.app')

@section('titulo', 'Historial de la propiedad - LIT Inmobiliaria')

@php
    $etiquetasCampo = [
        'titulo' => 'Título', 'descripcion' => 'Descripción', 'precio' => 'Precio', 'tipo' => 'Tipo de operación',
        'estado' => 'Estado', 'superficie' => 'Superficie', 'areaconstruida' => 'Área construida',
        'habitaciones' => 'Habitaciones', 'banos' => 'Baños', 'antiguedad' => 'Antigüedad', 'sector' => 'Sector',
        'contacto' => 'Contacto', 'idpropietario' => 'Propietario (id)', 'idcategoria' => 'Categoría (id)',
        'zona' => 'Zona', 'direccion' => 'Dirección', 'latitud' => 'Latitud', 'longitud' => 'Longitud',
    ];
    $controlador = \App\Modules\ReportesAdministracion\CU31_ConsultarHistorialPropiedad\HistorialPropiedadController::class;
@endphp

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Historial de la propiedad <span class="cu-etiqueta">(CU31)</span></h1>
    <a href="{{ route('propiedades.index') }}" class="btn btn-outline-secondary">Volver al inventario</a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <span class="badge text-bg-dark me-2">{{ $propiedad->codigo }}</span>
        <strong>{{ $propiedad->titulo }}</strong>
        <span class="badge text-bg-secondary ms-2">{{ ucfirst(strtolower($propiedad->estadopropiedad)) }}</span>
    </div>
</div>

<form method="GET" action="{{ route('propiedades.historial', $propiedad) }}" class="card card-body shadow-sm mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small mb-1">Tipo de cambio</label>
            <select name="tipo" class="form-select form-select-sm">
                <option value="">Todos</option>
                @foreach ($tipos as $clave => $etiqueta)
                    <option value="{{ $clave }}" @selected($tipo === $clave)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Desde</label>
            <input type="date" name="desde" value="{{ $desde }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary flex-fill">Filtrar</button>
            <a href="{{ route('propiedades.historial', $propiedad) }}" class="btn btn-sm btn-outline-secondary flex-fill">Limpiar</a>
        </div>
    </div>
</form>

@unless ($rangoValido)
    <div class="alert alert-danger">El rango de fechas no es válido.</div>
@else
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Usuario</th>
                        <th class="text-end">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cambios as $c)
                        @php
                            $antes = $controlador::pares($c->valoranterior);
                            $despues = $controlador::pares($c->valoractual);
                            $campos = array_values(array_unique(array_merge(array_keys($antes), array_keys($despues))));
                        @endphp
                        <tr>
                            <td>{{ $c->fecha->format('d/m/Y H:i') }}</td>
                            <td>{{ $tipos[$c->tipo] ?? $c->tipo }}</td>
                            <td>{{ $c->usuario?->nombre ?? 'Sistema' }}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#detalle-{{ $c->idhistorial }}">Ver detalle</button>
                            </td>
                        </tr>
                        <tr class="collapse" id="detalle-{{ $c->idhistorial }}">
                            <td colspan="4" class="bg-light">
                                @if ($c->motivo)
                                    <p class="mb-2"><strong>Motivo:</strong> {{ $c->motivo }}</p>
                                @endif
                                @if ($campos)
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr><th>Campo</th><th>Valor anterior</th><th>Valor nuevo</th></tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($campos as $campo)
                                                <tr>
                                                    <td>{{ $etiquetasCampo[$campo] ?? $campo }}</td>
                                                    <td>{{ ($antes[$campo] ?? '') !== '' ? $antes[$campo] : '—' }}</td>
                                                    <td>{{ ($despues[$campo] ?? '') !== '' ? $despues[$campo] : '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <span class="text-muted">Sin valores registrados.</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Sin cambios registrados</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $cambios->links('pagination::bootstrap-5') }}</div>
@endunless
@endsection