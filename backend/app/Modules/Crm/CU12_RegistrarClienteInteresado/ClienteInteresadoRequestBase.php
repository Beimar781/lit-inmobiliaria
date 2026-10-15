<?php

namespace App\Modules\Crm\CU12_RegistrarClienteInteresado;

use Illuminate\Foundation\Http\FormRequest;

/** Validaciones comunes del CU12: nombre, y correo y/o teléfono (al menos uno), más un mensaje opcional. */
abstract class ClienteInteresadoRequestBase extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso por rol está en routes.php
    }

    protected function reglasBase(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['nullable', 'required_without:telefono', 'email', 'max:255'],
            'telefono' => ['nullable', 'required_without:email', 'regex:/^\+?[0-9][0-9\s\-]{5,18}[0-9]$/'],
            'mensaje' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre.',
            'nombre.min' => 'El nombre debe tener al menos 2 caracteres.',
            'email.required_without' => 'Indica un correo electrónico o un teléfono de contacto.',
            'telefono.required_without' => 'Indica un correo electrónico o un teléfono de contacto.',
            'telefono.regex' => 'El teléfono solo puede tener números (7 a 20 dígitos), con o sin el signo + inicial.',
            'mensaje.max' => 'El mensaje no puede superar los 1000 caracteres.',
        ];
    }
}
