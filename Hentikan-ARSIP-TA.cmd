@echo off
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0laravel-assets\scripts\runtime.ps1" -Command stop
if errorlevel 1 (
    echo Peladen belum dapat dihentikan.
    pause
    exit /b 1
)
echo ARSIP-TA di komputer ini sudah dihentikan.
pause
