<?php

namespace App\Modules\AgendaVisitasPagos\CU26_GestionarReservas;

use App\Models\Pago;
use App\Models\Propiedad;
use App\Models\Rol;
use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validaciones del CU26 (paso 6): propiedad y cliente del CRM, la visita realizada (opcional), lo que paga el cliente en
 * efectivo (USD o BOB con su tipo de cambio) y un plazo futuro.
 */
class ReservaRequest extends FormRequest
{
    /**
     * El rol está en routes.php; aquí se revisa el permiso sobre la propiedad ANTES de validar el formulario,
     * así un Agente que quiere reservar una propiedad que no registró recibe siempre 403.
     */
    public function authorize(): bool
    {
        $propiedad = Propiedad::find($this->input('idpropiedad'));

        abort_if(
            $propiedad && $this->user()->tieneRol(Rol::AGENTE) && $propiedad->idusuario !== $this->user()->idusuario,
            403,
            'Solo puedes reservar las propiedades que tú registraste.'
        );

        return true;
    }

    public function rules(): array
    {
        return [
            'idpropiedad' => ['required', 'exists:propiedad,idpropiedad'],
            'idcliente' => ['required', 'exists:cliente,idcliente'],
            'idvisita' => ['nullable', 'exists:visita,idvisita'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'moneda' => ['required', Rule::in(Pago::MONEDAS)],
            // El dólar oficial cambia a diario: en bolivianos el tipo de cambio es obligatorio y lo escribe la persona.
            'tipocambio' => [Rule::requiredIf(fn () => $this->input('moneda') === Pago::BOB), 'nullable', 'numeric', 'gt:0', 'max:99999', 'decimal:0,4'],
            'fechaplazo' => [
                'bail', 'required', 'date_format:Y-m-d',
                function (string $atributo, mixed $fecha, \Closure $falla) {
                    if ($fecha <= AgendaDeVisitas::ahora()->toDateString()) {
                        $falla('El plazo de la reserva debe ser una fecha futura.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'idpropiedad.required' => 'Elige la propiedad que se va a reservar.',
            'idpropiedad.exists' => 'La propiedad elegida no existe.',
            'idcliente.required' => 'Elige el cliente que reserva.',
            'idcliente.exists' => 'El cliente no existe en el CRM. Regístralo primero como prospecto.',
            'idvisita.exists' => 'La visita elegida no existe.',
            'monto.required' => 'Indica cuánto paga el cliente para reservar.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.gt' => 'El monto debe ser mayor a cero.',
            'monto.max' => 'El monto es demasiado grande.',
            'monto.decimal' => 'El monto puede tener como máximo 2 decimales.',
            'moneda.required' => 'Elige la moneda del pago.',
            'moneda.in' => 'La moneda debe ser dólares (USD) o bolivianos (BOB).',
            'tipocambio.required' => 'Indica el tipo de cambio oficial del día para el pago en bolivianos.',
            'tipocambio.numeric' => 'El tipo de cambio debe ser un número.',
            'tipocambio.gt' => 'El tipo de cambio debe ser mayor a cero.',
            'tipocambio.decimal' => 'El tipo de cambio puede tener como máximo 4 decimales.',
            'fechaplazo.required' => 'Indica la fecha de plazo de la reserva.',
            'fechaplazo.date_format' => 'La fecha de plazo no es válida.',
        ];
    }
}
