@extends('layouts.app')

@section('titulo', 'Ubicación de la propiedad - LIT Inmobiliaria')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Ubicación de la propiedad <span class="cu-etiqueta">(CU21)</span></h1>
    <a href="{{ route('propiedades.index') }}" class="btn btn-outline-secondary">Volver al inventario</a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <span class="badge text-bg-dark me-2">{{ $propiedad->codigo }}</span>
        <strong>{{ $propiedad->titulo }}</strong>
    </div>
</div>

<form method="POST" action="{{ route('propiedades.ubicacion.guardar', $propiedad) }}" novalidate>
    @csrf
    @method('PUT')

    <p class="small text-muted">
        La propiedad debe estar dentro del 4.º anillo (círculo rojo del mapa). Haz clic en el mapa o arrastra el marcador;
        también puedes escribir la latitud y la longitud a mano.
    </p>

    <div class="row">
        <div class="col-md-4"><x-campo name="zona" label="Zona / barrio" :valor="$propiedad->ubicacion?->zona" /></div>
        <div class="col-md-8"><x-campo name="direccion" label="Dirección" :valor="$propiedad->ubicacion?->direccion" /></div>
    </div>

    <div id="mapa" class="rounded border mb-2" style="height: 380px"></div>
    <div id="aviso-ubicacion" class="form-text mb-3">Aún no marcaste un punto en el mapa.</div>

    <div class="row">
        <div class="col-md-6"><x-campo name="latitud" label="Latitud" :valor="$propiedad->ubicacion?->latitud" /></div>
        <div class="col-md-6"><x-campo name="longitud" label="Longitud" :valor="$propiedad->ubicacion?->longitud" /></div>
    </div>

    <button type="submit" class="btn btn-primary">Guardar ubicación</button>
    <a href="{{ route('propiedades.index') }}" class="btn btn-outline-secondary">Cancelar</a>
</form>
@endsection

@push('estilos')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script>
        (function () {
            var inputLat = document.getElementById('latitud');
            var inputLng = document.getElementById('longitud');
            var aviso = document.getElementById('aviso-ubicacion');
            var centro = @json(config('santacruz.centro'));
            var radioMetros = {{ (float) config('santacruz.radio_km') }} * 1000;
            var marcador = null;
            var mapa = null;

            function estaDentro(lat, lng) {
                var dLat = (lat - centro.lat) * Math.PI / 180, dLng = (lng - centro.lng) * Math.PI / 180;
                var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(centro.lat * Math.PI / 180) * Math.cos(lat * Math.PI / 180) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
                return 6371000 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)) <= radioMetros;
            }

            function avisar(lat, lng) {
                if (estaDentro(lat, lng)) {
                    aviso.className = 'form-text mb-3 text-success';
                    aviso.textContent = 'Ubicación válida: dentro del 4.º anillo.';
                } else {
                    aviso.className = 'form-text mb-3 text-danger';
                    aviso.textContent = 'Esta ubicación está fuera del 4.º anillo: no se podrá guardar.';
                }
            }

            function poner(lat, lng) {
                if (mapa !== null) {
                    if (marcador === null) {
                        marcador = L.marker([lat, lng], { draggable: true }).addTo(mapa);
                        marcador.on('dragend', function () { var p = marcador.getLatLng(); poner(p.lat, p.lng); });
                    } else {
                        marcador.setLatLng([lat, lng]);
                    }
                }
                inputLat.value = lat.toFixed(7);
                inputLng.value = lng.toFixed(7);
                avisar(lat, lng);
            }

            // Las coordenadas escritas a mano funcionan aunque el mapa no cargue.
            [inputLat, inputLng].forEach(function (campo) {
                campo.addEventListener('change', function () {
                    var lat = parseFloat(inputLat.value), lng = parseFloat(inputLng.value);
                    if (!isNaN(lat) && !isNaN(lng)) {
                        poner(lat, lng);
                        if (mapa !== null) { mapa.panTo([lat, lng]); }
                    }
                });
            });

            if (typeof L === 'undefined') {
                aviso.textContent = 'No se pudo cargar el mapa. Escribe la latitud y la longitud a mano.';
                return;
            }

            mapa = L.map('mapa').setView([centro.lat, centro.lng], 12);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(mapa);
            var limite = L.circle([centro.lat, centro.lng], { radius: radioMetros, color: '#dc3545', weight: 2, fill: false }).addTo(mapa);
            mapa.fitBounds(limite.getBounds());
            mapa.on('click', function (e) { poner(e.latlng.lat, e.latlng.lng); });

            if (inputLat.value !== '' && inputLng.value !== '') {
                var lat = parseFloat(inputLat.value), lng = parseFloat(inputLng.value);
                if (!isNaN(lat) && !isNaN(lng)) { poner(lat, lng); mapa.setView([lat, lng], 15); }
            }
        })();
    </script>
@endpush