<?php

namespace App\Modules\ReportesAdministracion\CU20_GenerarReportes;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use App\Models\Propiedad;
use App\Models\Visita;
use Illuminate\Support\Carbon;

/**
 * Lo que se pide en un reporte: tipo, rango de fechas, estado, agente, zona y agrupación.
 * Los valores se validan contra listas permitidas, así el constructor de reportes (y la IA del prompt siguiente)
 * solo reciben criterios seguros. Una fecha mal escrita no es un error grave: marca el rango como no válido.
 */
final class CriteriosDeReporte
{
    public const TIPOS = [
        'propiedades' => 'Propiedades',
        'clientes' => 'Clientes',
        'visitas' => 'Visitas',
        'contratos' => 'Contratos',
        'pagos' => 'Pagos',
    ];

    public const AGRUPACIONES = [
        'ninguna' => 'Sin agrupar',
        'estado' => 'Por estado',
        'categoria' => 'Por categoría',
        'agente' => 'Por agente',
        'mes' => 'Por mes',
    ];

    public function __construct(
        public readonly string $tipo,
        public readonly ?Carbon $desde = null,
        public readonly ?Carbon $hasta = null,
        public readonly ?string $estado = null,
        public readonly ?int $idagente = null,
        public readonly ?string $zona = null,
        public readonly string $agrupacion = 'ninguna',
        public readonly bool $fechaMalFormada = false,
    ) {
    }

    /**
     * Arma los criterios desde valores de texto (querystring o IA). Lanza CriteriosNoValidos si el tipo, el estado
     * o la agrupación no están en la lista.
     */
    public static function desdeArreglo(array $datos): self
    {
        $tipo = self::texto($datos['tipo'] ?? null);
        if (! array_key_exists($tipo, self::TIPOS)) {
            throw new CriteriosNoValidos('Tipo de reporte no válido.');
        }

        $agrupacion = self::texto($datos['agrupacion'] ?? null) ?: 'ninguna';
        if (! array_key_exists($agrupacion, self::AGRUPACIONES) || ($agrupacion === 'categoria' && $tipo !== 'propiedades')) {
            throw new CriteriosNoValidos('Agrupación no válida.');
        }

        $estado = self::texto($datos['estado'] ?? null) ?: null;
        if ($estado !== null && ! in_array($estado, self::estadosDe($tipo), true)) {
            throw new CriteriosNoValidos('Estado no válido.');
        }

        $malFormada = false;
        $desde = self::fecha($datos['desde'] ?? null, $malFormada);
        $hasta = self::fecha($datos['hasta'] ?? null, $malFormada);

        $agente = self::texto($datos['idagente'] ?? null);
        $zona = self::texto($datos['zona'] ?? null);

        return new self(
            $tipo,
            $desde,
            $hasta,
            $estado,
            ctype_digit($agente) && strlen($agente) <= 9 ? (int) $agente : null,
            in_array($tipo, ['propiedades', 'visitas'], true) && $zona !== '' ? mb_substr($zona, 0, 150) : null,
            $agrupacion,
            $malFormada,
        );
    }

    /** Estados que se pueden pedir según el tipo. */
    public static function estadosDe(string $tipo): array
    {
        return match ($tipo) {
            'propiedades' => Propiedad::ESTADOS,
            'clientes' => array_keys(Cliente::ESTADOS),
            'visitas' => array_keys(Visita::ESTADOS),
            'contratos' => array_keys(Contrato::ESTADOS),
            'pagos' => array_keys(Pago::ESTADOS),
            default => [],
        };
    }

    /** Estados con su etiqueta legible, para el formulario: [clave => texto]. */
    public static function etiquetasDeEstado(string $tipo): array
    {
        $etiquetas = match ($tipo) {
            'clientes' => Cliente::ESTADOS,
            'visitas' => Visita::ESTADOS,
            'contratos' => Contrato::ESTADOS,
            'pagos' => Pago::ESTADOS,
            default => [],
        };

        return $etiquetas ?: array_combine(self::estadosDe($tipo), array_map(fn ($e) => ucfirst(strtolower($e)), self::estadosDe($tipo)));
    }

    /** Desde y hasta, ya con fechas válidas, deben estar en orden; una fecha mal escrita también es un rango no válido. */
    public function rangoValido(): bool
    {
        if ($this->fechaMalFormada) {
            return false;
        }

        return ! ($this->desde && $this->hasta && $this->desde->gt($this->hasta));
    }

    /** Los criterios como parámetros de URL (para exportar con los mismos filtros). */
    public function aParametros(): array
    {
        return array_filter([
            'tipo' => $this->tipo,
            'desde' => $this->desde?->format('Y-m-d'),
            'hasta' => $this->hasta?->format('Y-m-d'),
            'estado' => $this->estado,
            'idagente' => $this->idagente,
            'zona' => $this->zona,
            'agrupacion' => $this->agrupacion !== 'ninguna' ? $this->agrupacion : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** Líneas legibles con los filtros aplicados (para la pantalla y los archivos exportados). */
    public function descripcion(?string $nombreAgente = null): array
    {
        $lineas = ['Tipo: ' . self::TIPOS[$this->tipo]];

        if ($this->desde || $this->hasta) {
            $lineas[] = 'Fechas: ' . ($this->desde?->format('d/m/Y') ?? 'inicio') . ' al ' . ($this->hasta?->format('d/m/Y') ?? 'hoy');
        }
        if ($this->estado) {
            $lineas[] = 'Estado: ' . (self::etiquetasDeEstado($this->tipo)[$this->estado] ?? $this->estado);
        }
        if ($this->idagente) {
            $lineas[] = 'Agente: ' . ($nombreAgente ?? "N.º {$this->idagente}");
        }
        if ($this->zona) {
            $lineas[] = 'Zona: ' . $this->zona;
        }
        if ($this->agrupacion !== 'ninguna') {
            $lineas[] = 'Agrupado ' . strtolower(self::AGRUPACIONES[$this->agrupacion]);
        }

        return $lineas;
    }

    private static function texto(mixed $valor): string
    {
        return is_string($valor) || is_int($valor) ? trim((string) $valor) : '';
    }

    /** Fecha Y-m-d real (2026-02-31 no lo es); si viene algo y no es una fecha, marca $malFormada. */
    private static function fecha(mixed $valor, bool &$malFormada): ?Carbon
    {
        $texto = self::texto($valor);

        if ($texto === '') {
            $malFormada = $malFormada || is_array($valor);

            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto)) {
            $malFormada = true;

            return null;
        }

        try {
            $fecha = Carbon::createFromFormat('!Y-m-d', $texto);
        } catch (\Throwable) {
            $fecha = false;
        }

        if (! $fecha || $fecha->format('Y-m-d') !== $texto) {
            $malFormada = true;

            return null;
        }

        return $fecha;
    }
}