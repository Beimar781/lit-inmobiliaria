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

    private const PERIODOS = [
        'hoy', 'ayer', 'esta_semana', 'semana_pasada', 'ultimos_7_dias', 'este_mes', 'mes_pasado', 'ultimo_mes',
        'ultimos_3_meses', 'ultimos_6_meses', 'este_anio', 'anio_pasado',
    ];

    private const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    /** Expresiones de tiempo que, si aparecen en la frase, obligan a que haya un período (si no, no se genera sin filtro). */
    private const EXPRESION_DE_TIEMPO = '/\b(hoy|ayer|anteayer|semana|quincena|trimestre|semestre|hace \d+|ultimos? \d+|ultimo (mes|ano|anio)|(mes|ano|anio) pasado|este (mes|ano|anio)|enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)\b/';

    public function __construct(private readonly ServicioDeIa $ia)
    {
    }

    /** Hoy en Bolivia (la IA no sabe qué día es: se le dice, y el sistema calcula y corrige las fechas). */
    public static function hoy(): Carbon
    {
        return Carbon::now('America/La_Paz')->startOfDay();
    }

    /** Texto fijo que se le da a la IA: solo listas permitidas y la fecha de hoy, nunca datos de la base. */
    public static function instruccion(array $tiposPermitidos, ?Carbon $hoy = null): string
    {
        $hoy ??= self::hoy();
        $estados = collect($tiposPermitidos)
            ->map(fn ($t) => "- {$t}: " . implode(', ', CriteriosDeReporte::estadosDe($t)))
            ->implode("\n");

        return 'Hoy es ' . $hoy->format('Y-m-d') . ' (' . self::DIAS[$hoy->dayOfWeek] . '), en la zona horaria America/La_Paz; el año actual es ' . $hoy->year . ". "
            . "Si la solicitud da fechas sin año, usa el año actual (o el anterior si esa fecha todavía no llegó); nunca inventes otro año.\n"
            . "Eres un intérprete de solicitudes de reportes de una inmobiliaria. Responde SOLO un objeto JSON con las claves "
            . "tipo, periodo, filtros {desde, hasta, estado, zona, agente} y agrupacion. "
            . "Si la solicitud no corresponde a ningún reporte, responde {\"tipo\": null}. No inventes valores.\n"
            . "tipo debe ser uno de: " . implode(', ', $tiposPermitidos) . ".\n"
            . "Estados permitidos por tipo (escríbelos exactamente así):\n{$estados}\n"
            . "agrupacion debe ser una de: ninguna, estado, categoria (solo propiedades), agente, mes.\n"
            . "periodo (opcional) debe ser una de: " . implode(', ', self::PERIODOS) . "; úsalo SOLO para expresiones relativas "
            . "(hoy, ayer, esta semana, el mes pasado, los últimos 3 meses, este año...) y no calcules fechas tú en ese caso. "
            . "Si la solicitud nombra un mes (por ejemplo \"septiembre\" o \"enero\") o da fechas concretas, NO uses periodo: pon desde y hasta "
            . "(primer y último día, formato AAAA-MM-DD, con el año actual salvo que el usuario diga otro). "
            . "Usa null en lo que no se pida.";
    }

    /**
     * Valida la propuesta de la IA con las mismas reglas que el reporte manual (listas, rol y rango de fechas).
     * Si todo es válido devuelve los criterios; si no, lanza InterpretacionFallida con el motivo y lo que sí se entendió.
     *
     * @throws InterpretacionFallida
     */
    public function interpretar(string $solicitud, Usuario $actor): CriteriosDeReporte
    {
        $tipos = ConstructorDeReportes::tiposPermitidos($actor);
        $propuesta = $this->ia->interpretar($solicitud, $tipos);

        if ($propuesta === null) {
            throw new InterpretacionFallida(InterpretacionFallida::SERVICIO, mostrarPanel: false);
        }

        $datos = $this->aDatos($propuesta, $actor, $solicitud);

        if (! array_key_exists((string) $datos['tipo'], CriteriosDeReporte::TIPOS)) {
            throw new InterpretacionFallida(InterpretacionFallida::NO_RECONOCIDO);
        }

        try {
            $criterios = CriteriosDeReporte::desdeArreglo($datos);
        } catch (CriteriosNoValidos) {
            // Algún valor inventado (estado, agrupación...): se muestra lo que sí es válido para que lo corrija.
            $rescatados = $this->rescatar($datos);

            throw new InterpretacionFallida(InterpretacionFallida::NO_RECONOCIDO, $rescatados?->descripcion() ?? [], $this->paraFormulario($rescatados, $tipos));
        }

        if (! ConstructorDeReportes::permite($actor, $criterios)) {
            throw new InterpretacionFallida(InterpretacionFallida::SIN_PERMISO, $criterios->descripcion());
        }

        if (! $criterios->rangoValido()) {
            throw new InterpretacionFallida(InterpretacionFallida::RANGO, $criterios->descripcion(), $criterios->aParametros());
        }

        // Si la frase habla de un período ("últimos 3 meses", "de septiembre"...) y no quedó ninguna fecha, no se genera
        // un reporte sin filtro en silencio: se pide corregir las fechas.
        if ($criterios->desde === null && $criterios->hasta === null && $this->mencionaUnPeriodo($solicitud)) {
            throw new InterpretacionFallida(InterpretacionFallida::PERIODO, $criterios->descripcion(), $criterios->aParametros());
        }

        return $criterios;
    }

    private function mencionaUnPeriodo(string $solicitud): bool
    {
        $texto = strtr(mb_strtolower($solicitud), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);

        return (bool) preg_match(self::EXPRESION_DE_TIEMPO, $texto);
    }

    /** Pasa la propuesta de la IA a los datos que entiende CriteriosDeReporte (el período lo resuelve el sistema, no la IA). */
    private function aDatos(array $propuesta, Usuario $actor, string $solicitud = ''): array
    {
        $filtros = is_array($propuesta['filtros'] ?? null) ? $propuesta['filtros'] : [];
        $tipo = $this->valor($propuesta['tipo'] ?? null);
        [$desde, $hasta] = $this->fechas($this->valor($propuesta['periodo'] ?? null), $filtros);

        // Las fechas exactas que da la IA se corrigen si traen un año absurdo (no si vienen de un período que calculó el sistema).
        if ($this->valor($propuesta['periodo'] ?? null) === null || ! in_array($this->valor($propuesta['periodo'] ?? null), self::PERIODOS, true)) {
            [$desde, $hasta] = $this->corregirAnio($desde, $hasta, $solicitud, $tipo);
        }

        return [
            'tipo' => $this->valor($propuesta['tipo'] ?? null),
            'desde' => $desde,
            'hasta' => $hasta,
            'estado' => $this->estado($this->valor($filtros['estado'] ?? null)),
            'zona' => $this->zona($this->valor($filtros['zona'] ?? null)),
            'idagente' => $actor->tieneRol(Rol::ADMINISTRADOR) ? $this->agente($this->valor($filtros['agente'] ?? null)) : null,
            'agrupacion' => $this->valor($propuesta['agrupacion'] ?? null) ?? 'ninguna',
        ];
    }

    /** Quita de a uno los valores no válidos (estado y luego agrupación) hasta poder armar unos criterios. */
    private function rescatar(array $datos): ?CriteriosDeReporte
    {
        foreach ([[], ['estado'], ['estado', 'agrupacion']] as $quitar) {
            $intento = $datos;
            foreach ($quitar as $campo) {
                $intento[$campo] = $campo === 'agrupacion' ? 'ninguna' : null;
            }
            try {
                return CriteriosDeReporte::desdeArreglo($intento);
            } catch (CriteriosNoValidos) {
            }
        }

        return null;
    }

    /** Solo se precarga en el formulario lo que este rol puede pedir. */
    private function paraFormulario(?CriteriosDeReporte $criterios, array $tiposPermitidos): array
    {
        return $criterios !== null && in_array($criterios->tipo, $tiposPermitidos, true) ? $criterios->aParametros() : [];
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
        $hoy = self::hoy();
        $f = fn (Carbon $fecha) => $fecha->format('Y-m-d');

        return match ($periodo) {
            'hoy' => [$f($hoy), $f($hoy)],
            'ayer' => [$f($hoy->copy()->subDay()), $f($hoy->copy()->subDay())],
            'esta_semana' => [$f($hoy->copy()->startOfWeek()), $f($hoy)],
            'semana_pasada' => [$f($hoy->copy()->subWeek()->startOfWeek()), $f($hoy->copy()->subWeek()->endOfWeek())],
            'ultimos_7_dias' => [$f($hoy->copy()->subDays(7)), $f($hoy)],
            'este_mes' => [$f($hoy->copy()->startOfMonth()), $f($hoy)],
            'mes_pasado' => [$f($hoy->copy()->subMonthNoOverflow()->startOfMonth()), $f($hoy->copy()->subMonthNoOverflow()->endOfMonth())],
            'ultimo_mes' => [$f($hoy->copy()->subMonth()), $f($hoy)],
            'ultimos_3_meses' => [$f($hoy->copy()->subMonthsNoOverflow(3)), $f($hoy)],
            'ultimos_6_meses' => [$f($hoy->copy()->subMonthsNoOverflow(6)), $f($hoy)],
            'este_anio' => [$f($hoy->copy()->startOfYear()), $f($hoy)],
            'anio_pasado' => [$f($hoy->copy()->subYear()->startOfYear()), $f($hoy->copy()->subYear()->endOfYear())],
            default => [$this->valor($filtros['desde'] ?? null), $this->valor($filtros['hasta'] ?? null)],
        };
    }

    /**
     * La IA a veces devuelve el año de su entrenamiento (2023, 2024...). Una fecha exacta con más de un año de distancia a hoy
     * se pasa al año actual (o al anterior si todavía no llegó, salvo en visitas, que pueden ser futuras), a menos que el
     * usuario haya escrito el año en su frase. Si queda "desde" después de "hasta", "desde" retrocede un año.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function corregirAnio(?string $desde, ?string $hasta, string $solicitud, ?string $tipo): array
    {
        if (preg_match('/\b(19|20)\d{2}\b/', $solicitud)) {
            return [$desde, $hasta]; // el usuario dijo el año: se respeta
        }

        $hoy = self::hoy();
        $cambio = false;
        $corregir = function (?string $texto) use ($hoy, $tipo, &$cambio): ?Carbon {
            if ($texto === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto)) {
                return null; // vacía o mal escrita: la valida CriteriosDeReporte
            }
            try {
                $fecha = Carbon::createFromFormat('!Y-m-d', $texto, 'America/La_Paz');
            } catch (\Throwable) {
                return null;
            }
            if (! $fecha || $fecha->format('Y-m-d') !== $texto) {
                return null;
            }
            if ($fecha->lt($hoy->copy()->subYear()) || $fecha->gt($hoy->copy()->addYear())) {
                $cambio = true;
                $fecha = $fecha->copy()->year($hoy->year);
                if ($fecha->gt($hoy) && $tipo !== 'visitas') {
                    $fecha = $fecha->copy()->subYear();
                }
            }

            return $fecha;
        };

        $nuevoDesde = $corregir($desde);
        $nuevoHasta = $corregir($hasta);
        if ($cambio && $nuevoDesde && $nuevoHasta && $nuevoDesde->gt($nuevoHasta)) {
            $nuevoDesde = $nuevoDesde->copy()->subYear();
        }

        return [$nuevoDesde?->format('Y-m-d') ?? $desde, $nuevoHasta?->format('Y-m-d') ?? $hasta];
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