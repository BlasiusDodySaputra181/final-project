@echo off
set "ROOT=%~dp0"
cd /d "%SystemRoot%"
echo Penghapusan ini permanen.
echo Isi berkas aplikasi ditimpa, lalu folder ini dan salinan ARSIP-TA yang dikenali dihapus.
echo Yang ikut terhapus: basis data di dalam folder, registri, catatan, pintasan, kode QR, dan sisa berkas pemasangan.
echo Cara lain: klik kanan pintasan ARSIP-TA, lalu pilih Copot.
echo Folder berkas yang berada di luar folder aplikasi tidak dihapus.
echo.
set /p confirm=Ketik HAPUS untuk melanjutkan: 
if /I not "%confirm%"=="HAPUS" (
    echo Penghapusan dibatalkan.
    pause
    exit /b 0
)
powershell -NoProfile -STA -ExecutionPolicy Bypass -File "%ROOT%laravel-assets\scripts\runtime.ps1" -Command uninstall
if errorlevel 1 (
    echo.
    echo Penghapusan belum selesai. Baca pesan di atas, lalu jalankan berkas ini lagi.
    pause
    exit /b 1
)
echo.
echo Penghapusan berlanjut di latar belakang. Pesan muncul setelah folder selesai dihapus.
pause
