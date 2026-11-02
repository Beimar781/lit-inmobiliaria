<?php

namespace App\Modules\AgendaVisitasPagos\CU26_GestionarReservas;

use App\Models\Propiedad;
use App\Models\Rol;
use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use Illuminate\Foundation\Http\FormRequest;

/** Validaciones del CU26 (paso 6): propiedad, cliente del CRM y un plazo futuro. */
class ReservaRequest extends FormRequest
{
    /**
     * El rol está en routes.php; aquí se revisa el permiso sobre la propiedad ANTES de validar el formulario,
     * así un Agente que quiere reservar una propiedad que no registró recibe siempre 403.
     */
    public function authorize(): bool
    {
        $propiedad = Propiedad::find($this->input('idpropiedad'));

        abort_if(
            $propiedad && $this->user()->tieneRol(Rol::AGENTE) && $propiedad->idusuario !== $this->user()->idusuario,
            403,
            'Solo puedes reservar las propiedades que tú registraste.'
        );

        return true;
    }

    public function rules(): array
    {
        return [
            'idpropiedad' => ['required', 'exists:propiedad,idpropiedad'],
            'idcliente' => ['required', 'exists:cliente,idcliente'],
            'fechaplazo' => [
                'bail', 'required', 'date_format:Y-m-d',
                function (string $atributo, mixed $fecha, \Closure $falla) {
                    if ($fecha <= AgendaDeVisitas::ahora()->toDateString()) {
                        $falla('El plazo de la reserva debe ser una fecha futura.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'idpropiedad.required' => 'Elige la propiedad que se va a reservar.',
            'idpropiedad.exists' => 'La propiedad elegida no existe.',
            'idcliente.required' => 'Elige el cliente que reserva.',
            'idcliente.exists' => 'El cliente no existe en el CRM. Regístralo primero como prospecto.',
            'fechaplazo.required' => 'Indica la fecha de plazo de la reserva.',
            'fechaplazo.date_format' => 'La fecha de plazo no es válida.',
        ];
    }
}
