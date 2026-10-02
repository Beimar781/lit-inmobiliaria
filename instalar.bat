@echo off
chcp 65001 >nul
echo ==========================================
echo   LIT Inmobiliaria - instalacion (una vez)
echo ==========================================
echo.

where php >nul 2>nul || (echo [ERROR] No se encontro PHP. Instala Laragon o XAMPP, abre una terminal NUEVA y vuelve a intentarlo. & pause & exit /b 1)
where composer >nul 2>nul || (echo [ERROR] No se encontro Composer. Instalalo desde https://getcomposer.org ^(Composer-Setup.exe^), abre una terminal NUEVA y vuelve a intentarlo. & pause & exit /b 1)

echo [1/5] Instalando librerias de PHP (necesita internet)...
call composer install --no-interaction || (echo [ERROR] Fallo composer install. Revisa tu internet y que PHP sea 8.3 o superior ^(php -v^). & pause & exit /b 1)

echo [2/5] Creando el archivo .env...
if not exist .env copy .env.example .env >nul

echo [3/5] Generando la clave de la aplicacion...
call php artisan key:generate --force || (echo [ERROR] Fallo key:generate. & pause & exit /b 1)

echo [4/5] Creando la base de datos (MySQL debe estar encendido)...
set "MYSQL="
where mysql >nul 2>nul && set "MYSQL=mysql"
if not defined MYSQL if exist "C:\xampp\mysql\bin\mysql.exe" set "MYSQL=C:\xampp\mysql\bin\mysql.exe"
if defined MYSQL ("%MYSQL%" -u root -e "CREATE DATABASE IF NOT EXISTS lit_inmobiliaria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || (echo [ERROR] No se pudo conectar a MySQL. Enciendelo ^(Laragon: Iniciar todo / XAMPP: Start en MySQL^). Si tu root tiene contrasena, sigue el README. & pause & exit /b 1))
if not defined MYSQL (echo [AVISO] No se encontro el comando mysql. Abre phpMyAdmin ^(http://localhost/phpmyadmin^), crea una base llamada lit_inmobiliaria con cotejamiento utf8mb4_unicode_ci y luego presiona una tecla. & pause)

echo [5/5] Creando las tablas y cargando los datos de ejemplo...
call php artisan migrate:fresh --seed --force || (echo [ERROR] Fallo migrate. Revisa que MySQL este encendido y la seccion DB_ del archivo .env. & pause & exit /b 1)

echo.
echo ==========================================
echo   Listo. Ahora ejecuta iniciar.bat
echo ==========================================
pause
