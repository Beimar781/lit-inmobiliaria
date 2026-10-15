<?php

namespace App\Modules\Propiedades\Compartido;

use App\Models\Propiedad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Validaciones comunes de CU5 (registrar) y CU6 (modificar). */
abstract class PropiedadRequestBase extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso por rol ya está en routes.php
    }

    protected function reglasBase(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'precio' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'tipopropiedad' => ['required', Rule::in(Propiedad::TIPOS)],
            'superficie' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'areaconstruida' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'habitaciones' => ['nullable', 'integer', 'min:0', 'max:100'],
            'banos' => ['nullable', 'integer', 'min:0', 'max:100'],
            'antiguedad' => ['nullable', 'integer', 'min:0', 'max:200'],
            'idcategoria' => ['required', 'exists:categoria,idcategoria'],

            // Propietario existente, o datos del nuevo propietario si no se elige ninguno
            'idpropietario' => ['nullable', 'exists:propietario,idpropietario'],
            'propietario_nombre' => ['required_without:idpropietario', 'nullable', 'string', 'max:255'],
            'propietario_telefono' => ['nullable', 'string', 'max:20'],
            'propietario_email' => ['nullable', 'email', 'max:255'],
            'propietario_direccion' => ['nullable', 'string', 'max:500'],

            // Ubicación (debe estar dentro del 4.º anillo, ver after())
            'zona' => ['required', 'string', 'max:150'],
            'direccion' => ['required', 'string', 'max:500'],
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],

            'imagenes.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'portada' => ['nullable', 'regex:/^(img|nueva)-\d+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'imagenes.min' => 'Debes subir al menos una imagen de la propiedad.',
            'imagenes.max' => 'Puedes tener como máximo 8 imágenes por propiedad.',
            'imagenes.required' => 'Debes subir al menos una imagen de la propiedad.',
        ];
    }

    /** Alcance del proyecto: solo propiedades dentro del 4.º anillo de Santa Cruz de la Sierra. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['latitud', 'longitud'])) {
                    return;
                }

                $latitud = $this->input('latitud');
                $longitud = $this->input('longitud');

                if (is_numeric($latitud) && is_numeric($longitud)
                    && ! LimiteCuartoAnillo::contiene((float) $latitud, (float) $longitud)) {
                    $validator->errors()->add(
                        'latitud',
                        'La ubicación debe estar dentro del 4.º anillo de ' . config('santacruz.ciudad') . '.'
                    );
                }
            },
        ];
    }
}
