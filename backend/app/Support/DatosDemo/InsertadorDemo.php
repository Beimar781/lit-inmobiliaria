<?php

namespace App\Support\DatosDemo;

use App\Models\Categoria;
use App\Models\Rol;
use App\Models\Usuario;
use App\Modules\AgendaVisitasPagos\Compartido\NumeradorDeComprobantes;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Inserta en la base el plan de datos demo. Solo INSERTA filas nuevas: no modifica ni borra nada que ya exista.
 * Las referencias (agentes, categorías, otras filas, comprobantes) se resuelven aquí con los ids reales de esta base.
 */
final class InsertadorDemo
{
    /** Tablas con llave autoincremental: se necesita su id para enlazar las filas que dependen de ellas. */
    private const LLAVES = [
        'propietario' => 'idpropietario', 'ubicacion' => 'idubicacion', 'propiedad' => 'idpropiedad', 'cliente' => 'idcliente',
        'reserva' => 'idreserva', 'contrato' => 'idcontrato', 'pago' => 'idpago',
    ];

    /** @var array<string, int> ids de las filas insertadas, por tabla y clave natural */
    private array $ids = [];
    /** @var int[] */
    private array $agentes = [];
    private ?int $asistente = null;
    /** @var array<string, int> */
    private array $categorias = [];
    /** @var array<string, int> */
    private array $conteo = [];
    private int $visitasOmitidas = 0;

    public function __construct(private readonly NumeradorDeComprobantes $numerador)
    {
    }

    /**
     * @param  array<int, array> $ops operaciones del plan
     * @return array<string, int> filas insertadas por tabla
     */
    public function insertar(array $ops): array
    {
        $this->ids = [];
        $this->conteo = [];
        $this->visitasOmitidas = 0;

        DB::transaction(function () use ($ops) {
            $this->preparar();
            foreach ($ops as $op) {
                $this->insertarUna($op);
            }
        });

        return $this->conteo;
    }

    /** Visitas del plan que no se crearon porque el agente ya tenía una real a esa misma hora. */
    public function visitasOmitidas(): int
    {
        return $this->visitasOmitidas;
    }

    private function preparar(): void
    {
        $this->agentes = Usuario::whereHas('rol', fn ($r) => $r->where('nombre', Rol::AGENTE))
            ->where('estado', Usuario::ACTIVO)->orderBy('idusuario')->pluck('idusuario')->map(fn ($id) => (int) $id)->all();

        if ($this->agentes === []) {
            throw new RuntimeException('No hay agentes activos: los datos demo se reparten entre los agentes que ya existen.');
        }

        $this->asistente = Usuario::whereHas('rol', fn ($r) => $r->where('nombre', Rol::ASISTENTE))->where('estado', Usuario::ACTIVO)->orderBy('idusuario')->value('idusuario')
            ?? Usuario::whereHas('rol', fn ($r) => $r->where('nombre', Rol::ADMINISTRADOR))->orderBy('idusuario')->value('idusuario');
        $this->asistente = $this->asistente === null ? null : (int) $this->asistente;

        $this->categorias = Categoria::query()->orderBy('idcategoria')->pluck('idcategoria', 'nombre')->map(fn ($id) => (int) $id)->all();
    }

    private function insertarUna(array $op): void
    {
        $tabla = $op['tabla'];
        $fila = array_map(fn ($valor) => $valor instanceof Ref ? $this->resolver($valor) : $valor, $op['fila']);

        // Un agente no puede estar en dos visitas a la misma hora: si ya tiene una (real o demo) a esa hora, esta se omite.
        if ($op['visita'] !== null && $this->horarioOcupado($op['visita'])) {
            $this->visitasOmitidas++;

            return;
        }

        if (isset(self::LLAVES[$tabla])) {
            $id = (int) DB::table($tabla)->insertGetId($fila, self::LLAVES[$tabla]);
            if ($op['clave'] !== null) {
                $this->ids[$this->llave($tabla, $op['clave'])] = $id;
            }
        } else {
            DB::table($tabla)->insert($fila);
        }

        $this->conteo[$tabla] = ($this->conteo[$tabla] ?? 0) + 1;
    }

    private function resolver(Ref $ref): int|string|null
    {
        $d = $ref->datos;

        return match ($ref->tipo) {
            Ref::AGENTE => $this->agentes[($d['posicion'] - 1) % count($this->agentes)],
            Ref::ASISTENTE => $this->asistente,
            Ref::CATEGORIA => $this->categorias[$d['nombre']] ?? null,
            Ref::FILA => $this->ids[$this->llave($d['tabla'], $d['clave'])]
                ?? throw new RuntimeException("El plan referencia una fila que todavía no existe: {$d['tabla']} " . json_encode($d['clave'], JSON_UNESCAPED_UNICODE)),
            Ref::COMPROBANTE => $this->numerador->siguiente($d['prefijo'], $d['tabla']),
        };
    }

    private function llave(string $tabla, array $clave): string
    {
        return $tabla . '|' . implode('|', array_values($clave));
    }

    private function horarioOcupado(array $visita): bool
    {
        $agente = $this->resolver($visita['agente']);

        return DB::table('visita')
            ->join('propiedad', 'propiedad.idpropiedad', '=', 'visita.idpropiedad')
            ->where('propiedad.idusuario', $agente)
            ->where('visita.fecha', $visita['fecha'])
            ->where('visita.hora', $visita['hora'])
            ->exists();
    }
}
