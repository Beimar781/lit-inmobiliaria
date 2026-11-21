<?php

namespace App\Support\DatosDemo;

use App\Models\Rol;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Convierte el MISMO plan de datos demo en un script SQL para PostgreSQL (por ejemplo, para cargarlo en Railway) y genera
 * el script que borra solo lo demo. Son INSERTs puros dentro de una transacción; no hay ids fijos: las filas se enlazan con
 * subconsultas por correo, nombre o título, y los agentes se reparten entre los que existan en esa base.
 */
final class GeneradorSqlDemo
{
    /** Script que inserta los datos demo. */
    public function poblar(array $ops, CarbonImmutable $hoy): string
    {
        $anio = $hoy->year;
        $filas = [];
        foreach ($ops as $op) {
            $filas[$op['tabla']] = ($filas[$op['tabla']] ?? 0) + 1;
        }

        $sql = [];
        $sql[] = '-- =====================================================================';
        $sql[] = '-- DATOS FICTICIOS (DEMO) PARA PROBAR LOS REPORTES - LIT Inmobiliaria';
        $sql[] = '-- Generado con: php artisan demo:poblar-reportes --sql=<ruta>   (fecha de referencia: ' . $hoy->format('d/m/Y') . ')';
        $sql[] = '-- Solo INSERTA filas nuevas dentro de una transacción; no modifica nada existente.';
        $sql[] = "-- Lo demo se reconoce por: propietarios 'DEMO - ...' y clientes con correo " . MarcasDemo::DOMINIO_CLIENTE . '.';
        $sql[] = '-- Para borrarlo todo: ejecutar quitar_datos_demo.sql. Para PostgreSQL.';
        $sql[] = '-- Filas: ' . implode(', ', array_map(fn ($t, $n) => "{$t} {$n}", array_keys($filas), $filas));
        $sql[] = '-- =====================================================================';
        $sql[] = '';
        $sql[] = 'BEGIN;';
        $sql[] = '';
        $sql[] = '-- Aborta si ya existen datos demo (para no duplicarlos) o si no hay agentes.';
        $sql[] = 'DO $$';
        $sql[] = 'BEGIN';
        $sql[] = "    IF EXISTS (SELECT 1 FROM propietario WHERE nombre LIKE '" . MarcasDemo::PREFIJO_PROPIETARIO . "%')";
        $sql[] = "       OR EXISTS (SELECT 1 FROM cliente WHERE email LIKE '%" . MarcasDemo::DOMINIO_CLIENTE . "') THEN";
        $sql[] = "        RAISE EXCEPTION 'Ya existen datos demo. Ejecuta primero quitar_datos_demo.sql si quieres volver a crearlos.';";
        $sql[] = '    END IF;';
        $sql[] = '    IF NOT EXISTS (SELECT 1 FROM usuario u JOIN rol r ON r.idrol = u.idrol';
        $sql[] = "                   WHERE r.nombre = " . $this->q(Rol::AGENTE) . " AND u.estado = 'ACTIVO') THEN";
        $sql[] = "        RAISE EXCEPTION 'No hay agentes activos: los datos demo se reparten entre los agentes que ya existen.';";
        $sql[] = '    END IF;';
        $sql[] = 'END $$;';
        $sql[] = '';
        $sql[] = '-- Agentes activos numerados 1, 2, 3...: cada dato demo usa uno según su posición (se reparte en círculo).';
        $sql[] = 'CREATE TEMP TABLE demo_agentes ON COMMIT DROP AS';
        $sql[] = 'SELECT (row_number() OVER (ORDER BY u.idusuario))::int AS orden, u.idusuario';
        $sql[] = 'FROM usuario u JOIN rol r ON r.idrol = u.idrol';
        $sql[] = "WHERE r.nombre = " . $this->q(Rol::AGENTE) . " AND u.estado = 'ACTIVO';";

        $actual = null;
        foreach ($ops as $op) {
            if ($op['tabla'] !== $actual) {
                $actual = $op['tabla'];
                $sql[] = '';
                $sql[] = '-- ' . $actual;
            }
            $sql[] = $this->insertar($op, $anio);
        }

        $sql[] = '';
        $sql[] = 'COMMIT;';
        $sql[] = '';

        return implode("\n", $sql);
    }

    /** Script que borra solo lo demo, en el orden de las llaves foráneas. */
    public function quitar(): string
    {
        $props = "SELECT idpropiedad FROM demo_props";
        $clientes = "SELECT idcliente FROM demo_clientes";
        $deDemo = "idpropiedad IN ({$props}) OR idcliente IN ({$clientes})";

        $sql = [
            '-- =====================================================================',
            '-- BORRA TODO LO DEMO de LIT Inmobiliaria (y solo lo demo).',
            "-- Lo demo: propietarios 'DEMO - ...', sus propiedades y los clientes con correo " . MarcasDemo::DOMINIO_CLIENTE . '.',
            '-- También se borra lo que cuelgue de esas propiedades o clientes (visitas, reservas, contratos, pagos...).',
            '-- Generado con: php artisan demo:poblar-reportes --quitar --sql=<ruta>. Para PostgreSQL.',
            '-- Nota: si alguien subió fotos a una propiedad demo, se borran sus filas pero no los archivos del disco.',
            '-- =====================================================================',
            '',
            'BEGIN;',
            '',
            'CREATE TEMP TABLE demo_props ON COMMIT DROP AS',
            "SELECT idpropiedad, idubicacion FROM propiedad WHERE idpropietario IN (SELECT idpropietario FROM propietario WHERE nombre LIKE '" . MarcasDemo::PREFIJO_PROPIETARIO . "%');",
            'CREATE TEMP TABLE demo_clientes ON COMMIT DROP AS',
            "SELECT idcliente FROM cliente WHERE email LIKE '%" . MarcasDemo::DOMINIO_CLIENTE . "';",
            'CREATE TEMP TABLE demo_contratos ON COMMIT DROP AS',
            "SELECT idcontrato FROM contrato WHERE {$deDemo};",
            'CREATE TEMP TABLE demo_reservas ON COMMIT DROP AS',
            "SELECT idreserva FROM reserva WHERE {$deDemo};",
            '',
            'DELETE FROM tipopago WHERE idpago IN (SELECT idpago FROM pago WHERE idcontrato IN (SELECT idcontrato FROM demo_contratos));',
            'DELETE FROM pago WHERE idcontrato IN (SELECT idcontrato FROM demo_contratos);',
            "DELETE FROM resena WHERE {$deDemo};",
            'DELETE FROM contrato WHERE idcontrato IN (SELECT idcontrato FROM demo_contratos);',
            'DELETE FROM movimientoreserva WHERE idreserva IN (SELECT idreserva FROM demo_reservas);',
            'DELETE FROM reserva WHERE idreserva IN (SELECT idreserva FROM demo_reservas);',
            "DELETE FROM visita WHERE {$deDemo};",
            "DELETE FROM seguimiento WHERE idcliente IN ({$clientes});",
            "DELETE FROM inquilino WHERE idcliente IN ({$clientes});",
            "DELETE FROM interes WHERE {$deDemo};",
            "DELETE FROM historial WHERE idpropiedad IN ({$props});",
            "DELETE FROM imagen WHERE idpropiedad IN ({$props});",
            "DELETE FROM propiedad WHERE idpropiedad IN ({$props});",
            'DELETE FROM ubicacion WHERE idubicacion IN (SELECT idubicacion FROM demo_props WHERE idubicacion IS NOT NULL);',
            "DELETE FROM cliente WHERE idcliente IN ({$clientes});",
            "DELETE FROM propietario WHERE nombre LIKE '" . MarcasDemo::PREFIJO_PROPIETARIO . "%';",
            '',
            'COMMIT;',
            '',
        ];

        return implode("\n", $sql);
    }

    // ------------------------------------------------------------------------------------------------ interno

    private function insertar(array $op, int $anio): string
    {
        $columnas = implode(', ', array_keys($op['fila']));
        $valores = array_map(fn ($v) => $this->valor($v, $anio), $op['fila']);

        // Un agente no puede estar en dos visitas a la misma hora: si ya tiene una (real o demo) a esa hora, esta no se inserta.
        if ($op['visita'] !== null) {
            $agente = $this->referencia($op['visita']['agente'], $anio);

            return "INSERT INTO {$op['tabla']} ({$columnas}) SELECT " . implode(', ', $valores)
                . " WHERE NOT EXISTS (SELECT 1 FROM visita v JOIN propiedad p ON p.idpropiedad = v.idpropiedad WHERE p.idusuario = {$agente}"
                . " AND v.fecha = " . $this->q($op['visita']['fecha']) . ' AND v.hora = ' . $this->q($op['visita']['hora']) . ');';
        }

        return "INSERT INTO {$op['tabla']} ({$columnas}) VALUES (" . implode(', ', $valores) . ');';
    }

    private function valor(mixed $valor, int $anio): string
    {
        return match (true) {
            $valor instanceof Ref => $this->referencia($valor, $anio),
            $valor === null => 'NULL',
            is_bool($valor) => $valor ? 'TRUE' : 'FALSE',
            is_int($valor) => (string) $valor,
            is_float($valor) => rtrim(rtrim(number_format($valor, 6, '.', ''), '0'), '.') ?: '0',
            default => $this->q((string) $valor),
        };
    }

    private function referencia(Ref $ref, int $anio): string
    {
        $d = $ref->datos;

        return match ($ref->tipo) {
            Ref::AGENTE => '(SELECT idusuario FROM demo_agentes WHERE orden = ((' . $d['posicion'] . ' - 1) % (SELECT count(*) FROM demo_agentes)) + 1)',
            Ref::ASISTENTE => '(COALESCE((SELECT u.idusuario FROM usuario u JOIN rol r ON r.idrol = u.idrol WHERE r.nombre = ' . $this->q(Rol::ASISTENTE)
                . " AND u.estado = 'ACTIVO' ORDER BY u.idusuario LIMIT 1), (SELECT u.idusuario FROM usuario u JOIN rol r ON r.idrol = u.idrol WHERE r.nombre = "
                . $this->q(Rol::ADMINISTRADOR) . ' ORDER BY u.idusuario LIMIT 1)))',
            Ref::CATEGORIA => '(SELECT idcategoria FROM categoria WHERE nombre = ' . $this->q($d['nombre']) . ' ORDER BY idcategoria LIMIT 1)',
            Ref::FILA => $this->fila($d['tabla'], $d['clave']),
            Ref::COMPROBANTE => $this->comprobante($d['prefijo'], $d['tabla'], $anio),
        };
    }

    /** Siguiente número correlativo del año: sigue la secuencia que ya exista en esa base. */
    private function comprobante(string $prefijo, string $tabla, int $anio): string
    {
        $inicio = "{$prefijo}-{$anio}-";

        return "('{$inicio}' || lpad((COALESCE((SELECT max(substring(comprobante FROM " . (strlen($inicio) + 1) . ")::int) FROM {$tabla} WHERE comprobante LIKE '{$inicio}%'), 0) + 1)::text, 6, '0'))";
    }

    private function fila(string $tabla, array $c): string
    {
        $cliente = fn () => '(SELECT idcliente FROM cliente WHERE email = ' . $this->q($c['cliente'] ?? $c['email']) . ')';
        $propiedad = fn () => '(SELECT idpropiedad FROM propiedad WHERE titulo = ' . $this->q($c['propiedad'] ?? $c['titulo'])
            . " AND idpropietario IN (SELECT idpropietario FROM propietario WHERE nombre LIKE '" . MarcasDemo::PREFIJO_PROPIETARIO . "%'))";
        $contrato = fn () => '(SELECT idcontrato FROM contrato WHERE idcliente = ' . $cliente() . ' AND idpropiedad = ' . $propiedad() . ')';

        return match ($tabla) {
            'propietario' => '(SELECT idpropietario FROM propietario WHERE nombre = ' . $this->q($c['nombre']) . ')',
            'ubicacion' => '(SELECT max(idubicacion) FROM ubicacion WHERE direccion = ' . $this->q($c['direccion']) . ')',
            'propiedad' => $propiedad(),
            'cliente' => $cliente(),
            'reserva' => '(SELECT idreserva FROM reserva WHERE idcliente = ' . $cliente() . ' AND idpropiedad = ' . $propiedad() . ')',
            'contrato' => $contrato(),
            'pago' => '(SELECT idpago FROM pago WHERE idcontrato = ' . $contrato() . ' AND fechapago = ' . $this->q($c['fechapago']) . ')',
            default => throw new RuntimeException("No se sabe enlazar una fila de {$tabla}."),
        };
    }

    private function q(string $texto): string
    {
        return "'" . str_replace("'", "''", $texto) . "'";
    }
}
