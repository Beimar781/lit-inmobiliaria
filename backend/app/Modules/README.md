# Módulos del sistema

Cada carpeta es un paquete del documento y cada subcarpeta `CUx_...` es un caso de uso.
Aquí (backend) van el controlador y las validaciones (Request) de cada CU.
Las pantallas (`.blade.php`) del mismo CU están en la carpeta hermana `frontend/<Paquete>/<CU>/`.

| Paquete | Contenido |
|---|---|
| `AutentificacionSeguridad/` | P1: CU1 Iniciar sesión · CU2 Cerrar sesión · CU3 Recuperar contraseña · CU4 Gestionar usuarios |
| `GestionPropiedades/` | P2: CU5 Registrar · CU6 Modificar · CU7 Eliminar · CU8 Cambiar estado de propiedad (y `Compartido/` con lo que usan los cuatro) |
| `BusquedaConsulta/` | P3: CU9 Buscar · CU10 Filtrar · CU11 Ver detalle de propiedad (catálogo público; `Compartido/` tiene la búsqueda que también usa el inventario) |
| `CrmGestionClientes/` | P4: CU12 Registrar cliente interesado · CU13 Gestionar prospectos CRM |
| `AgendaVisitas/` | P5: Agenda y gestión de visitas (ciclo posterior) |
| `ReportesAdministracion/` | P6: Reportes y administración del sistema (hoy: bitácora del administrador, que no es un caso de uso) |

El archivo `routes.php` de cada paquete y sus vistas se cargan solos (ver `app/Providers/AppServiceProvider.php`).
Para crear un paquete o CU nuevo basta con agregar su carpeta aquí con su `routes.php` y la carpeta del mismo nombre en `frontend/`; no hay que registrar nada más.
