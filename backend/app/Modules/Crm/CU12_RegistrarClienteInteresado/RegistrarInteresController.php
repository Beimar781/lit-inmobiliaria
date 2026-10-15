<?php

namespace App\Modules\Crm\CU12_RegistrarClienteInteresado;

use App\Http\Controllers\Controller;
use App\Models\Propiedad;
use Illuminate\Http\RedirectResponse;

/**
 * CU12 (vía 1): "Me interesa" del Cliente público, desde la ficha de la propiedad. No requiere iniciar sesión.
 * Flujo principal:
 *  1. El cliente pulsa "Me interesa" en la ficha                         -> me-interesa.blade.php (incluido en el CU11)
 *  2. Ingresa nombre, correo y/o teléfono (al menos uno) y un mensaje opcional
 *  3. El sistema valida la información                                   -> InteresPublicoRequest
 *  4-6. Busca si ya existe el cliente, registra el interés y asigna el prospecto al agente de la propiedad -> RegistroDeInteres
 *  7. Agradece al cliente e informa que un agente lo contactará
 * Si ya había mostrado interés por esa propiedad, no se duplica: se le informa.
 * Protección contra spam: límite de solicitudes por minuto (ver routes.php).
 */
class RegistrarInteresController extends Controller
{
    public function guardar(InteresPublicoRequest $request, Propiedad $propiedad, RegistroDeInteres $registro): RedirectResponse
    {
        abort_if($propiedad->estadopropiedad === Propiedad::BAJA, 404);

        // Trampa para robots: el campo "sitio_web" está oculto para las personas. Si viene lleno, se finge éxito y no se guarda nada.
        if ($request->filled('sitio_web')) {
            return redirect()->route('catalogo.detalle', $propiedad)
                ->with('status', '¡Gracias por tu interés! Un agente se pondrá en contacto contigo pronto.');
        }

        if ($propiedad->estadopropiedad !== Propiedad::DISPONIBLE) {
            return redirect()->route('catalogo.detalle', $propiedad)
                ->with('error', 'Esta propiedad ya no está disponible.');
        }

        $resultado = $registro->registrar(
            array_merge($request->validated(), [
                // En el formulario público el tipo se deduce de la operación.
                'tipocliente' => $propiedad->tipopropiedad === 'Venta' ? 'Comprador' : 'Inquilino',
            ]),
            $propiedad
        );

        $mensaje = $resultado['ya_registrado']
            ? 'Tu interés en esta propiedad ya fue registrado. Un agente se pondrá en contacto contigo.'
            : '¡Gracias por tu interés! Un agente se pondrá en contacto contigo pronto.';

        return redirect()->route('catalogo.detalle', $propiedad)->with('status', $mensaje);
    }
}
