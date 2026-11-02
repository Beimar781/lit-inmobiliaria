<?php

namespace App\Modules\AgendaVisitasPagos\CU16_CancelarVisita;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validaciones del CU16 (pasos 4 y 5). El motivo solo se exige si el actor confirma la cancelación:
 * si desiste (botón «No, mantener la visita»), no se valida nada y no se cambia la visita.
 */
class CancelarVisitaRequest extends FormRequest
{
    public const CANCELAR = 'cancelar';
    public const MANTENER = 'mantener';

    public function authorize(): bool
    {
        return true; // el acceso por rol está en routes.php y el de cada visita, en el controlador
    }

    /** ¿El actor confirmó explícitamente la cancelación? */
    public function confirmada(): bool
    {
        return $this->input('accion') === self::CANCELAR;
    }

    public function rules(): array
    {
        return [
            'motivo' => $this->confirmada() ? ['required', 'string', 'min:3', 'max:500'] : ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo de la cancelación es obligatorio.',
            'motivo.min' => 'El motivo debe tener al menos 3 caracteres.',
            'motivo.max' => 'El motivo no puede superar los 500 caracteres.',
        ];
    }
}
