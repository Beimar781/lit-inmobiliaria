@extends('layouts.app')

@section('titulo', $propiedad->titulo . ' - LIT Inmobiliaria')

@php($colores = ['DISPONIBLE' => 'success', 'RESERVADO' => 'warning', 'VENDIDO' => 'secondary', 'ALQUILADO' => 'info'])

@section('contenido')
<a href="{{ route('catalogo.index') }}" class="text-decoration-none">&larr; Volver al catálogo</a>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 my-3">
    <div>
        <p class="text-muted small mb-1">{{ $propiedad->codigo }}</p>
        <h1 class="h3 mb-1">{{ $propiedad->titulo }}</h1>
        <p class="text-muted mb-0">
            {{ $propiedad->categoria?->nombre }} &middot; {{ $propiedad->tipopropiedad }}
            @if ($propiedad->ubicacion?->zona) &middot; {{ $propiedad->ubicacion->zona }} @endif
        </p>
    </div>
    <div class="text-md-end">
        <span class="badge text-bg-{{ $colores[$propiedad->estadopropiedad] ?? 'secondary' }} mb-1">{{ ucfirst(strtolower($propiedad->estadopropiedad)) }}</span>
        <p class="h3 mb-0">USD {{ number_format($propiedad->precio, 0, '.', ',') }}@if ($propiedad->tipopropiedad === 'Alquiler') <small class="text-muted fs-6">/ mes</small>@endif</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        {{-- Galería: la portada va primero --}}
        <div class="card shadow-sm mb-4">
            @if ($imagenes->isNotEmpty())
                {{-- La foto se muestra completa y sin recortar, con su proporción original y sin agrandarla (así no pierde nitidez).
                     Al hacer clic se abre el archivo original en otra pestaña. --}}
                <a id="enlace-foto" href="{{ asset($imagenes->first()->ruta) }}" target="_blank" rel="noopener"
                   class="d-flex align-items-center justify-content-center rounded-top overflow-hidden"
                   style="height: 560px; background: #e9ecef" title="Ver la foto en su tamaño original">
                    <img id="foto-principal" src="{{ asset($imagenes->first()->ruta) }}" alt="{{ $propiedad->titulo }}"
                         style="max-width: 100%; max-height: 100%; width: auto; height: auto">
                </a>
                @if ($imagenes->count() > 1)
                    <div class="card-body d-flex flex-wrap gap-2">
                        @foreach ($imagenes as $imagen)
                            <img src="{{ asset($imagen->ruta) }}" alt="Foto {{ $loop->iteration }}" class="rounded border miniatura"
                                 style="width: 88px; height: 64px; object-fit: cover; cursor: pointer">
                        @endforeach
                    </div>
                @endif
            @else
                <div class="bg-secondary-subtle text-center text-muted d-flex align-items-center justify-content-center"
                     style="height: 380px">Esta propiedad aún no tiene fotos</div>
            @endif
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5">Descripción</h2>
                <p class="mb-0">{{ $propiedad->descripcion ?: 'Sin descripción.' }}</p>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Características</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Superficie</dt>
                    <dd class="col-sm-8">{{ $propiedad->superficie !== null ? rtrim(rtrim(number_format($propiedad->superficie, 2), '0'), '.') . ' m²' : '—' }}</dd>
                    <dt class="col-sm-4">Área construida</dt>
                    <dd class="col-sm-8">{{ $propiedad->areaconstruida !== null ? rtrim(rtrim(number_format($propiedad->areaconstruida, 2), '0'), '.') . ' m²' : '—' }}</dd>
                    <dt class="col-sm-4">Habitaciones</dt>
                    <dd class="col-sm-8">{{ $propiedad->habitaciones ?? '—' }}</dd>
                    <dt class="col-sm-4">Baños</dt>
                    <dd class="col-sm-8">{{ $propiedad->banos ?? '—' }}</dd>
                    <dt class="col-sm-4">Antigüedad</dt>
                    <dd class="col-sm-8">{{ $propiedad->antiguedad !== null ? $propiedad->antiguedad . ' años' : '—' }}</dd>
                </dl>
            </div>
        </div>

        @if ($propiedad->ubicacion?->latitud !== null && $propiedad->ubicacion?->longitud !== null)
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5">Ubicación</h2>
                    <p class="text-muted small mb-2">{{ $propiedad->ubicacion->direccion }}{{ str_contains(mb_strtolower($propiedad->ubicacion->direccion), 'referencial') ? '' : ' (referencial)' }}</p>
                    <div id="mapa" style="height: 300px" class="rounded border"></div>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-4">
        {{-- Agente a cargo --}}
        @if ($propiedad->agente)
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5">Agente a cargo</h2>
                    <p class="mb-1">{{ $propiedad->agente->nombre }}</p>
                    @if ($propiedad->agente->telefono)
                        <p class="text-muted mb-2">Tel. {{ $propiedad->agente->telefono }}</p>
                    @endif
                    @if ($disponible && $propiedad->whatsapp)
                        <a href="{{ $propiedad->whatsapp }}" target="_blank" rel="noopener" class="btn btn-outline-success btn-sm">Escribir por WhatsApp</a>
                    @endif
                </div>
            </div>
        @endif

        {{-- CU12: "Me interesa" (solo si está disponible) --}}
        @if ($disponible)
            @include('crmgestionclientes::CU12_RegistrarClienteInteresado.me-interesa')
        @else
            <div class="alert alert-secondary">
                Esta propiedad ya no está disponible ({{ strtolower($propiedad->estadopropiedad) }}).
                <a href="{{ route('catalogo.index') }}" class="alert-link">Ver otras propiedades</a>
            </div>
        @endif
    </div>
</div>
@push('scripts')
<script>
    document.querySelectorAll('.miniatura').forEach(function (m) {
        m.addEventListener('click', function () {
            document.getElementById('foto-principal').src = m.src;
            document.getElementById('enlace-foto').href = m.src;
        });
    });
</script>
@endpush

@if ($propiedad->ubicacion?->latitud !== null && $propiedad->ubicacion?->longitud !== null)
    @push('estilos')
        <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    @endpush

    @push('scripts')
        <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
        <script>
            (function () {
                // Si el mapa no carga (sin internet), la ficha se muestra igual, sin el mapa.
                if (typeof L === 'undefined') { return; }
                var punto = [{{ (float) $propiedad->ubicacion->latitud }}, {{ (float) $propiedad->ubicacion->longitud }}];
                var mapa = L.map('mapa').setView(punto, 15);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; Colaboradores de OpenStreetMap'
                }).addTo(mapa);
                L.marker(punto).addTo(mapa);
            })();
        </script>
    @endpush
@endif
@endsection
