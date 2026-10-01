# LIT Inmobiliaria — Sistema de gestión inmobiliaria

Proyecto semestral de Sistemas de Información 1. Laravel + MySQL + Blade. Desarrollo por ciclos (PUDS).

## Requisitos
PHP 8.2+, Composer 2, MySQL 8 (Laragon trae todo), Git.

## Instalación (para cada integrante)
```
git clone https://github.com/Beimar781/lit-inmobiliaria.git
cd lit-inmobiliaria
composer install
copy .env.example .env
php artisan key:generate
mysql -u root -e "CREATE DATABASE lit_inmobiliaria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed
php artisan serve
```
Abrir http://127.0.0.1:8000

## Usuarios de prueba (contraseña: `Lit2026*`)
| Rol | Correo |
|---|---|
| Administrador | jgarcia@inmobiliaria.com |
| Agente Inmobiliario | mtorrez@inmobiliaria.com |
| Asistente Administrativo | rjustiniano@inmobiliaria.com |
| Cliente | croca@gmail.com |

## Estructura
El código está organizado por paquete y caso de uso en `app/Modules` (ver `app/Modules/README.md`).
Los modelos están en `app/Models` y las tablas en `database/migrations`.

## Ciclo I
CU1 Iniciar sesión · CU2 Cerrar sesión · CU3 Recuperar contraseña · CU4 Gestionar usuarios · CU5 Registrar propiedad · CU6 Modificar propiedad · CU7 Eliminar propiedad

## Trabajo en equipo
Una rama por caso de uso: `git checkout -b cu5-registrar-propiedad`, luego commit, push y Pull Request hacia `main`.
