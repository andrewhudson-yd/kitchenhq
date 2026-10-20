@echo off
cd /d "%~dp0"
echo Kitchen HQ will open at http://127.0.0.1:8173
echo Start MySQL in the XAMPP Control Panel first.
php artisan serve --host=127.0.0.1 --port=8173
pause
