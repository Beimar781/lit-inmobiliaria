<?php

namespace App\Modules\ReportesAdministracion\CU31_ConsultarHistorialPropiedad;

use App\Models\Historial;
use App\Support\ParametrosConsulta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Filtros de la consulta del historial (tipo de cambio y rango de fechas), leídos de la URL.
 * Un valor mal formado (?tipo[]=x, ?desde=zzz) se ignora en lugar de dar error 500.
 */
class HistorialPropiedadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso por rol está en routes.php y la propiedad se revisa en el controlador
    }

    public function rules(): array
    {
        return [];
    }

    public function tipo(): ?string
    {
        return ParametrosConsulta::opcion($this, 'tipo', array_keys(self::TIPOS));
    }

    public function desde(): ?Carbon
    {
        return $this->fecha('desde');
    }

    public function hasta(): ?Carbon
    {
        return $this->fecha('hasta');
    }

    /** Si las dos fechas vienen y "desde" es posterior a "hasta", no se consulta. */
    public function rangoValido(): bool
    {
        $desde = $this->desde();
        $hasta = $this->hasta();

        return ! ($desde && $hasta && $desde->gt($hasta));
    }

    public const TIPOS = [
        Historial::REGISTRO => 'Registro',
        Historial::MODIFICACION => 'Modificación',
        Historial::CAMBIO_ESTADO => 'Cambio de estado',
        Historial::BAJA => 'Baja',
    ];

    private function fecha(string $nombre): ?Carbon
    {
        $texto = ParametrosConsulta::texto($this, $nombre);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto)) {
            return null;
        }

        try {
            $fecha = Carbon::createFromFormat('!Y-m-d', $texto);
        } catch (\Throwable) {
            return null;
        }

        // createFromFormat acepta 2026-02-31 como 3 de marzo; se exige que la fecha vuelva igual.
        return $fecha && $fecha->format('Y-m-d') === $texto ? $fecha : null;
    }
}