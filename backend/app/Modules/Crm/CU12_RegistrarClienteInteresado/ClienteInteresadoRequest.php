<?php

namespace App\Modules\Crm\CU12_RegistrarClienteInteresado;

use App\Models\Cliente;
use Illuminate\Validation\Rule;

/** Formulario del agente: además indica la propiedad y el tipo de cliente (Comprador, Inversionista o Inquilino). */
class ClienteInteresadoRequest extends ClienteInteresadoRequestBase
{
    public function rules(): array
    {
        return array_merge($this->reglasBase(), [
            'idpropiedad' => ['required', 'exists:propiedad,idpropiedad'],
            'tipocliente' => ['required', Rule::in(Cliente::TIPOS)],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'idpropiedad.required' => 'Elige la propiedad por la que preguntó.',
            'tipocliente.required' => 'Elige el tipo de cliente.',
        ]);
    }
}
