<?php

namespace App\Modules\Autenticacion\CU3_RecuperarPassword;

use Illuminate\Foundation\Http\FormRequest;

class OlvidoPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
