<?php

use App\Models\Rol;

/*
 * Tarjetas del panel de inicio (CU1). Cada tarjeta es un módulo del sistema y pertenece a uno de los 6 paquetes del documento.
 * El panel recorre esta lista y le muestra a cada rol solo las tarjetas que puede usar.
 *
 * Cada tarjeta lleva:
 *  - paquete:     código del paquete del documento al que pertenece (P1 a P6)
 *  - titulo:      nombre que se ve en la tarjeta
 *  - descripcion: texto corto bajo el nombre
 *  - ruta:        nombre de la ruta a la que se entra (si la ruta no existe, la tarjeta no se muestra)
 *  - roles:       quiénes la ven en su panel
 *
 * El número de cada caso de uso (CU) se ve en el título de su pantalla, no en el panel.
 */

$personal = [Rol::ADMINISTRADOR, Rol::AGENTE];

return [

    // Los 6 paquetes del documento (3.4)
    'paquetes' => [
        'P1' => 'Autentificación y Seguridad',
        'P2' => 'Gestión de Propiedades',
        'P3' => 'Búsqueda y Consulta de Inmuebles',
        'P4' => 'CRM y Gestión de Clientes',
        'P5' => 'Agenda, Visitas y Pagos',
        'P6' => 'Reportes y Administración del Sistema',
    ],

    'tarjetas' => [
        ['paquete' => 'P1', 'titulo' => 'Usuarios', 'descripcion' => 'Gestionar las cuentas de los usuarios del sistema', 'ruta' => 'usuarios.index', 'roles' => [Rol::ADMINISTRADOR]],
        ['paquete' => 'P2', 'titulo' => 'Propiedades', 'descripcion' => 'Registrar, modificar, cambiar el estado y dar de baja propiedades', 'ruta' => 'propiedades.index', 'roles' => $personal],
        ['paquete' => 'P4', 'titulo' => 'Prospectos CRM', 'descripcion' => 'Clientes interesados y su seguimiento', 'ruta' => 'prospectos.index', 'roles' => $personal],
        ['paquete' => 'P3', 'titulo' => 'Catálogo', 'descripcion' => 'Buscar y ver las propiedades disponibles como las ve el público', 'ruta' => 'catalogo.index', 'roles' => $personal],
        ['paquete' => 'P5', 'titulo' => 'Calendario de visitas', 'descripcion' => 'Ver, reagendar y cancelar las visitas a los inmuebles', 'ruta' => 'visitas.calendario', 'roles' => $personal],
        ['paquete' => 'P5', 'titulo' => 'Reservas', 'descripcion' => 'Reservar propiedades, cobrar y devolver el dinero de la reserva', 'ruta' => 'reservas.index', 'roles' => [Rol::ADMINISTRADOR, Rol::AGENTE, Rol::ASISTENTE]],
        ['paquete' => 'P5', 'titulo' => 'Contratos', 'descripcion' => 'Registrar contratos de venta, alquiler y anticrético', 'ruta' => 'contratos.index', 'roles' => $personal],
        ['paquete' => 'P5', 'titulo' => 'Registrar pago', 'descripcion' => 'Cobrar un pago de un contrato en efectivo o con tarjeta', 'ruta' => 'pagos.create', 'roles' => [Rol::ADMINISTRADOR, Rol::ASISTENTE]],
        ['paquete' => 'P5', 'titulo' => 'Historial de pagos', 'descripcion' => 'Consultar los pagos registrados y sus comprobantes', 'ruta' => 'pagos.index', 'roles' => [Rol::ADMINISTRADOR, Rol::ASISTENTE]],
        ['paquete' => 'P5', 'titulo' => 'Mis contratos y pagos', 'descripcion' => 'Tus contratos, lo que has pagado, tus comprobantes y el pago con tarjeta', 'ruta' => 'portal.contratos', 'roles' => [Rol::CLIENTE]],
        ['paquete' => 'P6', 'titulo' => 'Bitácora', 'descripcion' => 'Registro de la actividad de todo el sistema', 'ruta' => 'bitacora.index', 'roles' => [Rol::ADMINISTRADOR]],
    ],

];
