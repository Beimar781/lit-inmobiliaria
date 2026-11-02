<?php

namespace App\Modules\AgendaVisitasPagos\CU27_GestionarContratos;

use App\Models\Contrato;
use App\Models\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validaciones del cambio de estado de un contrato: Finalizar o Rescindir (el motivo es obligatorio al rescindir). */
class CambiarEstadoContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Contrato $contrato */
        $contrato = $this->route('contrato');

        abort_if(
            $this->user()->tieneRol(Rol::AGENTE) && $contrato->propiedad->idusuario !== $this->user()->idusuario,
            403,
            'Solo puedes gestionar los contratos de las propiedades que tú registraste.'
        );

        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in([Contrato::FINALIZADO, Contrato::RESCINDIDO])],
            'motivo' => [
                Rule::requiredIf(fn () => $this->input('estado') === Contrato::RESCINDIDO),
                'nullable', 'string', 'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'Elige el nuevo estado del contrato.',
            'estado.in' => 'Ese estado no es válido: solo se puede finalizar o rescindir un contrato.',
            'motivo.required' => 'Indica el motivo para rescindir el contrato.',
            'motivo.max' => 'El motivo no puede superar los 500 caracteres.',
        ];
    }
}
