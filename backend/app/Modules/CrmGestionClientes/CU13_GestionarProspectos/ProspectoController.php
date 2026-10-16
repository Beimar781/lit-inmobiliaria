<?php

namespace App\Modules\CrmGestionClientes\CU13_GestionarProspectos;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\BitacoraService;
use App\Support\ParametrosConsulta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CU13: Gestionar prospectos CRM (Agente y Administrador)
 * El Agente ve y gestiona solo sus prospectos; el Administrador ve todos (incluidos los sin asignar) y puede reasignarlos.
 * Las notas e historial de contactos se registran con el CU18 (ciclo posterior).
 * Flujo principal:
 *  1. El usuario accede al listado de prospectos                          -> index()
 *  2. Busca por nombre, correo o teléfono y/o filtra por estado de seguimiento
 *  3. Selecciona un prospecto y el sistema muestra sus datos y las propiedades de interés -> show()
 *  4. Actualiza el estado de seguimiento (y, el administrador, el agente asignado) -> actualizarSeguimiento() / reasignar()
 *  5. El sistema valida, guarda y registra el cambio en la bitácora
 * Excepciones: prospecto inexistente (404), prospecto de otro agente (403), estado no válido.
 */
class ProspectoController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = $request->user();
        $esAdmin = $usuario->tieneRol(Rol::ADMINISTRADOR);
        $busqueda = ParametrosConsulta::texto($request, 'q');
        $estado = ParametrosConsulta::opcion($request, 'estado', array_keys(Cliente::ESTADOS));
        $agente = ParametrosConsulta::texto($request, 'agente');

        $prospectos = Cliente::with('agente')
            ->withCount('intereses')
            ->when(! $esAdmin, fn ($c) => $c->where('idusuario', $usuario->idusuario))
            ->when($busqueda !== '', function ($c) use ($busqueda) {
                $c->where(function ($donde) use ($busqueda) {
                    $donde->whereLike('nombre', "%{$busqueda}%")
                        ->orWhereLike('email', "%{$busqueda}%")
                        ->orWhereLike('telefono', "%{$busqueda}%");
                });
            })
            ->when($estado !== null, fn ($c) => $c->where('estadoseguimiento', $estado))
            ->when($esAdmin && $agente === 'sin', fn ($c) => $c->whereNull('idusuario'))
            ->when($esAdmin && ParametrosConsulta::entero($request, 'agente') !== null, fn ($c) => $c->where('idusuario', ParametrosConsulta::entero($request, 'agente')))
            ->orderByDesc('idcliente')
            ->paginate(10)
            ->withQueryString();

        return view('crmgestionclientes::CU13_GestionarProspectos.index', [
            'prospectos' => $prospectos,
            'busqueda' => $busqueda,
            'estados' => Cliente::ESTADOS,
            'agentes' => $esAdmin ? $this->agentes() : [],
            'esAdmin' => $esAdmin,
        ]);
    }

    public function show(Request $request, Cliente $cliente): View
    {
        $this->autorizar($request, $cliente);

        return view('crmgestionclientes::CU13_GestionarProspectos.detalle', [
            'cliente' => $cliente->load(['agente', 'intereses.propiedad']),
            'estados' => Cliente::ESTADOS,
            'agentes' => $request->user()->tieneRol(Rol::ADMINISTRADOR) ? $this->agentes() : [],
            'esAdmin' => $request->user()->tieneRol(Rol::ADMINISTRADOR),
        ]);
    }

    public function actualizarSeguimiento(Request $request, Cliente $cliente): RedirectResponse
    {
        $this->autorizar($request, $cliente);

        $datos = $request->validate(
            ['estadoseguimiento' => ['required', Rule::in(array_keys(Cliente::ESTADOS))]],
            ['estadoseguimiento.required' => 'Elige un estado de seguimiento.', 'estadoseguimiento.in' => 'El estado de seguimiento no es válido.']
        );

        $anterior = $cliente->estadoseguimiento;

        if ($anterior === $datos['estadoseguimiento']) {
            return redirect()->route('prospectos.show', $cliente)->with('status', 'El estado de seguimiento no cambió.');
        }

        $cliente->update(['estadoseguimiento' => $datos['estadoseguimiento']]);

        BitacoraService::registrar('PROSPECTO_SEGUIMIENTO', 'CRM', "Cambió el seguimiento de {$cliente->nombre} de {$anterior} a {$cliente->estadoseguimiento}", [
            'idcliente' => $cliente->idcliente,
            'estado_anterior' => $anterior,
            'estado_nuevo' => $cliente->estadoseguimiento,
        ]);

        return redirect()->route('prospectos.show', $cliente)->with('status', 'Seguimiento actualizado.');
    }

    /** Solo el Administrador (ver routes.php). */
    public function reasignar(Request $request, Cliente $cliente): RedirectResponse
    {
        $datos = $request->validate(
            ['idusuario' => ['required', Rule::in(array_keys($this->agentes()))]],
            ['idusuario.required' => 'Elige un agente.', 'idusuario.in' => 'El agente elegido no es válido o está desactivado.']
        );

        $anterior = $cliente->idusuario;

        if ($anterior === (int) $datos['idusuario']) {
            return redirect()->route('prospectos.show', $cliente)->with('status', 'El prospecto ya estaba asignado a ese agente.');
        }

        $cliente->update(['idusuario' => $datos['idusuario']]);
        $cliente->load('agente');

        BitacoraService::registrar('PROSPECTO_REASIGNADO', 'CRM', "Reasignó a {$cliente->nombre} al agente {$cliente->agente->nombre}", [
            'idcliente' => $cliente->idcliente,
            'agente_anterior' => $anterior,
            'agente_nuevo' => $cliente->idusuario,
        ]);

        return redirect()->route('prospectos.show', $cliente)->with('status', 'Prospecto asignado a ' . $cliente->agente->nombre . '.');
    }

    /** El Agente solo accede a sus prospectos; el Administrador, a todos. */
    private function autorizar(Request $request, Cliente $cliente): void
    {
        $usuario = $request->user();

        abort_unless(
            $usuario->tieneRol(Rol::ADMINISTRADOR) || $cliente->idusuario === $usuario->idusuario,
            403,
            'Este prospecto no está asignado a ti.'
        );
    }

    /** Agentes activos a los que se puede asignar un prospecto: [idusuario => nombre]. */
    private function agentes(): array
    {
        return Usuario::where('estado', Usuario::ACTIVO)
            ->whereHas('rol', fn ($r) => $r->where('nombre', Rol::AGENTE))
            ->orderBy('nombre')
            ->pluck('nombre', 'idusuario')
            ->all();
    }
}
