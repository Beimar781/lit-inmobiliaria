<?php

namespace App\Modules\AgendaVisitasPagos\CU28_RegistrarPago;

use App\Models\Pago;
use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validaciones del CU28 (paso 6): contrato, monto mayor a cero, moneda (USD o BOB), tipo de cambio obligatorio en bolivianos,
 * fecha de pago que no sea futura y método de pago.
 */
class RegistrarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso por rol está en routes.php
    }

    public function rules(): array
    {
        return [
            'idcontrato' => ['required', 'exists:contrato,idcontrato'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'moneda' => ['required', Rule::in(Pago::MONEDAS)],
            // El dólar oficial cambia a diario: en bolivianos el tipo de cambio es obligatorio y lo escribe la persona.
            'tipocambio' => [Rule::requiredIf(fn () => $this->input('moneda') === Pago::BOB), 'nullable', 'numeric', 'gt:0', 'max:99999', 'decimal:0,4'],
            'fechapago' => [
                'bail', 'required', 'date_format:Y-m-d',
                function (string $atributo, mixed $fecha, \Closure $falla) {
                    if ($fecha > AgendaDeVisitas::ahora()->toDateString()) {
                        $falla('La fecha de pago no puede ser futura.');
                    }
                },
            ],
            'metodo' => ['required', Rule::in(array_keys(Pago::METODOS))],
        ];
    }

    public function messages(): array
    {
        return [
            'idcontrato.required' => 'Elige el contrato al que corresponde el pago.',
            'idcontrato.exists' => 'El contrato elegido no existe.',
            'monto.required' => 'Indica el monto del pago.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.gt' => 'El monto debe ser mayor a cero.',
            'monto.max' => 'El monto es demasiado grande.',
            'monto.decimal' => 'El monto puede tener como máximo 2 decimales.',
            'moneda.required' => 'Elige la moneda del pago.',
            'moneda.in' => 'La moneda debe ser dólares (USD) o bolivianos (BOB).',
            'tipocambio.required' => 'Indica el tipo de cambio oficial del día para el pago en bolivianos.',
            'tipocambio.numeric' => 'El tipo de cambio debe ser un número.',
            'tipocambio.gt' => 'El tipo de cambio debe ser mayor a cero.',
            'tipocambio.max' => 'El tipo de cambio es demasiado grande.',
            'tipocambio.decimal' => 'El tipo de cambio puede tener como máximo 4 decimales.',
            'fechapago.required' => 'Indica la fecha del pago.',
            'fechapago.date_format' => 'La fecha del pago no es válida.',
            'metodo.required' => 'Elige el método de pago.',
            'metodo.in' => 'El método de pago no es válido.',
        ];
    }
}
