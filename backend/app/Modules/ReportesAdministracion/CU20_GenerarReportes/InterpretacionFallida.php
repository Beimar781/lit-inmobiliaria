<?php

namespace App\Modules\ReportesAdministracion\CU20_GenerarReportes;

/** La solicitud no se pudo convertir en criterios válidos; el mensaje es el que se le muestra al usuario. */
class InterpretacionFallida extends \RuntimeException
{
    public const SERVICIO = 'El servicio de inteligencia artificial no respondió. Continúa con los filtros manuales.';
    public const NO_RECONOCIDO = 'No reconocí un reporte en tu solicitud. Reformúlala o usa los filtros manuales.';
}