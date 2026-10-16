<?php

namespace App\Modules\AutentificacionSeguridad\CU3_RecuperarContrasena;

use Illuminate\Foundation\Http\FormRequest;

class OlvidoPasswordRequest extends FormRequest
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
        return ['email' => ['required', 'email']];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.email' => 'Ingresa un correo electrónico válido.',
        ];
    }
}
