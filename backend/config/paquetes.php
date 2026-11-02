<?php

use App\Models\Rol;

/*
 * Los 6 paquetes del sistema y los casos de uso (CU) de cada uno, con los nombres del documento.
 * El panel (CU1) recorre esta lista y le muestra a cada rol solo los CU que puede usar.
 *
 * Cada CU lleva:
 *  - codigo / nombre / ciclo (I, II o III)
 *  - ruta:   nombre de la ruta a la que se entra (si la ruta no existe, el CU no se muestra)
 *  - roles:  quiénes lo ven en su panel
 *  - accion: 'cerrar_sesion' (botón en vez de enlace) o 'nota' (no tiene pantalla propia; solo muestra la nota). Si no se indica, es un enlace.
 *  - nota:   texto corto opcional bajo el nombre (por ejemplo, desde dónde se entra)
 *
 * Los CU de un ciclo nuevo se agregan aquí cuando se implementan.
 */

$todos = [Rol::ADMINISTRADOR, Rol::AGENTE, Rol::ASISTENTE];
$personal = [Rol::ADMINISTRADOR, Rol::AGENTE];

return [

    [
        'codigo' => 'P1',
        'nombre' => 'Autentificación y Seguridad',
        'casos' => [
            ['codigo' => 'CU1', 'nombre' => 'Iniciar sesión', 'ciclo' => 'I', 'ruta' => 'panel', 'roles' => $todos, 'accion' => 'nota', 'nota' => 'Ya iniciaste sesión'],
            ['codigo' => 'CU2', 'nombre' => 'Cerrar sesión', 'ciclo' => 'I', 'ruta' => 'logout', 'roles' => $todos, 'accion' => 'cerrar_sesion'],
            ['codigo' => 'CU3', 'nombre' => 'Recuperar contraseña', 'ciclo' => 'I', 'ruta' => 'password.request', 'roles' => $todos, 'accion' => 'nota', 'nota' => 'Se usa desde la pantalla de inicio de sesión'],
            ['codigo' => 'CU4', 'nombre' => 'Gestionar usuarios', 'ciclo' => 'I', 'ruta' => 'usuarios.index', 'roles' => [Rol::ADMINISTRADOR]],
        ],
    ],

    [
        'codigo' => 'P2',
        'nombre' => 'Gestión de Propiedades',
        'casos' => [
            ['codigo' => 'CU5', 'nombre' => 'Registrar propiedad', 'ciclo' => 'I', 'ruta' => 'propiedades.create', 'roles' => $personal],
            ['codigo' => 'CU6', 'nombre' => 'Modificar propiedad', 'ciclo' => 'I', 'ruta' => 'propiedades.index', 'roles' => $personal, 'nota' => 'Desde el inventario'],
            ['codigo' => 'CU7', 'nombre' => 'Eliminar propiedad', 'ciclo' => 'I', 'ruta' => 'propiedades.index', 'roles' => [Rol::ADMINISTRADOR], 'nota' => 'Desde el inventario'],
            ['codigo' => 'CU8', 'nombre' => 'Cambiar estado de propiedad', 'ciclo' => 'II', 'ruta' => 'propiedades.index', 'roles' => $personal, 'nota' => 'Desde el inventario'],
        ],
    ],

    [
        'codigo' => 'P3',
        'nombre' => 'Búsqueda y Consulta de Inmuebles',
        'casos' => [
            ['codigo' => 'CU9', 'nombre' => 'Buscar propiedades', 'ciclo' => 'II', 'ruta' => 'catalogo.index', 'roles' => $personal],
            ['codigo' => 'CU10', 'nombre' => 'Filtrar propiedades', 'ciclo' => 'II', 'ruta' => 'catalogo.index', 'roles' => $personal],
            ['codigo' => 'CU11', 'nombre' => 'Ver detalle de propiedad', 'ciclo' => 'II', 'ruta' => 'catalogo.index', 'roles' => $personal, 'nota' => 'Desde el catálogo'],
        ],
    ],

    [
        'codigo' => 'P4',
        'nombre' => 'CRM y Gestión de Clientes',
        'casos' => [
            ['codigo' => 'CU12', 'nombre' => 'Registrar cliente interesado', 'ciclo' => 'II', 'ruta' => 'prospectos.create', 'roles' => [Rol::AGENTE]],
            ['codigo' => 'CU13', 'nombre' => 'Gestionar prospectos CRM', 'ciclo' => 'II', 'ruta' => 'prospectos.index', 'roles' => $personal],
        ],
    ],

    [
        'codigo' => 'P5',
        'nombre' => 'Agenda, Visitas y Pagos',
        'casos' => [
            // CU14 a CU17 y CU26 a CU30 (ciclo III): cada prompt del ciclo agrega los suyos aquí.
            ['codigo' => 'CU14', 'nombre' => 'Agendar visita a inmueble', 'ciclo' => 'III', 'ruta' => 'propiedades.index', 'roles' => $personal, 'nota' => 'Desde el inventario o un prospecto. El cliente público, desde el catálogo'],
            ['codigo' => 'CU15', 'nombre' => 'Reagendar visita', 'ciclo' => 'III', 'ruta' => 'visitas.calendario', 'roles' => $personal, 'nota' => 'Desde el detalle de la visita'],
            ['codigo' => 'CU16', 'nombre' => 'Cancelar visita', 'ciclo' => 'III', 'ruta' => 'visitas.calendario', 'roles' => $personal, 'nota' => 'Desde el detalle de la visita'],
            ['codigo' => 'CU17', 'nombre' => 'Ver calendario de visitas', 'ciclo' => 'III', 'ruta' => 'visitas.calendario', 'roles' => $personal],
            ['codigo' => 'CU26', 'nombre' => 'Gestionar reservas de propiedad', 'ciclo' => 'III', 'ruta' => 'reservas.index', 'roles' => array_merge($personal, [Rol::ASISTENTE])],
            ['codigo' => 'CU27', 'nombre' => 'Gestionar contratos', 'ciclo' => 'III', 'ruta' => 'contratos.index', 'roles' => $personal],
        ],
    ],

    [
        'codigo' => 'P6',
        'nombre' => 'Reportes y Administración del Sistema',
        'casos' => [
            // Todavía no hay CU de reportes.
        ],
        // Funciones del administrador que no son un caso de uso del documento.
        'administracion' => [
            ['nombre' => 'Bitácora', 'descripcion' => 'Registro de la actividad de todo el sistema', 'ruta' => 'bitacora.index', 'roles' => [Rol::ADMINISTRADOR]],
        ],
    ],

];
