<?php

namespace App\Modules\AutentificacionSeguridad\CU4_GestionarUsuarios;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validaciones del formulario de usuarios (sirve para registrar y para editar). */
class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso ya está limitado al Administrador en routes.php
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
        $usuario = $this->route('usuario'); // null cuando se está registrando
        $reglasPassword = ['min:8', 'regex:/[A-Za-z]/', 'regex:/[0-9]/', 'confirmed'];

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255',
                Rule::unique('usuario', 'email')->ignore($usuario?->idusuario, 'idusuario')],
            'telefono' => ['nullable', 'regex:/^[0-9+\s-]{7,20}$/'],
            'idrol' => ['required', 'exists:rol,idrol'],
            'estado' => ['required', Rule::in([Usuario::ACTIVO, Usuario::INACTIVO])],
            'password' => $usuario === null
                ? array_merge(['required'], $reglasPassword)
                : array_merge(['nullable'], $reglasPassword),
        ];
    }

    public function messages(): array
    {
        return [
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex' => 'La contraseña debe incluir letras y números.',
            'telefono.regex' => 'El teléfono solo puede tener números (de 7 a 20 dígitos).',
        ];
    }
}
