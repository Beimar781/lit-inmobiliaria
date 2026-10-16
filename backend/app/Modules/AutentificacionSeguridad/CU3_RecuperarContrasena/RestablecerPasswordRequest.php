<?php

namespace App\Modules\AutentificacionSeguridad\CU3_RecuperarContrasena;

use Illuminate\Foundation\Http\FormRequest;

class RestablecerPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** PostgreSQL distingue mayúsculas en los correos: se buscan y se guardan siempre en minúsculas. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8', 'regex:/[A-Za-z]/', 'regex:/[0-9]/'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'password.required' => 'Ingresa la nueva contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex' => 'La contraseña debe incluir letras y números.',
        ];
    }
}
