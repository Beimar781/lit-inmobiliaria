<?php

namespace App\Modules\Busqueda\CU10_FiltrarPropiedades;

use App\Models\Propiedad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validaciones del catálogo: la palabra clave del CU9 y los filtros del CU10 (valores numéricos y mínimos no mayores que los máximos).
 * Si algo no es válido, se vuelve al catálogo completo mostrando el error (no se filtra).
 */
class FiltrarPropiedadesRequest extends FormRequest
{
    protected $redirectRoute = 'catalogo.index';

    public function authorize(): bool
    {
        return true; // el catálogo es público
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', Rule::in(Propiedad::TIPOS)],
            'categoria' => ['nullable', 'integer', 'exists:categoria,idcategoria'],
            'precio_min' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'precio_max' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'zona' => ['nullable', 'string', 'max:150'],
            'superficie_min' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'superficie_max' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'habitaciones' => ['nullable', 'integer', 'min:0', 'max:100'],
            'banos' => ['nullable', 'integer', 'min:0', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.max' => 'El texto de búsqueda es demasiado largo (máximo 100 caracteres).',
            'precio_min.numeric' => 'El precio mínimo debe ser un número.',
            'precio_max.numeric' => 'El precio máximo debe ser un número.',
            'superficie_min.numeric' => 'La superficie mínima debe ser un número.',
            'superficie_max.numeric' => 'La superficie máxima debe ser un número.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ([['precio_min', 'precio_max', 'precio'], ['superficie_min', 'superficie_max', 'superficie']] as [$min, $max, $nombre]) {
                    if ($validator->errors()->hasAny([$min, $max])) {
                        continue;
                    }

                    $desde = $this->input($min);
                    $hasta = $this->input($max);

                    if (is_numeric($desde) && is_numeric($hasta) && (float) $desde > (float) $hasta) {
                        $validator->errors()->add($min, "El {$nombre} mínimo no puede ser mayor que el máximo.");
                    }
                }
            },
        ];
    }

    public function filtros(): FiltrosPropiedades
    {
        return FiltrosPropiedades::desde($this->validated());
    }
}
