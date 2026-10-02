@echo off
chcp 65001 >nul
echo ==========================================
echo   LIT Inmobiliaria - instalacion (una vez)
echo ==========================================
echo.

where php >nul 2>nul || (echo [ERROR] No se encontro PHP. Instala Laragon, abre una terminal NUEVA y vuelve a intentarlo. & pause & exit /b 1)
where composer >nul 2>nul || (echo [ERROR] No se encontro Composer. Instala Laragon Full y abre una terminal NUEVA. & pause & exit /b 1)
where mysql >nul 2>nul || (echo [ERROR] No se encontro MySQL. Instala Laragon y agregalo al Path. & pause & exit /b 1)

echo [1/5] Instalando librerias de PHP (necesita internet)...
call composer install --no-interaction || (echo [ERROR] Fallo composer install. & pause & exit /b 1)

echo [2/5] Creando el archivo .env...
if not exist .env copy .env.example .env >nul

echo [3/5] Generando la clave de la aplicacion...
call php artisan key:generate --force || (echo [ERROR] Fallo key:generate. & pause & exit /b 1)

echo [4/5] Creando la base de datos (MySQL debe estar encendido)...
mysql -u root -e "CREATE DATABASE IF NOT EXISTS lit_inmobiliaria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || (echo [ERROR] No se pudo conectar a MySQL. Abre Laragon y pulsa "Iniciar todo". Si tu root tiene contrasena, sigue el README. & pause & exit /b 1)

echo [5/5] Creando las tablas y cargando los datos de ejemplo...
call php artisan migrate:fresh --seed --force || (echo [ERROR] Fallo migrate. Revisa el archivo .env (seccion DB_). & pause & exit /b 1)

echo.
echo ==========================================
echo   Listo. Ahora ejecuta iniciar.bat
echo ==========================================
pause
