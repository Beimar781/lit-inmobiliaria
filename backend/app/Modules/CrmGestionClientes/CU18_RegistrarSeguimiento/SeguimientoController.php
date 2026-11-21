<?php

namespace App\Modules\CrmGestionClientes\CU18_RegistrarSeguimiento;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Seguimiento;
use App\Modules\CrmGestionClientes\Compartido\AccesoAProspecto;
use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * CU18: Registrar seguimiento de cliente (Agente en sus prospectos, o Administrador)
 * Cada gestión (llamada, WhatsApp, correo, reunión...) queda en el historial del prospecto, del más reciente al más antiguo.
 * Los seguimientos no se editan ni se eliminan. No cambia el estado de seguimiento del CU13.
 *  1. Muestra el formulario y el historial   -> mostrar()
 *  2. Valida y guarda el seguimiento         -> guardar()  (validación en SeguimientoRequest)
 */
class SeguimientoController extends Controller
{
    public function mostrar(Cliente $cliente): View
    {
        AccesoAProspecto::autorizar($cliente);

        return view('crmgestionclientes::CU18_RegistrarSeguimiento.seguimiento', [
            'cliente' => $cliente,
            'seguimientos' => $cliente->seguimientos()->with(['usuario', 'propiedad'])
                ->orderByDesc('fechacontacto')->orderByDesc('idseguimiento')->get(),
            'propiedades' => $cliente->intereses()->with('propiedad')->get()->pluck('propiedad')->filter()->unique('idpropiedad')
                ->mapWithKeys(fn ($p) => [$p->idpropiedad => $p->codigo . ' - ' . $p->titulo])->all(),
            'medios' => Seguimiento::MEDIOS,
        ]);
    }

    public function guardar(SeguimientoRequest $request, Cliente $cliente): RedirectResponse
    {
        $datos = $request->validated();

        $seguimiento = Seguimiento::create([
            'idcliente' => $cliente->idcliente,
            'idusuario' => $request->user()->idusuario,
            'idpropiedad' => $datos['idpropiedad'] ?? null,
            'medio' => $datos['medio'],
            'nota' => $datos['nota'],
            'fechacontacto' => $datos['fechacontacto'],
            'proximocontacto' => $datos['proximocontacto'] ?? null,
            'fecharegistro' => now(),
        ]);

        BitacoraService::registrar('SEGUIMIENTO_REGISTRADO', 'CRM',
            "Registró un seguimiento ({$seguimiento->medio}) de {$cliente->nombre}", [
                'idcliente' => $cliente->idcliente,
                'idseguimiento' => $seguimiento->idseguimiento,
                'medio' => $seguimiento->medio,
            ]);

        return redirect()->route('prospectos.seguimientos', $cliente)->with('status', 'Seguimiento registrado correctamente.');
    }
}