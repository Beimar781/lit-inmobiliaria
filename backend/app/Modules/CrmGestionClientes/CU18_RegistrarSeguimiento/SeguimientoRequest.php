<?php

namespace App\Modules\CrmGestionClientes\CU18_RegistrarSeguimiento;

use App\Models\Seguimiento;
use App\Modules\CrmGestionClientes\Compartido\AccesoAProspecto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validaciones del seguimiento: medio, nota, fecha del contacto (no futura), propiedad y próximo contacto (opcionales). */
class SeguimientoRequest extends FormRequest
{
    /** Se revisa el acceso al prospecto ANTES de validar: un agente ajeno siempre recibe 403. */
    public function authorize(): bool
    {
        AccesoAProspecto::autorizar($this->route('cliente'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nota'))) {
            $this->merge(['nota' => trim($this->input('nota'))]);
        }
        foreach (['idpropiedad', 'proximocontacto'] as $campo) {
            if ($this->input($campo) === '') {
                $this->merge([$campo => null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'medio' => ['required', Rule::in(array_keys(Seguimiento::MEDIOS))],
            'nota' => ['required', 'string', 'max:2000'],
            // 1 minuto de tolerancia por el reloj del navegador
            'fechacontacto' => ['required', 'date', 'before_or_equal:' . now()->addMinute()->toDateTimeString()],
            'proximocontacto' => ['nullable', 'date', 'after:today'],
            'idpropiedad' => ['nullable', 'integer', 'exists:propiedad,idpropiedad'],
        ];
    }

    public function messages(): array
    {
        return [
            'medio.required' => 'Elige un medio de contacto válido.',
            'medio.in' => 'Elige un medio de contacto válido.',
            'nota.required' => 'Escribe la nota del seguimiento.',
            'nota.max' => 'La nota no puede pasar de 2000 caracteres.',
            'fechacontacto.required' => 'Indica la fecha y hora del contacto.',
            'fechacontacto.date' => 'La fecha del contacto no es válida.',
            'fechacontacto.before_or_equal' => 'La fecha del contacto no puede ser futura.',
            'proximocontacto.date' => 'La fecha del próximo contacto debe ser posterior a hoy.',
            'proximocontacto.after' => 'La fecha del próximo contacto debe ser posterior a hoy.',
            'idpropiedad.integer' => 'La propiedad indicada no existe.',
            'idpropiedad.exists' => 'La propiedad indicada no existe.',
        ];
    }
}