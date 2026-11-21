<?php

namespace App\Modules\CrmGestionClientes\CU25_RegistrarResena;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Propiedad;
use App\Models\Resena;
use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CU25: Registrar reseña de propiedad (solo el Cliente con contrato vigente o finalizado sobre la propiedad)
 * Califica de 1 a 5 y comenta; una reseña por cliente y propiedad. Se muestra en la ficha pública (CU11) con el promedio.
 *  1. Muestra el formulario, o la reseña ya publicada -> crear()
 *  2. Valida y publica la reseña                      -> guardar()  (reglas en ResenaRequest)
 */
class ResenaController extends Controller
{
    public function crear(Request $request, Propiedad $propiedad): View|RedirectResponse
    {
        $cliente = Cliente::where('idusuariocuenta', $request->user()->idusuario)->first();

        if (! $cliente || ! ResenaRequest::tieneContrato($cliente, $propiedad->idpropiedad)) {
            return redirect()->route('portal.contratos')
                ->with('error', 'Solo puedes reseñar propiedades sobre las que tienes un contrato.');
        }

        return view('crmgestionclientes::CU25_RegistrarResena.resena', [
            'propiedad' => $propiedad,
            'resena' => Resena::where('idcliente', $cliente->idcliente)->where('idpropiedad', $propiedad->idpropiedad)->first(),
        ]);
    }

    public function guardar(ResenaRequest $request, Propiedad $propiedad): RedirectResponse
    {
        $cliente = Cliente::where('idusuariocuenta', $request->user()->idusuario)->firstOrFail();
        $datos = $request->validated();

        $resena = Resena::create([
            'idpropiedad' => $propiedad->idpropiedad,
            'idcliente' => $cliente->idcliente,
            'puntuacion' => (int) $datos['puntuacion'],
            'comentario' => $datos['comentario'] ?? null,
            'fecha' => now(),
        ]);

        BitacoraService::registrar('RESENA_REGISTRADA', 'CRM', "Registró una reseña de {$resena->puntuacion} estrellas a la propiedad {$propiedad->codigo}", [
            'idpropiedad' => $propiedad->idpropiedad,
            'idresena' => $resena->idresena,
            'puntuacion' => $resena->puntuacion,
        ]);

        return redirect()->route('resenas.crear', $propiedad)->with('status', 'Tu reseña se publicó. ¡Gracias por calificar!');
    }
}