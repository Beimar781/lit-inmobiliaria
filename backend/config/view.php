<?php

/*
 * Las vistas (Blade) viven en la carpeta frontend/, hermana de backend/.
 *   frontend/layouts, frontend/components, frontend/errors  → diseño general
 *   frontend/<Paquete>/<CU>/                                → pantallas de cada caso de uso
 * (Las vistas de cada paquete se registran en App\Providers\AppServiceProvider.)
 */
return [

    'paths' => [
        dirname(base_path()) . DIRECTORY_SEPARATOR . 'frontend',
    ],

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        realpath(storage_path('framework/views')) ?: storage_path('framework/views')
    ),

];
