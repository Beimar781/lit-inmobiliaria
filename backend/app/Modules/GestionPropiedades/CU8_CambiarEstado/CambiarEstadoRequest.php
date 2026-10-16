<?php

namespace App\Modules\GestionPropiedades\CU8_CambiarEstado;

use App\Models\Propiedad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Validaciones del CU8: el estado nuevo debe ser válido para el tipo de operación y distinto del actual. */
class CambiarEstadoRequest extends FormRequest
{
    /**
     * El rol está en routes.php; aquí se revisa el permiso sobre la propiedad ANTES de validar el formulario,
     * así un agente ajeno recibe siempre 403 (y no un aviso de validación).
     */
    public function authorize(): bool
    {
        /** @var Propiedad $propiedad */
        $propiedad = $this->route('propiedad');

        abort_if($propiedad->estadopropiedad === Propiedad::BAJA, 404);

        abort_unless(
            $this->user()->tieneRol('Administrador') || $propiedad->idusuario === $this->user()->idusuario,
            403,
            'Solo puedes cambiar el estado de las propiedades que tú registraste.'
        );

        return true;
    }

    public function rules(): array
    {
        /** @var Propiedad $propiedad */
        $propiedad = $this->route('propiedad');

        return [
            'estadopropiedad' => ['required', Rule::in(Propiedad::estadosPermitidos($propiedad->tipopropiedad))],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'estadopropiedad.required' => 'Elige el nuevo estado de la propiedad.',
            'estadopropiedad.in' => 'Ese estado no es válido para este tipo de operación.',
            'motivo.max' => 'El motivo no puede superar los 500 caracteres.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('estadopropiedad')) {
                    return;
                }

                /** @var Propiedad $propiedad */
                $propiedad = $this->route('propiedad');
                $nuevo = $this->input('estadopropiedad');

                if ($nuevo === $propiedad->estadopropiedad) {
                    $validator->errors()->add('estadopropiedad', 'La propiedad ya está en ese estado.');

                    return;
                }

                // Flujo alternativo: si la operación "se cayó" (Vendida/Alquilada -> Disponible) hay que explicar por qué.
                $cerrada = in_array($propiedad->estadopropiedad, [Propiedad::VENDIDO, Propiedad::ALQUILADO], true);

                if ($cerrada && $nuevo === Propiedad::DISPONIBLE && trim((string) $this->input('motivo')) === '') {
                    $validator->errors()->add('motivo', 'Indica el motivo para volver a dejar disponible una propiedad ' . strtolower($propiedad->estadopropiedad) . '.');
                }
            },
        ];
    }
}
