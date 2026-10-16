<?php

namespace App\Modules\CrmGestionClientes\CU12_RegistrarClienteInteresado;

/** Formulario público "Me interesa" de la ficha de la propiedad. */
class InteresPublicoRequest extends ClienteInteresadoRequestBase
{
    public function rules(): array
    {
        return $this->reglasBase();
    }
}
