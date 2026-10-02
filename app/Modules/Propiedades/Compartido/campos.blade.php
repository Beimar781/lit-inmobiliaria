{{-- Formulario compartido por CU5 (registrar) y CU6 (modificar). $propiedad es null al registrar. --}}
@php($editando = $propiedad !== null)

<h2 class="h5 mb-3">Datos generales</h2>
<x-campo name="titulo" label="Título" :valor="$propiedad?->titulo" />

<div class="mb-3">
    <label for="descripcion" class="form-label">Descripción</label>
    <textarea id="descripcion" name="descripcion" rows="3"
              class="form-control @error('descripcion') is-invalid @enderror">{{ old('descripcion', $propiedad?->descripcion) }}</textarea>
    @error('descripcion')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-4">
        <x-seleccion name="tipopropiedad" label="Tipo de operación"
                     :opciones="array_combine(\App\Models\Propiedad::TIPOS, \App\Models\Propiedad::TIPOS)"
                     :valor="$propiedad?->tipopropiedad" vacio="Selecciona" />
    </div>
    <div class="col-md-4">
        <x-campo name="precio" label="Precio (USD $)" type="number" step="0.01" min="0" :valor="$propiedad?->precio" />
    </div>
    <div class="col-md-4">
        <x-seleccion name="idcategoria" label="Categoría" :opciones="$categorias"
                     :valor="$propiedad?->idcategoria" vacio="Selecciona" />
    </div>
</div>

<div class="row">
    <div class="col-md-3"><x-campo name="superficie" label="Superficie (m²)" type="number" step="0.01" min="0" :valor="$propiedad?->superficie" /></div>
    <div class="col-md-3"><x-campo name="areaconstruida" label="Área construida (m²)" type="number" step="0.01" min="0" :valor="$propiedad?->areaconstruida" /></div>
    <div class="col-md-2"><x-campo name="habitaciones" label="Habitaciones" type="number" min="0" :valor="$propiedad?->habitaciones" /></div>
    <div class="col-md-2"><x-campo name="banos" label="Baños" type="number" min="0" :valor="$propiedad?->banos" /></div>
    <div class="col-md-2"><x-campo name="antiguedad" label="Antigüedad (años)" type="number" min="0" :valor="$propiedad?->antiguedad" /></div>
</div>

@if ($editando)
    <div class="row">
        <div class="col-md-4">
            <x-seleccion name="estadopropiedad" label="Estado de la propiedad"
                         :opciones="['DISPONIBLE' => 'Disponible', 'RESERVADO' => 'Reservado', 'VENDIDO' => 'Vendido', 'ALQUILADO' => 'Alquilado']"
                         :valor="$propiedad->estadopropiedad" />
        </div>
    </div>
@endif

<hr class="my-4">
<h2 class="h5 mb-3">Propietario</h2>
<x-seleccion name="idpropietario" label="Propietario" :opciones="$propietarios"
             :valor="$propiedad?->idpropietario" vacio="— Registrar un propietario nuevo —" />

<div id="bloque-nuevo-propietario" class="border rounded p-3 mb-3 bg-light">
    <p class="fw-semibold mb-2">Datos del nuevo propietario</p>
    <x-campo name="propietario_nombre" label="Nombre completo" />
    <div class="row">
        <div class="col-md-6"><x-campo name="propietario_telefono" label="Teléfono" /></div>
        <div class="col-md-6"><x-campo name="propietario_email" label="Correo electrónico" type="email" /></div>
    </div>
    <x-campo name="propietario_direccion" label="Dirección" />
</div>

<hr class="my-4">
<h2 class="h5 mb-1">Ubicación</h2>
<p class="text-muted small">
    Solo se pueden registrar propiedades en {{ config('santacruz.ciudad') }}, dentro del 4.º anillo
    (círculo rojo del mapa). Haz clic en el mapa para marcar el punto exacto.
</p>

<div class="row">
    <div class="col-md-4"><x-campo name="zona" label="Zona / barrio" :valor="$propiedad?->ubicacion?->zona" /></div>
    <div class="col-md-8"><x-campo name="direccion" label="Dirección" :valor="$propiedad?->ubicacion?->direccion" /></div>
</div>

<div id="mapa" class="rounded border mb-2" style="height: 380px"></div>
<div id="aviso-ubicacion" class="form-text mb-3">Aún no marcaste un punto en el mapa.</div>

<div class="row">
    <div class="col-md-6"><x-campo name="latitud" label="Latitud" :valor="$propiedad?->ubicacion?->latitud" /></div>
    <div class="col-md-6"><x-campo name="longitud" label="Longitud" :valor="$propiedad?->ubicacion?->longitud" /></div>
</div>

<hr class="my-4">
<h2 class="h5 mb-1">Imágenes</h2>
<p class="small text-muted mb-3">Elige la <strong>imagen de portada</strong>: es la que se muestra en el listado de propiedades.</p>

@if ($editando && $propiedad->imagenes->isNotEmpty())
    @php($portadaActual = old('portada', 'img-' . $propiedad->imagenPrincipal?->idimagen))
    <div class="row g-2 mb-3">
        @foreach ($propiedad->imagenes as $imagen)
            <div class="col-6 col-md-3">
                <div class="border rounded p-1 text-center">
                    <img src="{{ asset($imagen->ruta) }}" alt="{{ $imagen->nombre }}" class="img-fluid rounded"
                         style="height: 100px; object-fit: cover; width: 100%">
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="radio" name="portada" value="img-{{ $imagen->idimagen }}"
                               id="portada{{ $imagen->idimagen }}" @checked($portadaActual === 'img-' . $imagen->idimagen)>
                        <label class="form-check-label small" for="portada{{ $imagen->idimagen }}">Portada</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="eliminar_imagenes[]"
                               value="{{ $imagen->idimagen }}" id="img{{ $imagen->idimagen }}">
                        <label class="form-check-label small" for="img{{ $imagen->idimagen }}">Eliminar</label>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="mb-3">
    <label for="imagenes" class="form-label">{{ $editando ? 'Agregar imágenes' : 'Imágenes de la propiedad' }}</label>
    <input type="file" id="imagenes" name="imagenes[]" multiple accept=".jpg,.jpeg,.png,.webp"
           class="form-control @if ($errors->has('imagenes') || $errors->has('imagenes.*')) is-invalid @endif">
    <div class="form-text">JPG, PNG o WEBP, hasta 4 MB cada una y máximo 8 por propiedad.</div>
    @error('imagenes')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @foreach ($errors->get('imagenes.*') as $mensajes)
        @foreach ($mensajes as $mensaje)
            <div class="invalid-feedback">{{ $mensaje }}</div>
        @endforeach
    @endforeach
</div>

{{-- Vista previa de las imágenes que se van a subir, con la opción de elegir la portada --}}
<div id="previas" class="row g-2 mb-3"></div>

@push('scripts')
    <script>
        (function () {
            var entrada = document.getElementById('imagenes');
            var previas = document.getElementById('previas');
            var hayGuardadas = {{ $editando && $propiedad->imagenes->isNotEmpty() ? 'true' : 'false' }};

            entrada.addEventListener('change', function () {
                previas.innerHTML = '';
                Array.prototype.forEach.call(entrada.files, function (archivo, i) {
                    var col = document.createElement('div');
                    col.className = 'col-6 col-md-3';
                    col.innerHTML =
                        '<div class="border rounded p-1 text-center">' +
                        '<img class="img-fluid rounded" style="height:100px;object-fit:cover;width:100%">' +
                        '<div class="form-check mt-1">' +
                        '<input class="form-check-input" type="radio" name="portada" value="nueva-' + i + '" id="portada-nueva-' + i + '">' +
                        '<label class="form-check-label small" for="portada-nueva-' + i + '">Portada</label></div>' +
                        '<div class="small text-muted text-truncate nombre"></div></div>';
                    col.querySelector('img').src = URL.createObjectURL(archivo);
                    col.querySelector('.nombre').textContent = archivo.name;
                    // Al registrar, la primera imagen queda como portada por defecto
                    if (!hayGuardadas && i === 0) { col.querySelector('input').checked = true; }
                    previas.appendChild(col);
                });
            });
        })();
    </script>
@endpush

@push('estilos')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script>
        (function () {
            // ----- Propietario: mostrar los campos del nuevo solo si no se elige uno existente -----
            var lista = document.getElementById('idpropietario');
            var bloque = document.getElementById('bloque-nuevo-propietario');
            function alternarPropietario() { bloque.style.display = lista.value === '' ? 'block' : 'none'; }
            lista.addEventListener('change', alternarPropietario);
            alternarPropietario();

            // ----- Mapa con el límite del 4.º anillo -----
            var centro = @json(config('santacruz.centro'));
            var radioMetros = {{ (float) config('santacruz.radio_km') }} * 1000;

            var mapa = L.map('mapa').setView([centro.lat, centro.lng], 12);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(mapa);

            var limite = L.circle([centro.lat, centro.lng], {
                radius: radioMetros, color: '#dc3545', weight: 2, fill: false
            }).addTo(mapa);
            mapa.fitBounds(limite.getBounds());

            var inputLat = document.getElementById('latitud');
            var inputLng = document.getElementById('longitud');
            var aviso = document.getElementById('aviso-ubicacion');
            var marcador = null;

            function estaDentro(lat, lng) {
                return mapa.distance([lat, lng], [centro.lat, centro.lng]) <= radioMetros;
            }

            function poner(lat, lng) {
                if (marcador === null) {
                    marcador = L.marker([lat, lng], { draggable: true }).addTo(mapa);
                    marcador.on('dragend', function () {
                        var p = marcador.getLatLng();
                        poner(p.lat, p.lng);
                    });
                } else {
                    marcador.setLatLng([lat, lng]);
                }

                inputLat.value = lat.toFixed(7);
                inputLng.value = lng.toFixed(7);

                if (estaDentro(lat, lng)) {
                    aviso.className = 'form-text mb-3 text-success';
                    aviso.textContent = 'Ubicación válida: dentro del 4.º anillo.';
                } else {
                    aviso.className = 'form-text mb-3 text-danger';
                    aviso.textContent = 'Esta ubicación está fuera del 4.º anillo: no se podrá registrar.';
                }
            }

            mapa.on('click', function (e) { poner(e.latlng.lat, e.latlng.lng); });

            [inputLat, inputLng].forEach(function (campo) {
                campo.addEventListener('change', function () {
                    var lat = parseFloat(inputLat.value), lng = parseFloat(inputLng.value);
                    if (!isNaN(lat) && !isNaN(lng)) { poner(lat, lng); mapa.panTo([lat, lng]); }
                });
            });

            if (inputLat.value !== '' && inputLng.value !== '') {
                poner(parseFloat(inputLat.value), parseFloat(inputLng.value));
            }
        })();
    </script>
@endpush
