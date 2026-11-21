<?php

namespace App\Modules\ReportesAdministracion\CU20_GenerarReportes;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Parámetros del reporte (por querystring, así los mismos criterios sirven para generar y para exportar).
 * Los valores se leen de forma segura: uno mal formado (?tipo[]=x, ?desde=zzz) nunca da error 500.
 */
class ReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso por rol está en routes.php y el alcance por tipo en el controlador
    }

    public function rules(): array
    {
        return [];
    }

    public function hayTipo(): bool
    {
        $tipo = $this->query('tipo');

        return is_string($tipo) ? trim($tipo) !== '' : $tipo !== null;
    }

    /** ¿Los criterios vienen de la interpretación con IA? */
    public function origenIa(): bool
    {
        return $this->query('origen') === 'ia';
    }

    /** ¿Se está trabajando por voz? (el navegador lee en voz alta y escucha comandos) */
    public function voz(): bool
    {
        return $this->query('voz') === '1';
    }

    /** ¿El actor pidió el resumen redactado por IA al confirmar? */
    public function conResumen(): bool
    {
        return $this->query('con_resumen') === '1';
    }

    /** La frase que dio origen al reporte (máximo 200 caracteres para la bitácora). */
    public function solicitud(): string
    {
        $texto = $this->query('solicitud');

        return is_string($texto) ? mb_substr(trim($texto), 0, 200) : '';
    }

    /** @throws CriteriosNoValidos si el tipo, el estado o la agrupación no están en la lista */
    public function criterios(): CriteriosDeReporte
    {
        return CriteriosDeReporte::desdeArreglo($this->query());
    }
}