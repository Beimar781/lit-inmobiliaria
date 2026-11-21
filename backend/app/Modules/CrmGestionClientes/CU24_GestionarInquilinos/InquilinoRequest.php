<?php

namespace App\Modules\CrmGestionClientes\CU24_GestionarInquilinos;

use App\Models\Inquilino;
use App\Modules\CrmGestionClientes\Compartido\AccesoAProspecto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validaciones del inquilino: ocupación, tipo de garantía, detalle (obligatorio salvo "Ninguna") y estado. */
class InquilinoRequest extends FormRequest
{
    /** Se revisa el acceso al prospecto ANTES de validar: un agente ajeno siempre recibe 403. */
    public function authorize(): bool
    {
        AccesoAProspecto::autorizar($this->route('cliente'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['ocupacion', 'detallegarantia'] as $campo) {
            if (is_string($this->input($campo))) {
                $this->merge([$campo => trim($this->input($campo))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'ocupacion' => ['required', 'string', 'max:100'],
            'tipogarantia' => ['required', Rule::in(array_keys(Inquilino::GARANTIAS))],
            'detallegarantia' => [
                Rule::requiredIf(fn () => in_array($this->input('tipogarantia'), array_keys(Inquilino::GARANTIAS), true)
                    && $this->input('tipogarantia') !== 'NINGUNA'),
                'nullable', 'string', 'max:2000',
            ],
            'estado' => ['nullable', Rule::in(array_keys(Inquilino::ESTADOS))],
        ];
    }

    public function messages(): array
    {
        return [
            'ocupacion.required' => 'La ocupación es obligatoria.',
            'ocupacion.max' => 'La ocupación no puede pasar de 100 caracteres.',
            'tipogarantia.required' => 'Elige el tipo de garantía.',
            'tipogarantia.in' => 'Elige el tipo de garantía.',
            'detallegarantia.required' => 'Describe la garantía.',
            'detallegarantia.max' => 'El detalle de la garantía no puede pasar de 2000 caracteres.',
            'estado.in' => 'El estado del inquilino debe ser activo o inactivo.',
        ];
    }
}