@extends('layouts.app')

@section('titulo', 'Reportes - LIT Inmobiliaria')

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Reportes <span class="cu-etiqueta">(CU20)</span></h1>
</div>

@if ($iaDisponible)
    <form method="POST" action="{{ route('reportes.interpretar') }}" class="card card-body shadow-sm mb-3">
        @csrf
        <input type="hidden" name="voz" id="campo-voz" value="">
        <label for="solicitud" class="form-label fw-semibold">Describe el reporte que necesitas</label>
        <div class="input-group">
            <input type="text" name="solicitud" id="solicitud" maxlength="300" value="{{ old('solicitud') }}"
                   class="form-control @error('solicitud') is-invalid @enderror"
                   placeholder="Ej.: visitas canceladas del último mes por agente">
            <button type="button" class="btn btn-outline-dark d-none" id="btn-microfono" title="Dictar la solicitud por voz">&#127908; Hablar</button>
            <button type="submit" class="btn btn-dark">Interpretar con IA</button>
            @error('solicitud')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-text">La IA solo recibe tu frase para entender qué reporte quieres; no consulta la base de datos. Si la entiende, el reporte se genera directo; si no, te muestra lo que interpretó para que lo corrijas.</div>
        <div class="form-text text-primary" id="estado-voz" aria-live="polite"></div>
        <div class="form-text text-muted d-none" id="aviso-sin-voz">Tu navegador no permite dictado por voz. Usa Google Chrome o Microsoft Edge para hablar con el sistema.</div>
    </form>
@endif

@if ($confirmar)
    {{-- Solo aparece cuando la IA NO pudo generar el reporte directo: se muestra lo que sí entendió para que el usuario lo corrija. --}}
    <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2" id="aviso-confirmar"
         data-texto="{{ trim($errors->first('solicitud') . ' ' . $textoConfirmacion) }}" data-voz="{{ $voz ? '1' : '0' }}"
         data-confirmable="{{ $confirmable ? '1' : '0' }}">
        <div>
            <strong>Esto fue lo que entendí.</strong>
            @if ($entendido)
                <ul class="mb-1">
                    @foreach ($entendido as $linea)
                        <li>{{ $linea }}</li>
                    @endforeach
                </ul>
            @endif
            <span>Revisa los criterios (puedes cambiarlos) y confirma para generar el reporte.</span>
        </div>
        <button type="button" class="btn btn-sm btn-dark d-none" id="btn-confirmar-voz">&#128266; Escuchar y confirmar por voz</button>
    </div>
@endif
<form method="GET" action="{{ route('reportes.generar') }}" class="card card-body shadow-sm mb-4" id="form-reporte">
    @if ($confirmar)
        <input type="hidden" name="origen" value="ia">
        <input type="hidden" name="con_resumen" value="1">
        <input type="hidden" name="solicitud" value="{{ $solicitud }}">
        @if ($voz)
            <input type="hidden" name="voz" value="1">
        @endif
    @endif
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
            <button type="submit" class="btn btn-primary" id="btn-generar">{{ $confirmar ? 'Confirmar y generar' : 'Generar reporte' }}</button>
            <a href="{{ route('reportes.index') }}" class="btn btn-outline-secondary">Limpiar</a>
        </div>
    </div>
</form>

@if ($error)
    <div class="alert alert-danger">{{ $error }}</div>
@endif

@if ($resultado)
    <div class="card shadow-sm" id="bloque-resultado" data-texto="{{ $textoVoz }}" data-voz="{{ $voz ? '1' : '0' }}">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">{{ $resultado->titulo }}</h2>
                    @if ($origenIa)
                        <p class="small text-muted mb-0">Criterios usados: {{ implode(' · ', $filtros) }} &middot; <a href="{{ route('reportes.index', $parametros) }}">Ajustar criterios</a></p>
                    @else
                        <p class="small text-muted mb-0">{{ implode(' · ', $filtros) }}</p>
                    @endif
                </div>
                @unless ($resultado->estaVacio())
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-dark btn-sm d-none" id="btn-leer">&#128266; Leer resultado</button>
                        <button type="button" class="btn btn-outline-dark btn-sm d-none" id="btn-comando" title="Di: PDF, Excel, leer o detener">&#127908; Comando de voz</button>
                        <a href="{{ route('reportes.exportar', ['formato' => 'pdf'] + $parametros) }}" class="btn btn-outline-danger btn-sm" id="enlace-pdf">Exportar PDF</a>
                        <a href="{{ route('reportes.exportar', ['formato' => 'xlsx'] + $parametros) }}" class="btn btn-outline-success btn-sm" id="enlace-xlsx">Exportar Excel</a>
                    </div>
                @endunless
            </div>
            <div class="small text-primary mb-2" id="estado-comando" aria-live="polite"></div>
            @if ($excedePdf)
                <div class="small text-warning-emphasis mb-2">El PDF admite hasta {{ $limitePdf }} filas; este reporte tiene más. Usa Excel o afina los filtros.</div>
            @endif

            @if ($resumen)
                <div class="alert alert-light border">
                    <div class="small text-muted mb-1">Resumen &middot; redactado por IA</div>
                    {{ $resumen }}
                </div>
            @elseif ($avisoResumen)
                <div class="alert alert-warning">{{ $avisoResumen }}</div>
            @endif

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
{{-- Detiene en cualquier momento la lectura en voz alta y el micrófono; solo se ve mientras el sistema habla o escucha. --}}
<button type="button" class="btn btn-danger shadow position-fixed d-none" id="btn-detener-voz" style="bottom: 1.25rem; right: 1.25rem; z-index: 1050" aria-label="Detener la voz">&#9209; Detener voz</button>
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
@push('scripts')
    <script>
        (function () {
            var Reconocimiento = window.SpeechRecognition || window.webkitSpeechRecognition;
            var sintesis = 'speechSynthesis' in window;

            function porId(id) { return document.getElementById(id); }
            function mostrar(boton) { if (boton) { boton.classList.remove('d-none'); } }
            function decir(estado, texto) { if (estado) { estado.textContent = texto; } }

            // ---------- Salida de voz: lee un texto en español con la mejor voz que tenga el navegador ----------
            function elegirVoz() {
                var voces = window.speechSynthesis.getVoices().filter(function (v) { return /^es/i.test(v.lang); });
                var orden = ['es-BO', 'es-MX', 'es-US', 'es-419', 'es-AR', 'es-ES'];
                for (var i = 0; i < orden.length; i++) {
                    var v = voces.filter(function (x) { return x.lang.replace('_', '-') === orden[i]; })[0];
                    if (v) { return v; }
                }
                return voces[0] || null;
            }

            // El botón «Detener voz» aparece mientras el sistema habla o escucha y corta lo que esté pasando.
            var detener = porId('btn-detener-voz');
            var turno = 0;          // cada lectura tiene un turno; al detener se invalida y no sigue con lo que venía después
            var hablando = false;
            var escuchando = null;
            var vigilante = null;

            function actualizarDetener() {
                var activo = hablando || escuchando !== null;
                if (detener) { detener.classList.toggle('d-none', !activo); }
                // Respaldo: algunos navegadores no avisan siempre cuando termina de hablar.
                if (activo && vigilante === null) {
                    vigilante = window.setInterval(function () {
                        if (hablando && sintesis && !window.speechSynthesis.speaking && !window.speechSynthesis.pending) { hablando = false; }
                        if (!hablando && escuchando === null) { window.clearInterval(vigilante); vigilante = null; }
                        if (detener) { detener.classList.toggle('d-none', !(hablando || escuchando !== null)); }
                    }, 700);
                }
            }

            function detenerTodo() {
                turno++;
                hablando = false;
                if (sintesis) { window.speechSynthesis.cancel(); }
                if (escuchando) { escuchando.detenido = true; try { escuchando.abort(); } catch (e) {} escuchando = null; }
                decir(porId('estado-voz'), '');
                decir(porId('estado-comando'), 'Voz detenida.');
                actualizarDetener();
            }

            if (detener) { detener.addEventListener('click', detenerTodo); }

            function hablar(texto, alTerminar, alFallar) {
                if (!sintesis || !texto) { if (alFallar) { alFallar(); } return; }
                window.speechSynthesis.cancel();
                var mio = ++turno;
                var frase = new SpeechSynthesisUtterance(texto);
                frase.lang = 'es-BO';
                var voz = elegirVoz();
                if (voz) { frase.voice = voz; frase.lang = voz.lang; }
                frase.rate = 1;
                frase.onend = function () {
                    if (mio !== turno) { return; }
                    hablando = false; actualizarDetener();
                    if (alTerminar) { alTerminar(); }
                };
                frase.onerror = function () {
                    if (mio !== turno) { return; }
                    hablando = false; actualizarDetener();
                    if (alFallar) { alFallar(); }
                };
                hablando = true;
                actualizarDetener();
                window.speechSynthesis.speak(frase);
            }
            // ---------- Entrada de voz: escucha una frase y la entrega como texto ----------
            var MENSAJES = {
                'not-allowed': 'El navegador no tiene permiso para usar el micrófono. Permítelo en el candado de la barra de direcciones.',
                'service-not-allowed': 'El navegador no tiene permiso para usar el micrófono.',
                'no-speech': 'No escuché nada. Inténtalo de nuevo.',
                'audio-capture': 'No encontré un micrófono.',
                'network': 'El reconocimiento de voz necesita conexión a internet.'
            };

            function escuchar(estado, alParcial, alFinal) {
                if (!Reconocimiento) { return; }
                if (escuchando) { try { escuchando.abort(); } catch (e) {} }
                var r = new Reconocimiento();
                escuchando = r;
                actualizarDetener();
                r.lang = 'es-BO';
                r.interimResults = true;
                r.continuous = false;
                r.maxAlternatives = 1;
                var entregado = false;
                decir(estado, 'Escuchando… habla ahora.');
                r.onresult = function (e) {
                    var texto = '';
                    var terminado = false;
                    for (var i = e.resultIndex; i < e.results.length; i++) {
                        texto += e.results[i][0].transcript;
                        if (e.results[i].isFinal) { terminado = true; }
                    }
                    texto = texto.trim();
                    if (r.detenido) { return; }
                    if (terminado && texto) { entregado = true; alFinal(texto); }
                    else if (alParcial) { alParcial(texto); }
                };
                r.onerror = function (e) { if (r.detenido) { return; } decir(estado, MENSAJES[e.error] || ('No pude escuchar (' + e.error + ').')); entregado = true; };
                r.onend = function () {
                    if (r.detenido) { return; }
                    if (!entregado) { decir(estado, 'No escuché nada. Inténtalo de nuevo.'); }
                    if (escuchando === r) { escuchando = null; }
                    actualizarDetener();
                };
                try { r.start(); } catch (e) { decir(estado, 'No pude iniciar el micrófono.'); }
            }

            function dice(texto, palabras) {
                var t = texto.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                return palabras.some(function (p) { return t.indexOf(p) !== -1; });
            }

            // ---------- 1) Dictar la solicitud a la IA ----------
            var boton = porId('btn-microfono');
            if (boton) {
                if (Reconocimiento) {
                    mostrar(boton);
                    boton.addEventListener('click', function () {
                        var campo = porId('solicitud');
                        var estado = porId('estado-voz');
                        escuchar(estado, function (parcial) { campo.value = parcial; }, function (texto) {
                            campo.value = texto;
                            porId('campo-voz').value = '1';
                            decir(estado, 'Escuché: «' + texto + '». Interpretando…');
                            campo.form.submit();
                        });
                    });
                } else {
                    var aviso = porId('aviso-sin-voz');
                    if (aviso) { aviso.classList.remove('d-none'); }
                }
            }

            // ---------- 2) Confirmar los criterios por voz ----------
            var aviso = porId('aviso-confirmar');
            var botonConfirmar = porId('btn-confirmar-voz');
            if (aviso && botonConfirmar && sintesis && Reconocimiento) {
                mostrar(botonConfirmar);

                function preguntar() {
                    if (aviso.getAttribute('data-confirmable') !== '1') {
                        // No hay criterios que confirmar: solo se lee lo que pasó y se corrige en el formulario.
                        hablar(aviso.getAttribute('data-texto') + ' Corrige los criterios en el formulario.');
                        return;
                    }
                    var pregunta = aviso.getAttribute('data-texto') + ' ¿Quieres generar el reporte? Di confirmar o cancelar.';
                    hablar(pregunta, function () {
                        escuchar(porId('estado-voz') || aviso, null, function (respuesta) {
                            if (dice(respuesta, ['confirm', 'genera', 'si', 'dale', 'adelante', 'ok', 'acepto'])) {
                                porId('btn-generar').click();
                            } else if (dice(respuesta, ['cancel', 'no', 'salir'])) {
                                window.location.href = {{ Illuminate\Support\Js::from(route('reportes.index')) }};
                            } else {
                                hablar('No te entendí. Usa el botón o inténtalo de nuevo.');
                            }
                        });
                    });
                }

                botonConfirmar.addEventListener('click', preguntar);
                // En modo voz se intenta leer solo; si el navegador lo bloquea (necesita un clic), queda el botón.
                if (aviso.getAttribute('data-voz') === '1') { hablar(aviso.getAttribute('data-texto'), null, function () {}); }
            }

            // ---------- 3) Leer el resultado y comandos de voz ----------
            var bloque = porId('bloque-resultado');
            if (bloque) {
                var textoResultado = bloque.getAttribute('data-texto');
                var botonLeer = porId('btn-leer');
                var botonComando = porId('btn-comando');
                var estadoComando = porId('estado-comando');

                if (botonLeer && sintesis) {
                    mostrar(botonLeer);
                    botonLeer.addEventListener('click', function () { hablar(textoResultado); });
                    if (bloque.getAttribute('data-voz') === '1') { hablar(textoResultado, null, function () {}); }
                }

                if (botonComando && Reconocimiento) {
                    mostrar(botonComando);
                    botonComando.addEventListener('click', function () {
                        detenerTodo();
                        escuchar(estadoComando, null, function (orden) {
                            decir(estadoComando, 'Escuché: «' + orden + '».');
                            if (dice(orden, ['pdf'])) { window.location.href = porId('enlace-pdf').href; }
                            else if (dice(orden, ['excel', 'xls'])) { window.location.href = porId('enlace-xlsx').href; }
                            else if (dice(orden, ['detener', 'calla', 'silencio', 'para'])) { detenerTodo(); }
                            else if (dice(orden, ['leer', 'lee', 'repet', 'resumen', 'resultado', 'escuch'])) { hablar(textoResultado); }
                            else { decir(estadoComando, 'No entendí «' + orden + '». Di: PDF, Excel, leer o detener.'); }
                        });
                    });
                }
            }
        })();
    </script>
@endpush