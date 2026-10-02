# Módulos del sistema

Cada carpeta es un paquete del documento y cada subcarpeta `CUx_...` es un caso de uso.
Dentro de cada CU van su controlador, sus validaciones (Request) y sus vistas (`.blade.php`).

| Paquete | Contenido |
|---|---|
| `Autenticacion/` | CU1 Iniciar sesión · CU2 Cerrar sesión · CU3 Recuperar contraseña |
| `Usuarios/` | CU4 Gestionar usuarios |
| `Propiedades/` | CU5 Registrar · CU6 Modificar · CU7 Eliminar propiedad (y `Compartido/` con lo que usan los tres) |
| `Reportes/` | Bitácora del administrador (no es un caso de uso) |

El archivo `routes.php` de cada paquete y sus vistas se cargan solos (ver `app/Providers/AppServiceProvider.php`).
Para crear un paquete o CU nuevo basta con agregar su carpeta y su `routes.php`; no hay que registrar nada más.
