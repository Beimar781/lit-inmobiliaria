<?php

namespace App\Modules\AgendaVisitasPagos\CU26_GestionarReservas;

use App\Models\Pago;
use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validaciones del CU26 (paso 6): la visita realizada en la que se apoya la reserva (de ahí salen la propiedad y el cliente),
 * lo que paga el cliente en efectivo (USD o BOB con su tipo de cambio) y un plazo futuro.
 */
class ReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso por rol está en routes.php
    }

    public function rules(): array
    {
        return [
            'idvisita' => ['required', 'exists:visita,idvisita'],
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
            'idvisita.required' => 'Elige la visita realizada en la que se apoya la reserva.',
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
