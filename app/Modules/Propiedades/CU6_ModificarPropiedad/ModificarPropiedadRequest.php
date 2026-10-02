<?php

namespace App\Modules\Propiedades\CU6_ModificarPropiedad;

use App\Models\Propiedad;
use App\Modules\Propiedades\Compartido\PropiedadRequestBase;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ModificarPropiedadRequest extends PropiedadRequestBase
{
    public function rules(): array
    {
        return array_merge($this->reglasBase(), [
            // BAJA no se elige aquí: se hace con el CU7.
            'estadopropiedad' => ['required', Rule::in([
                Propiedad::DISPONIBLE, Propiedad::RESERVADO, Propiedad::VENDIDO, Propiedad::ALQUILADO,
            ])],
            'imagenes' => ['nullable', 'array', 'max:8'],
            'eliminar_imagenes' => ['nullable', 'array'],
            'eliminar_imagenes.*' => ['integer'],
        ]);
    }

    public function after(): array
    {
        return array_merge(parent::after(), [
            // Máximo 8 imágenes en total (las que ya tiene - las que elimina + las nuevas)
            function (Validator $validator) {
                $propiedad = $this->route('propiedad');

                $actuales = $propiedad->imagenes()->count();
                $eliminadas = $propiedad->imagenes()
                    ->whereIn('idimagen', (array) $this->input('eliminar_imagenes', []))
                    ->count();
                $nuevas = count($this->file('imagenes') ?? []);

                if ($actuales - $eliminadas + $nuevas > 8) {
                    $validator->errors()->add('imagenes', 'Una propiedad puede tener como máximo 8 imágenes.');
                }
            },
        ]);
    }
}
