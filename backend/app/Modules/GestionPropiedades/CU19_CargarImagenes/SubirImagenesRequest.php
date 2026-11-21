<?php

namespace App\Modules\GestionPropiedades\CU19_CargarImagenes;

use App\Models\Propiedad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Validaciones de la subida de imágenes: formato JPG/PNG/WebP, hasta 4 MB cada una y 8 por propiedad en total. */
class SubirImagenesRequest extends FormRequest
{
    public const MAXIMO = 8;

    /** Se revisa el permiso sobre la propiedad ANTES de validar: un agente ajeno siempre recibe 403. */
    public function authorize(): bool
    {
        ImagenesController::autorizar($this->route('propiedad'));

        return true;
    }

    public function rules(): array
    {
        return [
            'imagenes' => ['required', 'array'],
            'imagenes.*' => ['bail', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        $formato = 'El archivo debe ser una imagen JPG, PNG o WebP.';

        return [
            'imagenes.required' => 'Selecciona al menos una imagen.',
            'imagenes.array' => 'Selecciona al menos una imagen.',
            'imagenes.*.file' => $formato,
            'imagenes.*.uploaded' => 'Cada imagen debe pesar como máximo 4 MB.',
            'imagenes.*.mimes' => $formato,
            'imagenes.*.max' => 'Cada imagen debe pesar como máximo 4 MB.',
        ];
    }

    /** Las ya guardadas más las nuevas no pueden pasar de 8; si se pasa, no se guarda ninguna de la tanda. */
    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var Propiedad $propiedad */
            $propiedad = $this->route('propiedad');
            $nuevas = count((array) $this->file('imagenes'));

            if ($propiedad->imagenes()->count() + $nuevas > self::MAXIMO) {
                $validator->errors()->add('imagenes', 'Puedes tener como máximo 8 imágenes por propiedad.');
            }
        }];
    }
}