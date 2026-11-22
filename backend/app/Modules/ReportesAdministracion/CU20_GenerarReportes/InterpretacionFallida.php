<?php

namespace App\Modules\ReportesAdministracion\CU20_GenerarReportes;

/**
 * La solicitud no se pudo convertir en criterios válidos para este usuario; el mensaje es el que se le muestra.
 * Lleva además lo que sí se pudo interpretar (para mostrarlo y cargarlo en el formulario y que lo corrija).
 */
class InterpretacionFallida extends \RuntimeException
{
    public const SERVICIO = 'El servicio de inteligencia artificial no respondió. Continúa con los filtros manuales.';
    public const NO_RECONOCIDO = 'No reconocí un reporte en tu solicitud. Reformúlala o usa los filtros manuales.';
    public const SIN_PERMISO = 'Este reporte no está permitido para tu rol.';
    public const RANGO = 'El rango de fechas no es válido.';
    public const PERIODO = 'No pude entender el período de tiempo de tu solicitud. Elige las fechas en el formulario y confirma.';

    /**
     * @param string[] $entendido  líneas legibles de lo que sí se interpretó (ej.: «Tipo: Pagos»)
     * @param array    $parametros criterios aprovechables para precargar el formulario manual
     * @param bool     $mostrarPanel false si el servicio de IA falló (ahí solo se avisa y se sigue con filtros manuales)
     */
    public function __construct(string $mensaje, public readonly array $entendido = [], public readonly array $parametros = [], public readonly bool $mostrarPanel = true)
    {
        parent::__construct($mensaje);
    }
}