<?php

namespace App\Modules\AgendaVisitasPagos\CU27_GestionarContratos;

use App\Models\Propiedad;
use App\Models\Rol;
use Illuminate\Foundation\Http\FormRequest;

/** Validaciones del CU27 (paso 7): propiedad, cliente del CRM, monto en dólares mayor a cero y fechas coherentes. */
class ContratoRequest extends FormRequest
{
    /**
     * El rol está en routes.php; aquí se revisa el permiso sobre la propiedad ANTES de validar el formulario,
     * así un Agente que quiere formalizar una propiedad que no registró recibe siempre 403.
     */
    public function authorize(): bool
    {
        $propiedad = Propiedad::find($this->input('idpropiedad'));

        abort_if(
            $propiedad && $this->user()->tieneRol(Rol::AGENTE) && $propiedad->idusuario !== $this->user()->idusuario,
            403,
            'Solo puedes formalizar las propiedades que tú registraste.'
        );

        return true;
    }

    public function rules(): array
    {
        return [
            'idpropiedad' => ['required', 'exists:propiedad,idpropiedad'],
            'idcliente' => ['required', 'exists:cliente,idcliente'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'fechainicio' => ['required', 'date_format:Y-m-d'],
            'fechafin' => ['nullable', 'date_format:Y-m-d', 'after:fechainicio'],
        ];
    }

    public function messages(): array
    {
        return [
            'idpropiedad.required' => 'Elige la propiedad del contrato.',
            'idpropiedad.exists' => 'La propiedad elegida no existe.',
            'idcliente.required' => 'Elige el cliente que firma el contrato.',
            'idcliente.exists' => 'El cliente no existe en el CRM. Regístralo primero como prospecto.',
            'monto.required' => 'Indica el monto del contrato en dólares.',
            'monto.numeric' => 'El monto debe ser un número.',
            'monto.gt' => 'El monto debe ser mayor a cero.',
            'monto.max' => 'El monto es demasiado grande.',
            'monto.decimal' => 'El monto puede tener como máximo 2 decimales.',
            'fechainicio.required' => 'Indica la fecha de inicio del contrato.',
            'fechainicio.date_format' => 'La fecha de inicio no es válida.',
            'fechafin.date_format' => 'La fecha de fin no es válida.',
            'fechafin.after' => 'La fecha de fin debe ser posterior a la de inicio.',
        ];
    }
}
