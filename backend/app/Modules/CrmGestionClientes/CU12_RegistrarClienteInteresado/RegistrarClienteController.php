<?php

namespace App\Modules\CrmGestionClientes\CU12_RegistrarClienteInteresado;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Propiedad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CU12 (vía 2): el Agente registra a quien lo contactó por otro medio (WhatsApp, redes, llamada).
 * Flujo principal:
 *  1. El agente selecciona "Registrar cliente interesado" en el CRM y elige la propiedad -> crear()
 *  2. Ingresa nombre, correo y/o teléfono (al menos uno), tipo de cliente y un mensaje opcional -> crear.blade.php
 *  3. El sistema valida la información                                   -> ClienteInteresadoRequest
 *  4-6. Busca si ya existe, registra el interés y asigna el prospecto    -> guardar() / RegistroDeInteres
 *  7. Confirma y lleva al agente al detalle del prospecto
 */
class RegistrarClienteController extends Controller
{
    public function crear(Request $request): View
    {
        return view('crmgestionclientes::CU12_RegistrarClienteInteresado.crear', [
            'propiedades' => Propiedad::vigentes()->orderByDesc('idpropiedad')->get()
                ->mapWithKeys(fn (Propiedad $p) => [$p->idpropiedad => "{$p->codigo} · {$p->titulo}"])->all(),
            'propiedadElegida' => $request->query('propiedad'),
            'tipos' => array_combine(Cliente::TIPOS, Cliente::TIPOS),
        ]);
    }

    public function guardar(ClienteInteresadoRequest $request, RegistroDeInteres $registro): RedirectResponse
    {
        $propiedad = Propiedad::findOrFail($request->input('idpropiedad'));

        // Excepción: la propiedad fue dada de baja.
        if ($propiedad->estadopropiedad === Propiedad::BAJA) {
            return back()->withInput()->withErrors(['idpropiedad' => 'Esa propiedad fue dada de baja.']);
        }

        $resultado = $registro->registrar($request->validated(), $propiedad, $request->user());
        $cliente = $resultado['cliente'];

        $mensaje = $resultado['ya_registrado']
            ? 'Ese cliente ya tenía registrado su interés en esta propiedad.'
            : ($resultado['cliente_nuevo'] ? 'Cliente interesado registrado como prospecto.' : 'Interés registrado en un cliente que ya existía en el CRM.');

        // Al detalle del prospecto, si el agente puede verlo (el prospecto va al agente de la propiedad).
        if ($cliente->idusuario === $request->user()->idusuario) {
            return redirect()->route('prospectos.show', $cliente)->with('status', $mensaje);
        }

        $aviso = $cliente->idusuario
            ? ' Quedó asignado a ' . $cliente->agente->nombre . ', agente de la propiedad.'
            : ' Quedó sin asignar para que el administrador lo asigne.';

        return redirect()->route('prospectos.index')->with('status', $mensaje . $aviso);
    }
}
