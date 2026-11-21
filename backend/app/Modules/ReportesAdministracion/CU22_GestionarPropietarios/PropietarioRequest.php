<?php

namespace App\Modules\ReportesAdministracion\CU22_GestionarPropietarios;

use App\Support\FormatoTelefono;
use Illuminate\Foundation\Http\FormRequest;

/** Validaciones del formulario de propietarios (sirve para registrar y para editar). */
class PropietarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso por rol está en routes.php
    }

    protected function prepareForValidation(): void
    {
        foreach (['nombre', 'telefono', 'email', 'direccion'] as $campo) {
            if (is_string($this->input($campo))) {
                $this->merge([$campo => trim($this->input($campo))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'regex:' . FormatoTelefono::REGEX],
            'email' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del propietario es obligatorio.',
            'nombre.max' => 'El nombre no puede pasar de 255 caracteres.',
            'email.email' => 'El correo no tiene un formato válido.',
            'telefono.regex' => 'El teléfono no tiene un formato válido.',
            'direccion.max' => 'La dirección no puede pasar de 500 caracteres.',
        ];
    }
}