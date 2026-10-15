# Módulos del sistema

Cada carpeta es un paquete del documento y cada subcarpeta `CUx_...` es un caso de uso.
Aquí (backend) van el controlador y las validaciones (Request) de cada CU.
Las pantallas (`.blade.php`) del mismo CU están en la carpeta hermana `frontend/<Paquete>/<CU>/`.

| Paquete | Contenido |
|---|---|
| `Autenticacion/` | CU1 Iniciar sesión · CU2 Cerrar sesión · CU3 Recuperar contraseña |
| `Usuarios/` | CU4 Gestionar usuarios |
| `Propiedades/` | CU5 Registrar · CU6 Modificar · CU7 Eliminar propiedad (y `Compartido/` con lo que usan los tres) |
| `Reportes/` | Bitácora del administrador (no es un caso de uso) |

El archivo `routes.php` de cada paquete y sus vistas se cargan solos (ver `app/Providers/AppServiceProvider.php`).
Para crear un paquete o CU nuevo basta con agregar su carpeta aquí con su `routes.php` y la carpeta del mismo nombre en `frontend/`; no hay que registrar nada más.
