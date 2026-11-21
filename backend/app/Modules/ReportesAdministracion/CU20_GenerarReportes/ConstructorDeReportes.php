<?php

namespace App\Modules\ReportesAdministracion\CU20_GenerarReportes;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use App\Models\Propiedad;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Visita;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Arma la tabla de un reporte (solo lectura). Recibe CriteriosDeReporte ya validados y NO sabe nada de IA ni de pantallas.
 * Aplica el alcance según el rol de quien lo pide: el Agente solo ve lo suyo y cada rol solo los tipos que le corresponden.
 * Todos los filtros entran por parámetros de Eloquent (nunca SQL armado con texto del usuario).
 */
class ConstructorDeReportes
{
    public const MAXIMO_FILAS = 5000;

    /** Qué tipos de reporte puede pedir cada rol. */
    public static function tiposPermitidos(Usuario $actor): array
    {
        return match (true) {
            $actor->tieneRol(Rol::ADMINISTRADOR) => array_keys(CriteriosDeReporte::TIPOS),
            $actor->tieneRol(Rol::AGENTE) => ['propiedades', 'clientes', 'visitas'],
            $actor->tieneRol(Rol::ASISTENTE) => ['contratos', 'pagos'],
            default => [],
        };
    }

    /** ¿Puede este usuario pedir estos criterios? (tipo de su rol y agrupación por agente solo para el Administrador). */
    public static function permite(Usuario $actor, CriteriosDeReporte $criterios): bool
    {
        if (! in_array($criterios->tipo, self::tiposPermitidos($actor), true)) {
            return false;
        }

        return $criterios->agrupacion !== 'agente' || $actor->tieneRol(Rol::ADMINISTRADOR);
    }

    public function construir(CriteriosDeReporte $criterios, Usuario $actor): ResultadoDeReporte
    {
        abort_unless(self::permite($actor, $criterios), 403);

        $consulta = $this->consulta($criterios, $actor);

        if ((clone $consulta)->count() > self::MAXIMO_FILAS) {
            throw new ReporteDemasiadoGrande('El reporte tiene más de ' . self::MAXIMO_FILAS . ' filas. Afina los filtros (fechas, estado o zona) e inténtalo de nuevo.');
        }

        $registros = $consulta->get();
        $definicion = $this->definicion($criterios->tipo);
        $items = $registros->map($definicion['fila'])->values()->all(); // [celdas, monto, estado, categoria, agente, fecha]

        $suma = $definicion['suma'] === null ? null : array_sum(array_map(fn ($i) => (float) $i['monto'], $items));
        $totales = ['cantidad' => count($items), 'suma' => $suma, 'etiquetaSuma' => $definicion['suma']];

        if ($criterios->agrupacion === 'ninguna') {
            return new ResultadoDeReporte(
                'Reporte de ' . strtolower(CriteriosDeReporte::TIPOS[$criterios->tipo]),
                $definicion['columnas'],
                array_map(fn ($i) => $i['celdas'], $items),
                $definicion['numericas'],
                $totales,
                false,
            );
        }

        return $this->agrupar($criterios, $definicion, $items, $totales);
    }

    private function agrupar(CriteriosDeReporte $criterios, array $definicion, array $items, array $totales): ResultadoDeReporte
    {
        $grupos = [];
        foreach ($items as $item) {
            $clave = match ($criterios->agrupacion) {
                'estado' => $item['estado'],
                'categoria' => $item['categoria'] ?? 'Sin categoría',
                'agente' => $item['agente'] ?? 'Sin agente',
                'mes' => $item['fecha'] ? $item['fecha']->format('Y-m') : 'Sin fecha',
            };
            $grupos[$clave] ??= ['cantidad' => 0, 'suma' => 0.0];
            $grupos[$clave]['cantidad']++;
            $grupos[$clave]['suma'] += (float) $item['monto'];
        }

        ksort($grupos);

        $conSuma = $definicion['suma'] !== null;
        $filas = [];
        foreach ($grupos as $clave => $grupo) {
            $nombre = $criterios->agrupacion === 'mes' && preg_match('/^(\d{4})-(\d{2})$/', (string) $clave, $m) ? "{$m[2]}/{$m[1]}" : $clave;
            $filas[] = $conSuma ? [$nombre, $grupo['cantidad'], $grupo['suma']] : [$nombre, $grupo['cantidad']];
        }

        $columnas = [CriteriosDeReporte::AGRUPACIONES[$criterios->agrupacion], 'Cantidad'];
        if ($conSuma) {
            $columnas[] = $definicion['suma'];
        }

        return new ResultadoDeReporte(
            'Reporte de ' . strtolower(CriteriosDeReporte::TIPOS[$criterios->tipo]) . ' ' . strtolower(CriteriosDeReporte::AGRUPACIONES[$criterios->agrupacion]),
            $columnas,
            $filas,
            $conSuma ? [2] : [],
            $totales,
            true,
        );
    }

    // ------------------------------------------------------------------ consultas

    private function consulta(CriteriosDeReporte $c, Usuario $actor): Builder
    {
        $esAdmin = $actor->tieneRol(Rol::ADMINISTRADOR);
        $esAgente = $actor->tieneRol(Rol::AGENTE);
        // El filtro por agente solo lo usa el Administrador; el Agente siempre queda limitado a lo suyo.
        $idagente = $esAdmin ? $c->idagente : null;

        $consulta = match ($c->tipo) {
            'propiedades' => Propiedad::query()->with(['categoria', 'ubicacion', 'agente'])
                ->when($c->estado === null, fn ($q) => $q->vigentes())
                ->when($esAgente, fn ($q) => $q->where('idusuario', $actor->idusuario))
                ->when($idagente, fn ($q) => $q->where('idusuario', $idagente))
                ->when($c->zona, fn ($q, $zona) => $q->whereHas('ubicacion', fn ($u) => $u->where('zona', $zona))),
            'clientes' => Cliente::query()->with('agente')
                ->when($esAgente, fn ($q) => $q->where('idusuario', $actor->idusuario))
                ->when($idagente, fn ($q) => $q->where('idusuario', $idagente)),
            'visitas' => Visita::query()->with(['propiedad.agente', 'propiedad.ubicacion', 'cliente'])
                ->when($esAgente, fn ($q) => $q->whereHas('propiedad', fn ($p) => $p->where('idusuario', $actor->idusuario)))
                ->when($idagente, fn ($q) => $q->whereHas('propiedad', fn ($p) => $p->where('idusuario', $idagente)))
                ->when($c->zona, fn ($q, $zona) => $q->whereHas('propiedad.ubicacion', fn ($u) => $u->where('zona', $zona))),
            'contratos' => Contrato::query()->with(['propiedad', 'cliente', 'agente'])
                ->when($idagente, fn ($q) => $q->where('idusuario', $idagente)),
            'pagos' => Pago::query()->with(['contrato.cliente', 'usuario'])
                ->when($idagente, fn ($q) => $q->where('idusuario', $idagente)),
        };

        $columnaEstado = match ($c->tipo) {
            'propiedades' => 'estadopropiedad',
            'clientes' => 'estadoseguimiento',
            default => 'estado',
        };
        $columnaFecha = match ($c->tipo) {
            'propiedades', 'clientes' => 'fecharegistro',
            'visitas' => 'fecha',
            'contratos' => 'fechainicio',
            'pagos' => 'fechapago',
        };

        return $consulta
            ->when($c->estado, fn ($q, $estado) => $q->where($columnaEstado, $estado))
            ->when($c->desde, fn ($q, $desde) => $q->whereDate($columnaFecha, '>=', $desde->toDateString()))
            ->when($c->hasta, fn ($q, $hasta) => $q->whereDate($columnaFecha, '<=', $hasta->toDateString()))
            ->orderByDesc($columnaFecha)
            ->orderByDesc($consulta->getModel()->getKeyName());
    }

    // ------------------------------------------------------------ columnas y filas

    /** @return array{columnas: string[], numericas: int[], suma: ?string, fila: \Closure} */
    private function definicion(string $tipo): array
    {
        $dinero = fn ($v) => $v === null ? null : (float) $v;
        $fecha = fn ($v) => $v ? Carbon::parse($v) : null;
        $estado = fn (array $lista, ?string $clave) => $lista[$clave] ?? ($clave ? ucfirst(strtolower($clave)) : '');

        return match ($tipo) {
            'propiedades' => [
                'columnas' => ['Código', 'Título', 'Categoría', 'Tipo', 'Sector / zona', 'Estado', 'Precio (USD)', 'Agente'],
                'numericas' => [6],
                'suma' => 'Suma de precios (USD)',
                'fila' => fn (Propiedad $p) => [
                    'celdas' => [$p->codigo, $p->titulo, $p->categoria?->nombre, $p->tipopropiedad, $p->ubicacion?->zona ?: $p->sector,
                        $estado([], $p->estadopropiedad), $dinero($p->precio), $p->agente?->nombre],
                    'monto' => $dinero($p->precio), 'estado' => $estado([], $p->estadopropiedad),
                    'categoria' => $p->categoria?->nombre, 'agente' => $p->agente?->nombre, 'fecha' => $fecha($p->fecharegistro),
                ],
            ],
            'clientes' => [
                'columnas' => ['Nombre', 'Correo', 'Teléfono', 'Tipo', 'Estado de seguimiento', 'Agente', 'Fecha de registro'],
                'numericas' => [],
                'suma' => null,
                'fila' => fn (Cliente $c) => [
                    'celdas' => [$c->nombre, $c->email, $c->telefono, $c->tipocliente, $estado(Cliente::ESTADOS, $c->estadoseguimiento),
                        $c->agente?->nombre, $fecha($c->fecharegistro)?->format('d/m/Y')],
                    'monto' => null, 'estado' => $estado(Cliente::ESTADOS, $c->estadoseguimiento),
                    'categoria' => null, 'agente' => $c->agente?->nombre, 'fecha' => $fecha($c->fecharegistro),
                ],
            ],
            'visitas' => [
                'columnas' => ['Fecha', 'Hora', 'Propiedad', 'Cliente', 'Estado', 'Agente'],
                'numericas' => [],
                'suma' => null,
                'fila' => fn (Visita $v) => [
                    'celdas' => [$fecha($v->fecha)?->format('d/m/Y'), $v->hora_corta, trim(($v->propiedad?->codigo ?? '') . ' ' . ($v->propiedad?->titulo ?? '')),
                        $v->cliente?->nombre, $estado(Visita::ESTADOS, $v->estado), $v->propiedad?->agente?->nombre],
                    'monto' => null, 'estado' => $estado(Visita::ESTADOS, $v->estado),
                    'categoria' => null, 'agente' => $v->propiedad?->agente?->nombre, 'fecha' => $fecha($v->fecha),
                ],
            ],
            'contratos' => [
                'columnas' => ['Propiedad', 'Cliente', 'Tipo', 'Monto (USD)', 'Estado', 'Fecha de inicio'],
                'numericas' => [3],
                'suma' => 'Suma de montos (USD)',
                'fila' => fn (Contrato $c) => [
                    'celdas' => [trim(($c->propiedad?->codigo ?? '') . ' ' . ($c->propiedad?->titulo ?? '')), $c->cliente?->nombre, $c->tipocontrato,
                        $dinero($c->monto), $estado(Contrato::ESTADOS, $c->estado), $fecha($c->fechainicio)?->format('d/m/Y')],
                    'monto' => $dinero($c->monto), 'estado' => $estado(Contrato::ESTADOS, $c->estado),
                    'categoria' => null, 'agente' => $c->agente?->nombre, 'fecha' => $fecha($c->fechainicio),
                ],
            ],
            'pagos' => [
                'columnas' => ['Comprobante', 'Contrato', 'Cliente', 'Monto', 'Moneda', 'Monto (USD)', 'Método', 'Estado', 'Fecha'],
                'numericas' => [3, 5],
                // Solo los pagos PAGADO suman: uno pendiente o anulado todavía no es dinero cobrado.
                'suma' => 'Total pagado (USD)',
                'fila' => fn (Pago $p) => [
                    'celdas' => [$p->comprobante, $p->idcontrato ? 'N.º ' . $p->idcontrato : '', $p->contrato?->cliente?->nombre, $dinero($p->monto), $p->moneda,
                        $dinero($p->montousd), Pago::METODOS[$p->metodo] ?? $p->metodo, $estado(Pago::ESTADOS, $p->estado), $fecha($p->fechapago)?->format('d/m/Y H:i')],
                    'monto' => $p->estado === Pago::PAGADO ? $dinero($p->montousd) : 0.0, 'estado' => $estado(Pago::ESTADOS, $p->estado),
                    'categoria' => null, 'agente' => $p->usuario?->nombre, 'fecha' => $fecha($p->fechapago),
                ],
            ],
        };
    }
}