<?php

namespace App\Modules\AgendaVisitasPagos\CU17_VerCalendarioVisitas;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Visita;
use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use App\Services\BitacoraService;
use App\Support\ParametrosConsulta;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * CU17: Ver calendario de visitas (Agente inmobiliario y Administrador)
 * El Agente ve las visitas de sus propiedades; el Administrador ve las de todos y puede filtrar por agente.
 * Flujo principal:
 *  1. El actor abre la opción "Calendario de visitas"                          -> index()
 *  2. El sistema muestra el mes actual con las visitas del actor (o de todos los agentes, si es administrador)
 *  3. El actor cambia de mes o semana y, si lo desea, filtra por estado (y por agente, si es administrador)
 *  4. El sistema actualiza el calendario con las visitas del período           -> calendario.blade.php
 *  5. El actor selecciona una visita                                           -> show()
 *  6. El sistema muestra su detalle y las acciones disponibles (Reagendar CU15, Cancelar CU16, Marcar como realizada)
 * Flujo alternativo: sin visitas en el período -> "Sin visitas programadas".
 * Excepciones: un Agente pide las visitas de otro agente (403); período o filtro no válido (se avisa y se usan los valores por defecto);
 * error de conexión con la base de datos (página de error).
 * No modifica ningún dato, salvo la acción "Marcar como realizada" del detalle (decisión del equipo).
 */
class CalendarioVisitasController extends Controller
{
    private const MESES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    private const DIAS = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    public function index(Request $request): View
    {
        $usuario = $request->user();
        $esAdmin = $usuario->tieneRol(Rol::ADMINISTRADOR);
        $hoy = AgendaDeVisitas::ahora()->startOfDay();
        $errores = [];

        // Parámetros de la URL: lo que no sea válido se avisa y se reemplaza por el valor por defecto.
        $vista = ParametrosConsulta::opcion($request, 'vista', ['mes', 'semana']) ?? 'mes';
        if (ParametrosConsulta::texto($request, 'vista') !== '' && $vista !== ParametrosConsulta::texto($request, 'vista')) {
            $errores[] = 'La vista no es válida: se muestra el mes.';
        }

        $referencia = $hoy;
        if (ParametrosConsulta::texto($request, 'fecha') !== '') {
            $fecha = $this->fecha(ParametrosConsulta::texto($request, 'fecha'));
            $fecha ? $referencia = $fecha : $errores[] = 'La fecha no es válida: se muestra el período actual.';
        }

        $estado = ParametrosConsulta::opcion($request, 'estado', array_keys(Visita::ESTADOS));
        if (ParametrosConsulta::texto($request, 'estado') !== '' && $estado === null) {
            $errores[] = 'El estado elegido no es válido: se muestran todos.';
        }

        // Agente: solo ve las suyas. Pedir las de otro agente es acceso denegado.
        $agentes = $esAdmin ? $this->agentes() : [];
        $idAgente = null;
        $pedido = ParametrosConsulta::texto($request, 'agente');
        if (! $esAdmin) {
            abort_if($pedido !== '' && $pedido !== (string) $usuario->idusuario, 403, 'No puedes ver las visitas de otro agente.');
            $idAgente = $usuario->idusuario;
        } elseif ($pedido !== '') {
            $idAgente = ctype_digit($pedido) && isset($agentes[(int) $pedido]) ? (int) $pedido : null;
            if ($idAgente === null) {
                $errores[] = 'El agente elegido no es válido: se muestran las visitas de todos.';
            }
        }

        // Período: el mes (con las semanas completas, de lunes a domingo) o la semana.
        if ($vista === 'mes') {
            $inicioPeriodo = $referencia->startOfMonth();
            $finPeriodo = $referencia->endOfMonth()->startOfDay();
            $desde = $inicioPeriodo->startOfWeek(CarbonImmutable::MONDAY);
            $hasta = $finPeriodo->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay();
            $titulo = self::MESES[$referencia->month] . ' ' . $referencia->year;
            $anterior = $inicioPeriodo->subMonth();
            $siguiente = $inicioPeriodo->addMonth();
        } else {
            $inicioPeriodo = $desde = $referencia->startOfWeek(CarbonImmutable::MONDAY);
            $finPeriodo = $hasta = $referencia->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay();
            $titulo = 'Semana del ' . $desde->day . ' de ' . self::MESES[$desde->month] . ' al ' . $hasta->day . ' de ' . self::MESES[$hasta->month] . ' de ' . $hasta->year;
            $anterior = $desde->subWeek();
            $siguiente = $desde->addWeek();
        }

        $visitas = Visita::with(['propiedad', 'cliente'])
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->when($idAgente, fn ($q) => $q->whereHas('propiedad', fn ($p) => $p->where('idusuario', $idAgente)))
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        $porDia = $visitas->groupBy(fn (Visita $v) => $v->fecha->format('Y-m-d'));

        $semanas = [];
        for ($dia = $desde; $dia <= $hasta; $dia = $dia->addDay()) {
            $semanas[(int) floor($dia->diffInDays($desde) / 7)][] = $dia;
        }

        $filtros = array_filter(['vista' => $vista === 'mes' ? null : $vista, 'estado' => $estado, 'agente' => $esAdmin ? $idAgente : null]);

        return view('agendavisitaspagos::CU17_VerCalendarioVisitas.calendario', [
            'vista' => $vista,
            'titulo' => $titulo,
            'semanas' => $semanas,
            'porDia' => $porDia,
            'hoy' => $hoy,
            'inicioPeriodo' => $inicioPeriodo,
            'finPeriodo' => $finPeriodo,
            'totalPeriodo' => $visitas->filter(fn (Visita $v) => $v->fecha->format('Y-m-d') >= $inicioPeriodo->toDateString() && $v->fecha->format('Y-m-d') <= $finPeriodo->toDateString())->count(),
            'estado' => $estado,
            'estados' => Visita::ESTADOS,
            'agentes' => $agentes,
            'idAgente' => $idAgente,
            'esAdmin' => $esAdmin,
            'errores' => $errores,
            'dias' => self::DIAS,
            'enlaceAnterior' => route('visitas.calendario', $filtros + ['fecha' => $anterior->toDateString()]),
            'enlaceSiguiente' => route('visitas.calendario', $filtros + ['fecha' => $siguiente->toDateString()]),
            'enlaceHoy' => route('visitas.calendario', $filtros),
            'enlaceMes' => route('visitas.calendario', array_diff_key($filtros, ['vista' => 1]) + ['fecha' => $referencia->toDateString()]),
            'enlaceSemana' => route('visitas.calendario', ['vista' => 'semana'] + array_diff_key($filtros, ['vista' => 1]) + ['fecha' => $referencia->toDateString()]),
            'filtros' => array_diff_key($filtros, ['estado' => 1, 'agente' => 1]) + ['fecha' => $referencia->toDateString()],
        ]);
    }

    /** Pasos 5 y 6: detalle de la visita con las acciones disponibles. */
    public function show(Request $request, Visita $visita): View
    {
        $this->autorizar($request, $visita);
        $visita->load(['propiedad.agente', 'cliente']);

        return view('agendavisitaspagos::CU17_VerCalendarioVisitas.detalle', [
            'visita' => $visita,
            'puedeReagendar' => $visita->estaActiva() && Route::has('visitas.reagendar'),   // CU15
            'puedeCancelar' => $visita->estaActiva() && Route::has('visitas.cancelar'),     // CU16
        ]);
    }

    /** Acción extra del detalle (decisión del equipo): la visita ya se hizo. */
    public function marcarRealizada(Request $request, Visita $visita): RedirectResponse
    {
        $this->autorizar($request, $visita);

        if (! $visita->estaActiva()) {
            return back()->with('error', 'Solo se pueden marcar como realizadas las visitas programadas o reagendadas.');
        }

        $anterior = $visita->estado;
        $visita->update(['estado' => Visita::REALIZADA]);
        $visita->loadMissing(['propiedad', 'cliente']);

        BitacoraService::registrar('VISITA_REALIZADA', 'Agenda', "Marcó como realizada la visita de {$visita->cliente->nombre} a «{$visita->propiedad->titulo}» ({$visita->propiedad->codigo})", [
            'idvisita' => $visita->idvisita,
            'estado_anterior' => $anterior,
            'fecha' => $visita->fecha->format('Y-m-d'),
            'hora' => $visita->hora_corta,
        ]);

        return redirect()->route('visitas.show', $visita)->with('status', 'La visita quedó marcada como realizada.');
    }

    /** El Agente solo accede a las visitas de sus propiedades; el Administrador, a todas. */
    private function autorizar(Request $request, Visita $visita): void
    {
        AgendaDeVisitas::autorizar($request->user(), $visita);
    }

    /** @return array<int,string> id => nombre de los agentes */
    private function agentes(): array
    {
        return Usuario::whereHas('rol', fn ($r) => $r->where('nombre', Rol::AGENTE))->orderBy('nombre')->pluck('nombre', 'idusuario')->all();
    }

    private function fecha(string $texto): ?CarbonImmutable
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $texto, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $texto, AgendaDeVisitas::ZONA)->startOfDay();
    }
}
