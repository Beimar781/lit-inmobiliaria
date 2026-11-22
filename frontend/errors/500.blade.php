<!DOCTYPE html>
{{-- Página independiente (sin el layout): si el error vino de la base de datos o de la sesión, el layout también fallaría. --}}
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error del servidor - LIT Inmobiliaria</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; background: #f8f9fa; color: #212529; margin: 0; }
        .caja { max-width: 520px; margin: 14vh auto; padding: 0 1rem; text-align: center; }
        .codigo { font-size: 5rem; font-weight: 700; color: #6c757d; margin: 0; }
        p { color: #6c757d; }
        a { display: inline-block; background: #0d6efd; color: #fff; text-decoration: none; padding: .5rem 1rem; border-radius: .375rem; }
    </style>
</head>
<body>
<div class="caja">
    <p class="codigo">500</p>
    <h1>Algo salió mal</h1>
    <p>Ocurrió un error inesperado en el servidor. Ya quedó registrado; intenta de nuevo en unos minutos.</p>
    <a href="/">Volver al inicio</a>
</div>
</body>
</html>
