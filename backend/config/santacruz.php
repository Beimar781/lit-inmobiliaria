<?php

/*
 * Alcance geográfico del sistema: Santa Cruz de la Sierra, dentro del 4.º anillo.
 *
 * IMPORTANTE: el 4.º anillo no es un círculo perfecto. Aquí se aproxima con un círculo
 * alrededor de la Plaza 24 de Septiembre. En la pantalla de registrar propiedad el
 * círculo se dibuja sobre el mapa: compáralo con el anillo real y ajusta 'radio_km'
 * si hace falta.
 */
return [
    'ciudad' => 'Santa Cruz de la Sierra',

    // Plaza 24 de Septiembre (centro de la ciudad)
    'centro' => ['lat' => -17.7834, 'lng' => -63.1821],

    // Radio aproximado del 4.º anillo, en kilómetros (valor de partida a verificar en el mapa)
    'radio_km' => 4.5,
];
