<?php

namespace App\Modules\ReportesAdministracion\CU20_GenerarReportes;

use App\Models\Rol;
use App\Models\Ubicacion;
use App\Models\Usuario;
use Illuminate\Support\Carbon;

/**
 * Convierte la frase del usuario en CriteriosDeReporte. La IA solo propone; aquí se VALIDA todo con las mismas listas y el
 * mismo rol que el reporte manual. Lo que no sea válido se descarta y no se consulta nada. Las fechas relativas las resuelve
 * el sistema (hora de Bolivia), no la IA.
 */
class InterpretadorDeSolicitud
{
    public const MAXIMO = 300;

    private const PERIODOS = ['ultimo_mes', 'este_mes', 'ultimos_7_dias', 'este_anio'];

    public function __construct(private readonly ServicioDeIa $ia)
    {
    }

    /** Texto fijo que se le da a la IA: solo listas permitidas, nunca datos de la base. */
    public static function instruccion(array $tiposPermitidos): string
    {
        $estados = collect($tiposPermitidos)
            ->map(fn ($t) => "- {$t}: " . implode(', ', CriteriosDeReporte::estadosDe($t)))
            ->implode("\n");

        return "Eres un intérprete de solicitudes de reportes de una inmobiliaria. Responde SOLO un objeto JSON con las claves "
            . "tipo, periodo, filtros {desde, hasta, estado, zona, agente} y agrupacion. "
            . "Si la solicitud no corresponde a ningún reporte, responde {\"tipo\": null}. No inventes valores.\n"
            . "tipo debe ser uno de: " . implode(', ', $tiposPermitidos) . ".\n"
            . "Estados permitidos por tipo (escríbelos exactamente así):\n{$estados}\n"
            . "agrupacion debe ser una de: ninguna, estado, categoria (solo propiedades), agente, mes.\n"
            . "periodo (opcional) debe ser una de: " . implode(', ', self::PERIODOS) . "; no calcules fechas tú. "
            . "desde y hasta solo si la solicitud da fechas exactas, en formato AAAA-MM-DD. "
            . "Usa null en lo que no se pida.";
    }

    /** @throws InterpretacionFallida */
    public function interpretar(string $solicitud, Usuario $actor): CriteriosDeReporte
    {
        $tipos = ConstructorDeReportes::tiposPermitidos($actor);
        $propuesta = $this->ia->interpretar($solicitud, $tipos);

        if ($propuesta === null) {
            throw new InterpretacionFallida(InterpretacionFallida::SERVICIO);
        }

        $tipo = $this->valor($propuesta['tipo'] ?? null);
        if ($tipo === null || ! in_array($tipo, $tipos, true)) {
            throw new InterpretacionFallida(InterpretacionFallida::NO_RECONOCIDO);
        }

        $filtros = is_array($propuesta['filtros'] ?? null) ? $propuesta['filtros'] : [];
        [$desde, $hasta] = $this->fechas($this->valor($propuesta['periodo'] ?? null), $filtros);

        try {
            $criterios = CriteriosDeReporte::desdeArreglo([
                'tipo' => $tipo,
                'desde' => $desde,
                'hasta' => $hasta,
                'estado' => $this->estado($this->valor($filtros['estado'] ?? null)),
                'zona' => $this->zona($this->valor($filtros['zona'] ?? null)),
                'idagente' => $actor->tieneRol(Rol::ADMINISTRADOR) ? $this->agente($this->valor($filtros['agente'] ?? null)) : null,
                'agrupacion' => $this->valor($propuesta['agrupacion'] ?? null) ?? 'ninguna',
            ]);
        } catch (CriteriosNoValidos) {
            throw new InterpretacionFallida(InterpretacionFallida::NO_RECONOCIDO);
        }

        if (! $criterios->rangoValido() || ! ConstructorDeReportes::permite($actor, $criterios)) {
            throw new InterpretacionFallida(InterpretacionFallida::NO_RECONOCIDO);
        }

        return $criterios;
    }

    /** Texto limpio; "null", "ninguno" y vacío cuentan como "no se pidió". */
    private function valor(mixed $v): ?string
    {
        if (! is_string($v)) {
            return null;
        }
        $v = trim($v);

        return in_array(mb_strtolower($v), ['', 'null', 'none', 'ninguno', 'ninguna'], true) ? null : $v;
    }

    private function estado(?string $estado): ?string
    {
        return $estado === null ? null : strtoupper(str_replace(' ', '_', $estado));
    }

    /** Fechas relativas con la fecha de hoy en Bolivia; si no hay periodo, las fechas exactas que dio la IA. */
    private function fechas(?string $periodo, array $filtros): array
    {
        $hoy = Carbon::now('America/La_Paz')->startOfDay();

        return match ($periodo) {
            'ultimo_mes' => [$hoy->copy()->subMonth()->format('Y-m-d'), $hoy->format('Y-m-d')],
            'este_mes' => [$hoy->copy()->startOfMonth()->format('Y-m-d'), $hoy->format('Y-m-d')],
            'ultimos_7_dias' => [$hoy->copy()->subDays(7)->format('Y-m-d'), $hoy->format('Y-m-d')],
            'este_anio' => [$hoy->copy()->startOfYear()->format('Y-m-d'), $hoy->format('Y-m-d')],
            default => [$this->valor($filtros['desde'] ?? null), $this->valor($filtros['hasta'] ?? null)],
        };
    }

    /** La zona debe existir tal cual en la base (sin distinguir mayúsculas); si no, se descarta el filtro. */
    private function zona(?string $zona): ?string
    {
        if ($zona === null) {
            return null;
        }

        return Ubicacion::whereNotNull('zona')->distinct()->pluck('zona')
            ->first(fn ($z) => mb_strtolower($z) === mb_strtolower($zona));
    }

    /** El agente se identifica por su nombre solo si coincide con uno; la IA nunca ve la lista de agentes. */
    private function agente(?string $nombre): ?int
    {
        if ($nombre === null) {
            return null;
        }

        $ids = Usuario::whereHas('rol', fn ($r) => $r->where('nombre', Rol::AGENTE))
            ->whereLike('nombre', '%' . addcslashes($nombre, '%_\\') . '%')
            ->pluck('idusuario');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }
}