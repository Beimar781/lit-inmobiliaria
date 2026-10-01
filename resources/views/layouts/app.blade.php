<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'LIT Inmobiliaria')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="{{ url('/') }}">LIT Inmobiliaria</a>

        @auth
            <div class="d-flex align-items-center gap-3">
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

    @yield('contenido')
</main>

</body>
</html>
