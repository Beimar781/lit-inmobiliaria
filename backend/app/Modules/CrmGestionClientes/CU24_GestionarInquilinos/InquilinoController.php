<?php

namespace App\Modules\CrmGestionClientes\CU24_GestionarInquilinos;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Inquilino;
use App\Modules\CrmGestionClientes\Compartido\AccesoAProspecto;
use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * CU24: Gestionar inquilinos (Agente en sus prospectos, o Administrador)
 * Un cliente tiene un solo registro de inquilino: si ya existe, registrar de nuevo lo actualiza. Nada se borra;
 * el inquilino se puede pasar a Inactivo.
 *  1. Muestra el formulario (registrar o editar) -> mostrar()
 *  2. Valida y guarda                            -> guardar()  (validación en InquilinoRequest)
 */
class InquilinoController extends Controller
{
    public function mostrar(Cliente $cliente): View
    {
        AccesoAProspecto::autorizar($cliente);

        return view('crmgestionclientes::CU24_GestionarInquilinos.inquilino', [
            'cliente' => $cliente,
            'inquilino' => $cliente->inquilino,
            'garantias' => Inquilino::GARANTIAS,
            'estados' => Inquilino::ESTADOS,
        ]);
    }

    public function guardar(InquilinoRequest $request, Cliente $cliente): RedirectResponse
    {
        $datos = $request->validated();
        $existente = $cliente->inquilino;

        $campos = [
            'ocupacion' => $datos['ocupacion'],
            'tipogarantia' => $datos['tipogarantia'],
            'detallegarantia' => $datos['tipogarantia'] === 'NINGUNA' ? null : $datos['detallegarantia'],
        ];

        if ($existente) {
            // El estado solo se cambia cuando el inquilino ya existe.
            $campos['estado'] = $datos['estado'] ?? $existente->estado;
            $existente->update($campos);
        } else {
            // updateOrCreate: aunque llegaran dos envíos a la vez, la llave única de la tabla evita un segundo inquilino.
            Inquilino::updateOrCreate(['idcliente' => $cliente->idcliente], $campos + ['estado' => 'ACTIVO', 'fecharegistro' => now()]);
        }

        $accion = $existente ? 'INQUILINO_MODIFICADO' : 'INQUILINO_REGISTRADO';
        BitacoraService::registrar($accion, 'CRM',
            ($existente ? 'Modificó' : 'Registró') . " al inquilino {$cliente->nombre}", [
                'idcliente' => $cliente->idcliente,
                'estado' => $cliente->fresh('inquilino')->inquilino->estado,
            ]);

        return redirect()->route('prospectos.show', $cliente)
            ->with('status', $existente ? 'Inquilino actualizado correctamente.' : 'Inquilino registrado correctamente.');
    }
}