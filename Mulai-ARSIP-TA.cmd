@echo off
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0laravel-assets\scripts\runtime.ps1" -Command start
if errorlevel 1 (
    echo Peladen belum dapat dijalankan.
    pause
    exit /b 1
)
