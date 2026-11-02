<?php

namespace App\Modules\AgendaVisitasPagos\CU15_ReagendarVisita;

use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validaciones del CU15 (paso 6): nueva fecha y hora futuras en horario de atención, y el motivo del cambio. */
class ReagendarVisitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso por rol está en routes.php y el de cada visita, en el controlador
    }

    public function rules(): array
    {
        return [
            'fecha' => [
                'bail', 'required', 'date_format:Y-m-d',
                function (string $atributo, mixed $fecha, \Closure $falla) {
                    $hora = (string) $this->input('hora');
                    if (in_array($hora, AgendaDeVisitas::horas(), true) && ! AgendaDeVisitas::esFutura($fecha, $hora)) {
                        $falla('La nueva fecha y hora deben ser futuras.');
                    }
                },
            ],
            'hora' => ['required', Rule::in(AgendaDeVisitas::horas())],
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'Elige la nueva fecha de la visita.',
            'fecha.date_format' => 'La fecha no es válida.',
            'hora.required' => 'Elige la nueva hora de la visita.',
            'hora.in' => 'Elige una hora entre las 08:00 y las 17:00 (bloques de una hora).',
            'motivo.required' => 'El motivo del cambio es obligatorio.',
            'motivo.min' => 'El motivo debe tener al menos 3 caracteres.',
            'motivo.max' => 'El motivo no puede superar los 500 caracteres.',
        ];
    }
}
