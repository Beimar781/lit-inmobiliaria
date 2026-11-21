<?php

namespace App\Modules\ReportesAdministracion\CU23_GestionarCategorias;

use Illuminate\Foundation\Http\FormRequest;

/** Validaciones del formulario de categorías (sirve para registrar y para editar). */
class CategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso ya está limitado al Administrador en routes.php
    }

    protected function prepareForValidation(): void
    {
        foreach (['nombre', 'descripcion'] as $campo) {
            if (is_string($this->input($campo))) {
                $this->merge([$campo => trim($this->input($campo))]);
            }
        }
    }

    public function rules(): array
    {
        $categoria = $this->route('categoria'); // null cuando se está registrando

        return [
            'nombre' => ['required', 'string', 'max:100', function (string $atributo, mixed $valor, \Closure $fallar) use ($categoria) {
                // Único sin distinguir mayúsculas ni espacios sobrantes (PostgreSQL sí las distingue).
                $existe = \App\Models\Categoria::query()
                    ->whereRaw('lower(trim(nombre)) = ?', [mb_strtolower(trim((string) $valor))])
                    ->when($categoria, fn ($q) => $q->where('idcategoria', '<>', $categoria->idcategoria))
                    ->exists();

                if ($existe) {
                    $fallar('Ya existe una categoría con ese nombre.');
                }
            }],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'nombre.max' => 'El nombre de la categoría no puede pasar de 100 caracteres.',
            'descripcion.max' => 'La descripción no puede pasar de 500 caracteres.',
        ];
    }
}