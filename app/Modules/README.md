# Módulos del sistema

Cada carpeta es un paquete del documento y cada subcarpeta `CUx_...` es un caso de uso.
Dentro de cada CU van su controlador, sus validaciones (Request) y sus vistas (`.blade.php`).

| Paquete | Casos de uso |
|---|---|
| `Autenticacion/` | CU1 Iniciar sesión · CU2 Cerrar sesión · CU3 Recuperar contraseña |
| `Usuarios/` | CU4 Gestionar usuarios |
| `Propiedades/` | CU5 Registrar · CU6 Modificar · CU7 Eliminar propiedad |

El archivo `routes.php` de cada paquete y sus vistas se cargan solos (ver `app/Providers/AppServiceProvider.php`).
