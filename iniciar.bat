@echo off
chcp 65001 >nul
echo Iniciando LIT Inmobiliaria en http://127.0.0.1:8000
echo (MySQL debe estar encendido en Laragon. Para apagar el sistema cierra esta ventana o presiona Ctrl + C)
echo.
start "" cmd /c "timeout /t 3 >nul & start http://127.0.0.1:8000"
php artisan serve
pause
