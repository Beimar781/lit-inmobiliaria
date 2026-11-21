<?php

namespace App\Modules\GestionPropiedades\CU21_GestionarGeolocalizacion;

use App\Modules\GestionPropiedades\Compartido\LimiteCuartoAnillo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Validaciones de la ubicación: zona y dirección, y un punto numérico dentro del 4.º anillo (regla compartida con CU5 y CU6). */
class UbicacionRequest extends FormRequest
{
    /** Se revisa el permiso sobre la propiedad ANTES de validar: un agente ajeno siempre recibe 403. */
    public function authorize(): bool
    {
        GeolocalizacionController::autorizar($this->route('propiedad'));

        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['zona', 'direccion', 'latitud', 'longitud'] as $campo) {
            if (is_string($this->input($campo))) {
                $this->merge([$campo => trim($this->input($campo))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'zona' => ['required', 'string', 'max:150'],
            'direccion' => ['required', 'string', 'max:500'],
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'zona.required' => 'La zona es obligatoria.',
            'zona.max' => 'La zona no puede pasar de 150 caracteres.',
            'direccion.required' => 'La dirección es obligatoria.',
            'direccion.max' => 'La dirección no puede pasar de 500 caracteres.',
            'latitud.required' => 'Marca la ubicación de la propiedad en el mapa.',
            'longitud.required' => 'Marca la ubicación de la propiedad en el mapa.',
            'latitud.numeric' => 'La latitud debe ser un número.',
            'longitud.numeric' => 'La longitud debe ser un número.',
            'latitud.between' => 'La latitud debe estar entre -90 y 90.',
            'longitud.between' => 'La longitud debe estar entre -180 y 180.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->hasAny(['latitud', 'longitud'])) {
                return;
            }

            if (! LimiteCuartoAnillo::contiene((float) $this->input('latitud'), (float) $this->input('longitud'))) {
                $validator->errors()->add('latitud', 'La ubicación debe estar dentro del 4.º anillo de ' . config('santacruz.ciudad') . '.');
            }
        }];
    }
}