<?php

namespace App\Modules\CrmGestionClientes\CU25_RegistrarResena;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Resena;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Reglas de la reseña: el cliente debe tener un contrato vigente o finalizado sobre la propiedad, solo una reseña por propiedad,
 * puntuación entera de 1 a 5 y comentario opcional. Si no se cumple lo primero o lo segundo se redirige con un aviso
 * (no se usa 403 porque esa pantalla no muestra mensajes).
 */
class ResenaRequest extends FormRequest
{
    private ?string $aviso = null;
    private ?string $destino = null;

    public function authorize(): bool
    {
        $cliente = Cliente::where('idusuariocuenta', $this->user()->idusuario)->first();
        $propiedad = $this->route('propiedad');

        if (! $cliente || ! self::tieneContrato($cliente, $propiedad->idpropiedad)) {
            $this->aviso = 'Solo puedes reseñar propiedades sobre las que tienes un contrato.';
            $this->destino = route('portal.contratos');

            return false;
        }

        if (Resena::where('idcliente', $cliente->idcliente)->where('idpropiedad', $propiedad->idpropiedad)->exists()) {
            $this->aviso = 'Ya registraste una reseña de esta propiedad.';
            $this->destino = route('resenas.crear', $propiedad);

            return false;
        }

        return true;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(redirect($this->destino)->with('error', $this->aviso));
    }

    /** Contrato vigente o finalizado del cliente sobre la propiedad (el rescindido no habilita). */
    public static function tieneContrato(Cliente $cliente, int $idpropiedad): bool
    {
        return Contrato::where('idcliente', $cliente->idcliente)
            ->where('idpropiedad', $idpropiedad)
            ->whereIn('estado', [Contrato::VIGENTE, Contrato::FINALIZADO])
            ->exists();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('comentario'))) {
            $this->merge(['comentario' => trim($this->input('comentario')) ?: null]);
        }
    }

    public function rules(): array
    {
        return [
            'puntuacion' => ['required', 'integer', 'between:1,5'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'puntuacion.required' => 'Elige una puntuación.',
            'puntuacion.integer' => 'La puntuación debe estar entre 1 y 5.',
            'puntuacion.between' => 'La puntuación debe estar entre 1 y 5.',
            'comentario.max' => 'El comentario no puede superar los 1000 caracteres.',
        ];
    }
}