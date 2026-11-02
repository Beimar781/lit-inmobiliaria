<?php

namespace App\Modules\AgendaVisitasPagos\CU29_ConsultarHistorialPagos;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Pago;
use App\Modules\AgendaVisitasPagos\Compartido\ComprobanteDePago;
use App\Support\ParametrosConsulta;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CU29: Consultar historial de pagos (Asistente Administrativo y Administrador)
 * Lista los pagos filtrables por contrato, cliente, rango de fechas y estado, con el total del resultado en dólares
 * (suma solo los pagos PAGADOS). No modifica ningún dato.
 * Flujo principal:
 *  1. El actor accede a "Historial de pagos"                                  -> index()
 *  2. El actor elige el contrato o el cliente a consultar                     -> index.blade.php
 *  3. De forma opcional, define el rango de fechas y el estado del pago
 *  4. El sistema consulta los pagos y muestra el listado con el total          -> index()
 *  5. El actor selecciona un pago
 *  6. El sistema muestra el detalle del pago y la opción de ver su comprobante -> show()
 * Flujo alternativo: sin pagos para los filtros -> «Sin pagos registrados» y total 0. Desde el detalle se puede volver a emitir
 * el comprobante (CU30), que conserva su número. Un pago PENDIENTE o ANULADO no tiene comprobante.
 * Excepciones: rango de fechas no válido («desde» posterior a «hasta»: se avisa y no se filtra por fechas), contrato o cliente que
 * no existe (se avisa y no se muestran pagos), pago inexistente (404), error de conexión con la base de datos (página de error).
 */
class HistorialPagosController extends Controller
{
    private const POR_PAGINA = 10;

    public function index(Request $request): View
    {
        $errores = [];

        $idContrato = ParametrosConsulta::entero($request, 'contrato');
        $idCliente = ParametrosConsulta::entero($request, 'cliente');
        $estado = ParametrosConsulta::opcion($request, 'estado', array_keys(Pago::ESTADOS));
        $sinResultados = false;

        // Un contrato o un cliente que no existe: se avisa y no se muestran pagos (no tendría sentido mostrar todos).
        if ($idContrato !== null && ! Contrato::whereKey($idContrato)->exists()) {
            $errores[] = 'El contrato elegido no existe.';
            $sinResultados = true;
        }
        if ($idCliente !== null && ! Cliente::whereKey($idCliente)->exists()) {
            $errores[] = 'El cliente elegido no existe.';
            $sinResultados = true;
        }
        if (ParametrosConsulta::texto($request, 'contrato') !== '' && $idContrato === null) {
            $errores[] = 'El contrato elegido no es válido.';
            $sinResultados = true;
        }
        if (ParametrosConsulta::texto($request, 'cliente') !== '' && $idCliente === null) {
            $errores[] = 'El cliente elegido no es válido.';
            $sinResultados = true;
        }
        if (ParametrosConsulta::texto($request, 'estado') !== '' && $estado === null) {
            $errores[] = 'El estado elegido no es válido: se muestran todos.';
        }

        // Rango de fechas: cada fecha debe ser válida y «desde» no puede ser posterior a «hasta».
        $desde = $this->fecha(ParametrosConsulta::texto($request, 'desde'), 'desde', $errores);
        $hasta = $this->fecha(ParametrosConsulta::texto($request, 'hasta'), 'hasta', $errores);
        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            $errores[] = 'La fecha «desde» no puede ser posterior a la fecha «hasta»: no se filtró por fechas.';
            $desde = $hasta = null;
        }

        $consulta = Pago::query()
            ->when($sinResultados, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($idContrato !== null, fn ($q) => $q->where('idcontrato', $idContrato))
            ->when($idCliente !== null, fn ($q) => $q->whereHas('contrato', fn ($c) => $c->where('idcliente', $idCliente)))
            ->when($estado !== null, fn ($q) => $q->where('estado', $estado))
            ->when($desde !== null, fn ($q) => $q->whereDate('fechapago', '>=', $desde))
            ->when($hasta !== null, fn ($q) => $q->whereDate('fechapago', '<=', $hasta));

        // El total es de TODO el resultado (no solo de la página) y suma únicamente los pagos PAGADOS.
        $totalUsd = (float) (clone $consulta)->where('estado', Pago::PAGADO)->sum('montousd');
        $cantidad = (clone $consulta)->count();

        $pagos = $consulta
            ->with(['contrato.cliente', 'contrato.propiedad'])
            ->orderByDesc('fechapago')
            ->orderByDesc('idpago')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        return view('agendavisitaspagos::CU29_ConsultarHistorialPagos.index', [
            'pagos' => $pagos,
            'totalUsd' => round($totalUsd, 2),
            'cantidad' => $cantidad,
            'errores' => $errores,
            'filtros' => ['contrato' => $idContrato, 'cliente' => $idCliente, 'estado' => $estado, 'desde' => $desde, 'hasta' => $hasta],
            'estados' => Pago::ESTADOS,
            'contratos' => Contrato::with(['cliente', 'propiedad'])->orderByDesc('idcontrato')->get()
                ->mapWithKeys(fn (Contrato $c) => [$c->idcontrato => "N.º {$c->idcontrato} · {$c->cliente->nombre} · {$c->propiedad->codigo}"])->all(),
            'clientes' => Cliente::whereHas('contratos')->orderBy('nombre')->pluck('nombre', 'idcliente')->all(),
        ]);
    }

    public function show(Pago $pago): View
    {
        $pago->load(['contrato.cliente', 'contrato.propiedad', 'tipopago', 'usuario']);

        return view('agendavisitaspagos::CU29_ConsultarHistorialPagos.detalle', [
            'pago' => $pago,
            'contrato' => $pago->contrato,
            'conComprobante' => ComprobanteDePago::disponible($pago),
        ]);
    }

    /** @param string[] $errores */
    private function fecha(string $texto, string $nombre, array &$errores): ?string
    {
        if ($texto === '') {
            return null;
        }

        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $texto, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $errores[] = "La fecha «{$nombre}» no es válida: no se filtró por ella.";

            return null;
        }

        return $texto;
    }
}
