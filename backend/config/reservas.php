<?php

/*
 * Reglas del dinero de las reservas (CU26).
 *
 *  - retencion: porcentaje de la reserva que se queda la empresa cuando el cliente no formaliza la compra (la reserva
 *    vence o se cancela). El resto se le devuelve en efectivo. Compensa el tiempo que la propiedad estuvo bloqueada.
 *  - minimo: lo mínimo que se debe pagar para reservar, como porcentaje del precio de la propiedad (en dólares).
 */
return [
    'retencion' => (float) env('RESERVA_RETENCION', 20),
    'minimo' => (float) env('RESERVA_MINIMO', 1),
];
