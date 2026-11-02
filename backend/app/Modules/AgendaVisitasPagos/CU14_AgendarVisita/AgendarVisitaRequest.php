<?php

namespace App\Modules\AgendaVisitasPagos\CU14_AgendarVisita;

use App\Models\Rol;
use App\Modules\AgendaVisitasPagos\Compartido\AgendaDeVisitas;
use App\Modules\CrmGestionClientes\CU12_RegistrarClienteInteresado\ClienteInteresadoRequestBase;
use Illuminate\Validation\Rule;

/**
 * Validaciones del CU14 (paso 5): fecha y hora futuras en horario de atención, y los datos de contacto del cliente
 * (nombre, y correo o teléfono; las mismas reglas del CU12). El Agente o el Administrador pueden elegir un cliente del CRM
 * en vez de escribir sus datos.
 */
class AgendarVisitaRequest extends ClienteInteresadoRequestBase
{
    /** ¿Quien envía el formulario es personal de la inmobiliaria que agenda en nombre de un cliente? */
    public function esPersonal(): bool
    {
        return (bool) $this->user()?->tieneRol(Rol::ADMINISTRADOR, Rol::AGENTE);
    }

    public function rules(): array
    {
        $conClienteDelCrm = $this->esPersonal() && $this->filled('idcliente');

        $reglas = $conClienteDelCrm
            ? [
                'idcliente' => ['required', 'exists:cliente,idcliente'],
                'nombre' => ['nullable'], 'email' => ['nullable'], 'telefono' => ['nullable'],
            ]
            : $this->reglasBase();

        unset($reglas['mensaje']);

        return array_merge($reglas, [
            'fecha' => [
                'bail', 'required', 'date_format:Y-m-d',
                function (string $atributo, mixed $fecha, \Closure $falla) {
                    // Solo se compara si la hora también es válida; si no, ya fallará la regla de la hora.
                    $hora = (string) $this->input('hora');
                    if (in_array($hora, AgendaDeVisitas::horas(), true) && ! AgendaDeVisitas::esFutura($fecha, $hora)) {
                        $falla('La fecha y la hora de la visita deben ser futuras.');
                    }
                },
            ],
            'hora' => ['required', Rule::in(AgendaDeVisitas::horas())],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'fecha.required' => 'Elige la fecha de la visita.',
            'fecha.date_format' => 'La fecha no es válida.',
            'hora.required' => 'Elige la hora de la visita.',
            'hora.in' => 'Elige una hora entre las 08:00 y las 17:00 (bloques de una hora).',
            'idcliente.exists' => 'El cliente elegido no existe en el CRM.',
            'observacion.max' => 'La observación no puede superar los 1000 caracteres.',
        ]);
    }
}
