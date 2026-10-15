<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'LIT Inmobiliaria')</title>
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    @stack('estilos')
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="{{ url('/') }}">LIT Inmobiliaria</a>

        @auth
            <div class="d-flex align-items-center gap-3">
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
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @yield('contenido')
</main>

@stack('scripts')
</body>
</html>
