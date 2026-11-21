<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $resultado->titulo }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        .meta { color: #555; margin-bottom: 10px; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #e9ecef; text-align: left; border: 1px solid #bbb; padding: 4px; }
        td { border: 1px solid #ccc; padding: 3px 4px; }
        .num { text-align: right; }
        .resumen { border: 1px solid #bbb; background: #f8f9fa; padding: 6px 8px; margin-bottom: 10px; }
        tfoot td { background: #f1f3f5; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $resultado->titulo }}</h1>
    <div class="meta">
        @foreach ($filtros as $linea) {{ $linea }}<br> @endforeach
        Generado por: {{ $generadoPor }} &middot; Fecha y hora: {{ $fechaHora }}
    </div>

    @if (!empty($resumen))
        <div class="resumen"><strong>Resumen (redactado por IA):</strong> {{ $resumen }}</div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach ($resultado->columnas as $i => $columna)
                    <th class="{{ in_array($i, $resultado->columnasNumericas, true) ? 'num' : '' }}">{{ $columna }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($resultado->filas as $fila)
                <tr>
                    @foreach ($fila as $i => $valor)
                        <td class="{{ in_array($i, $resultado->columnasNumericas, true) ? 'num' : '' }}">{{ $resultado->formatear($i, $valor) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="{{ max(1, count($resultado->columnas) - 1) }}">Total de filas</td>
                <td class="num">{{ $resultado->totales['cantidad'] }}</td>
            </tr>
            @if ($resultado->totales['suma'] !== null)
                <tr>
                    <td colspan="{{ max(1, count($resultado->columnas) - 1) }}">{{ $resultado->totales['etiquetaSuma'] }}</td>
                    <td class="num">{{ number_format($resultado->totales['suma'], 2, '.', ',') }}</td>
                </tr>
            @endif
        </tfoot>
    </table>
</body>
</html>