@extends('layouts.app')

@section('titulo', 'Mapa de la cartera - LIT Inmobiliaria')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Mapa de la cartera <span class="cu-etiqueta">(CU21)</span></h1>
    <a href="{{ route('propiedades.index') }}" class="btn btn-outline-secondary">Volver al inventario</a>
</div>

<form method="GET" action="{{ route('propiedades.mapa') }}" class="row g-2 mb-3">
    <div class="col-md-5">
        <select name="zona" class="form-select">
            <option value="">Todas las zonas</option>
            @foreach ($zonas as $nombre)
                <option value="{{ $nombre }}" @selected($zona === $nombre)>{{ $nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-outline-secondary w-100">Filtrar</button>
    </div>
    <div class="col-md-5 text-md-end align-self-center small text-muted">{{ count($marcadores) }} propiedad(es) en el mapa</div>
</form>

@if (count($marcadores) === 0)
    <div class="alert alert-info">{{ $zona !== '' ? 'No hay propiedades en esa zona.' : 'No hay propiedades con ubicación registrada.' }}</div>
@endif

<div id="mapa" class="rounded border" style="height: 520px"></div>
@endsection

@push('estilos')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script>
        (function () {
            if (typeof L === 'undefined') { return; }
            var centro = @json(config('santacruz.centro'));
            var radioMetros = {{ (float) config('santacruz.radio_km') }} * 1000;
            var propiedades = @json($marcadores);

            var mapa = L.map('mapa').setView([centro.lat, centro.lng], 12);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(mapa);
            var limite = L.circle([centro.lat, centro.lng], { radius: radioMetros, color: '#dc3545', weight: 2, fill: false }).addTo(mapa);

            function texto(valor) {
                var e = document.createElement('div');
                e.textContent = valor;
                return e.innerHTML;
            }

            var puntos = [];
            propiedades.forEach(function (p) {
                L.marker([p.lat, p.lng]).addTo(mapa).bindPopup(
                    '<strong>' + texto(p.codigo) + '</strong><br>' + texto(p.titulo) + '<br>' +
                    texto(p.precio) + ' &middot; ' + texto(p.estado) + '<br>' +
                    '<a href="' + encodeURI(p.url) + '">Ver ficha</a>'
                );
                puntos.push([p.lat, p.lng]);
            });

            if (puntos.length > 0) { mapa.fitBounds(puntos, { padding: [40, 40], maxZoom: 16 }); }
            else { mapa.fitBounds(limite.getBounds()); }
        })();
    </script>
@endpush