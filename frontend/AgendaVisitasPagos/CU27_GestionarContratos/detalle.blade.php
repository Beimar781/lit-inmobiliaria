@extends('layouts.app')

@section('titulo', 'Contrato ' . $contrato->idcontrato . ' - LIT Inmobiliaria')

@php($colores = ['VIGENTE' => 'success', 'FINALIZADO' => 'secondary', 'RESCINDIDO' => 'danger'])
@php($coloresPago = ['PAGADO' => 'success', 'PENDIENTE' => 'warning', 'ANULADO' => 'secondary'])

@section('contenido')
<a href="{{ route('contratos.index') }}" class="text-decoration-none">&larr; Volver a contratos</a>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 my-3">
    <div>
        <h1 class="h3 mb-1">Contrato N.º {{ $contrato->idcontrato }} &middot; {{ $contrato->tipocontrato }}</h1>
        <p class="text-muted mb-0">{{ $contrato->propiedad->codigo }} &middot; {{ $contrato->propiedad->titulo }}</p>
    </div>
    <span class="badge text-bg-{{ $colores[$contrato->estado] ?? 'secondary' }} fs-6">{{ $contrato->estado_etiqueta }}</span>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Cliente</dt>
                    <dd class="col-sm-8">{{ $contrato->cliente->nombre }}</dd>
                    <dt class="col-sm-4">Propiedad</dt>
                    <dd class="col-sm-8">
                        {{ $contrato->propiedad->codigo }} &middot; {{ $contrato->propiedad->titulo }}
                        <span class="text-muted small">({{ ucfirst(strtolower($contrato->propiedad->estadopropiedad)) }})</span>
                    </dd>
                    <dt class="col-sm-4">Tipo</dt>
                    <dd class="col-sm-8">{{ $contrato->tipocontrato }}</dd>
                    <dt class="col-sm-4">Monto</dt>
                    <dd class="col-sm-8">USD {{ number_format($contrato->monto, 2, '.', ',') }}</dd>
                    <dt class="col-sm-4">Pagado</dt>
                    <dd class="col-sm-8">USD {{ number_format($pagado, 2, '.', ',') }}</dd>
                    <dt class="col-sm-4">Saldo</dt>
                    <dd class="col-sm-8">USD {{ number_format($saldo, 2, '.', ',') }}</dd>
                    <dt class="col-sm-4">Inicio</dt>
                    <dd class="col-sm-8">{{ $contrato->fechainicio->format('d/m/Y') }}</dd>
                    <dt class="col-sm-4">Fin</dt>
                    <dd class="col-sm-8">{{ $contrato->fechafin?->format('d/m/Y') ?? '—' }}</dd>
                    <dt class="col-sm-4">Gestionado por</dt>
                    <dd class="col-sm-8">{{ $contrato->agente?->nombre ?? '—' }}</dd>
                    @if ($contrato->reserva)
                        <dt class="col-sm-4">Viene de la reserva</dt>
                        <dd class="col-sm-8">N.º {{ $contrato->reserva->idreserva }} ({{ $contrato->reserva->estado_etiqueta }})</dd>
                    @endif
                </dl>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h5 mb-0">Pagos</h2>
                    {{-- CU28: solo el Administrador y el Asistente registran pagos --}}
                    @if (auth()->user()->tieneRol('Administrador') && $contrato->estado === 'VIGENTE' && Route::has('pagos.create'))
                        <a href="{{ route('pagos.create', ['contrato' => $contrato->idcontrato]) }}" class="btn btn-sm btn-outline-primary">Registrar pago</a>
                    @endif
                </div>
                @if ($contrato->reserva && $contrato->reserva->montousd !== null)
                    <p class="small text-muted">
                        El pago inicial viene de la reserva N.º {{ $contrato->reserva->idreserva }}: el cliente ya había pagado USD {{ number_format($contrato->reserva->montousd, 2, '.', ',') }} y ese monto se tomó como primer pago del contrato.
                    </p>
                @endif
                @forelse ($contrato->pagos->sortByDesc('idpago') as $pago)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>
                            {{ $pago->fechapago->format('d/m/Y') }} &middot; {{ $pago->moneda }} {{ number_format($pago->monto, 2, '.', ',') }}
                            @if ($pago->comprobante)
                                @if (auth()->user()->tieneRol('Administrador') && Route::has('pagos.comprobante'))
                                    <a href="{{ route('pagos.comprobante', $pago) }}" class="small ms-1">{{ $pago->comprobante }}</a>
                                @else
                                    <span class="text-muted small">&middot; {{ $pago->comprobante }}</span>
                                @endif
                            @endif
                        </span>
                        <span class="badge text-bg-{{ $coloresPago[$pago->estado] ?? 'secondary' }}">{{ $pago->estado_etiqueta }}</span>
                    </div>
                @empty
                    <p class="text-muted mb-0">Todavía no hay pagos registrados.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Cambiar el estado del contrato</h2>

                @if ($contrato->estado === 'VIGENTE')
                    <form method="POST" action="{{ route('contratos.estado', $contrato) }}" novalidate>
                        @csrf
                        @method('PATCH')

                        <x-seleccion name="estado" label="Nuevo estado"
                                     :opciones="['FINALIZADO' => 'Finalizado', 'RESCINDIDO' => 'Rescindido (la propiedad vuelve a estar disponible)']"
                                     vacio="— Elige el estado —" />

                        <div class="mb-3">
                            <label for="motivo" class="form-label">Motivo <span class="text-muted">(obligatorio al rescindir)</span></label>
                            <textarea id="motivo" name="motivo" rows="3" maxlength="500"
                                      class="form-control @error('motivo') is-invalid @enderror">{{ old('motivo') }}</textarea>
                            @error('motivo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary"
                                onclick="return confirm('¿Cambiar el estado del contrato?')">Guardar estado</button>
                    </form>
                @else
                    <p class="text-muted mb-0">Este contrato está {{ strtolower($contrato->estado_etiqueta) }}: ya no se puede cambiar su estado.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
