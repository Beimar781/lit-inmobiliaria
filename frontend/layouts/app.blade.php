<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'LIT Inmobiliaria')</title>
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <style>
        /* Número del caso de uso junto al título de cada pantalla, en un gris suave */
        .cu-etiqueta { font-size: .55em; font-weight: 400; color: #adb5bd; margin-left: .25rem; white-space: nowrap; }
    </style>
    @stack('estilos')
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="{{ url('/') }}">LIT Inmobiliaria</a>

        @guest
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('catalogo.index') }}" class="text-white-50 small text-decoration-none">Catálogo</a>
                <a href="{{ route('login') }}" class="btn btn-outline-light btn-sm">Iniciar sesión</a>
            </div>
        @endguest

        @auth
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('catalogo.index') }}" class="text-white-50 small text-decoration-none">Catálogo</a>
                <a href="{{ route('panel') }}" class="text-white-50 small text-decoration-none">Panel</a>
                <span class="text-white small">
                    {{ auth()->user()->nombre }}
                    <span class="badge text-bg-secondary">{{ auth()->user()->rol->nombre }}</span>
                </span>

                {{-- CU2: Cerrar sesión (pide confirmación antes de salir) --}}
                <form method="POST" action="{{ route('logout') }}"
                      onsubmit="return confirm('¿Deseas cerrar sesión?')">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">Cerrar sesión</button>
                </form>
            </div>
        @endauth
    </div>
</nav>

<main class="container py-4">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('status_cuenta'))
        <div class="alert alert-info">{{ session('status_cuenta') }}</div>
    @endif
    @if (session('aviso'))
        <div class="alert alert-warning">{{ session('aviso') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @yield('contenido')
</main>

{{-- Foto de respaldo: si el archivo de una foto no se puede cargar (por ejemplo, se perdió del disco), se muestra «Sin foto» en lugar de un ícono roto. --}}
<script>
    (function () {
        var SIN_FOTO = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
            '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300"><rect width="400" height="300" fill="#e9ecef"/>'
            + '<text x="200" y="158" font-family="Arial, sans-serif" font-size="22" fill="#6c757d" text-anchor="middle">Sin foto</text></svg>');

        function reemplazar(img) {
            // No se tocan las imágenes del mapa (sus mosaicos pueden fallar sin conexión) ni las que ya se reemplazaron.
            if (img.dataset.sinFoto || img.closest('.leaflet-container') || !img.getAttribute('src')) { return; }
            img.dataset.sinFoto = '1';
            img.src = SIN_FOTO;
        }

        document.addEventListener('error', function (e) {
            if (e.target && e.target.tagName === 'IMG') { reemplazar(e.target); }
        }, true);

        // Las que ya habían fallado antes de que este script se ejecutara.
        document.querySelectorAll('img').forEach(function (img) {
            if (img.complete && img.naturalWidth === 0) { reemplazar(img); }
        });
    })();
</script>

{{-- Doble envío: al enviar un formulario POST se desactivan sus botones para que un segundo clic no repita la operación (pagos, reservas, contratos...). --}}
<script>
    (function () {
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || form.tagName !== 'FORM' || (form.method || '').toLowerCase() !== 'post') { return; }
            // Se espera a que terminen los demás manejadores (por ejemplo, un confirm() que cancele el envío).
            setTimeout(function () {
                if (e.defaultPrevented) { return; }
                form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach(function (b) { b.disabled = true; });
            }, 0);
        });
        // Al volver con «atrás» la página puede reaparecer con los botones desactivados.
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) { document.querySelectorAll('form button[disabled], form input[type="submit"][disabled]').forEach(function (b) { b.disabled = false; }); }
        });
    })();
</script>

@stack('scripts')
</body>
</html>
