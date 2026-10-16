<?php

namespace App\Modules\GestionPropiedades\CU5_RegistrarPropiedad;

use App\Modules\GestionPropiedades\Compartido\PropiedadRequestBase;

class RegistrarPropiedadRequest extends PropiedadRequestBase
{
    public function rules(): array
    {
        return array_merge($this->reglasBase(), [
            // Al registrar, se necesita al menos una imagen (paso 5 del flujo).
            'imagenes' => ['required', 'array', 'min:1', 'max:8'],
        ]);
    }
}
