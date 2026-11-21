<?php

namespace App\Modules\ReportesAdministracion\CU20_GenerarReportes;

/**
 * Servicio de inteligencia artificial del CU20, detrás de una interfaz para poder cambiar de proveedor.
 * REGLA DE ORO: la IA nunca consulta la base de datos ni recibe datos personales. Para interpretar recibe solo la frase
 * del usuario y para resumir solo cifras agregadas.
 */
interface ServicioDeIa
{
    /** ¿Está configurado (hay llave)? Si no, el sistema sigue con los filtros manuales. */
    public function disponible(): bool;

    /**
     * Convierte la frase en criterios (arreglo con tipo, periodo, filtros y agrupacion). Devuelve null si el servicio
     * no responde, da error o contesta algo que no es JSON.
     *
     * @param string[] $tiposPermitidos tipos de reporte que el rol del usuario puede pedir
     */
    public function interpretar(string $solicitud, array $tiposPermitidos): ?array;

    /** Redacta un resumen breve a partir de cifras agregadas. Devuelve null si no se pudo. */
    public function resumir(array $datosAgregados): ?string;
}