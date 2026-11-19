: << 'BATCH'
@echo off
cd /d "%~dp0"
echo Memasang ARSIP-TA ke perangkat ini. Pilih folder aplikasi, basis data, dan folder berkas.
powershell -NoProfile -STA -ExecutionPolicy Bypass -File "%~dp0laravel-assets\scripts\install-windows.ps1"
if errorlevel 1 (
    echo.
    echo Pemasangan belum selesai. Baca pesan di atas, lalu jalankan berkas ini lagi.
    pause
    exit /b 1
)
echo.
pause
exit /b 0
BATCH
#!/bin/sh
# Pasang ARSIP-TA ke perangkat ini.
set -eu

SOURCE=$(CDPATH= cd -- "$(dirname "$0")" && pwd)

echo 'Memasang ARSIP-TA ke perangkat ini.'
home_dest="${HOME:-/tmp}/.local/share/ARSIP-TA"
opt_dest='/opt/ARSIP-TA'
if [ -t 0 ]; then
    echo "1. Folder ini: $SOURCE"
    echo "2. $home_dest"
    echo "3. $opt_dest"
    echo '4. Folder lain'
    printf 'Pilihan [1]: '
    read -r choice || choice=1
    case "${choice:-1}" in
        2) DEST=$home_dest ;;
        3) DEST=$opt_dest ;;
        4)
            printf 'Lokasi folder: '
            read -r DEST || DEST=''
            ;;
        *) DEST=$SOURCE ;;
    esac
else
    DEST=$home_dest
fi

if [ -z "$DEST" ]; then
    echo 'Pemasangan dibatalkan.'
    exit 0
fi
case "$DEST" in
    /|/usr|/bin|/etc|/var|/home|/Users|"${HOME:-}")
        echo 'Pilih folder aplikasi, bukan folder sistem.'
        exit 1
        ;;
esac
case "$DEST" in
    "$SOURCE"/*|"$SOURCE")
        if [ "$DEST" != "$SOURCE" ]; then
            echo 'Folder pemasangan tidak boleh berada di dalam paket yang sedang dibuka.'
            exit 1
        fi
        ;;
esac

if [ "$DEST" != "$SOURCE" ]; then
    mkdir -p "$DEST"
    work=${TMPDIR:-/tmp}/arsip-ta-pasang-$$
    mkdir -p "$work"
    if [ -f "$DEST/.env" ]; then
        cp "$DEST/.env" "$work/env"
    fi
    if [ -f "$DEST/database/database.sqlite" ]; then
        cp "$DEST/database/database.sqlite" "$work/database.sqlite"
    fi
    tar -C "$SOURCE" \
        --exclude .git \
        --exclude node_modules \
        --exclude laravel-assets/runtime \
        -cf - . | tar -C "$DEST" -xf -
    if [ -f "$work/env" ]; then
        cp "$work/env" "$DEST/.env"
    fi
    if [ -f "$work/database.sqlite" ]; then
        mkdir -p "$DEST/database"
        cp "$work/database.sqlite" "$DEST/database/database.sqlite"
    fi
    rm -rf "$work"
    echo "Aplikasi disalin ke $DEST"
fi

sh "$DEST/laravel-assets/scripts/runtime.sh" install
echo 'Buka aplikasi dari pintasan ARSIP-TA.'
echo 'Untuk menghapus, klik kanan pintasan ARSIP-TA lalu pilih Copot.'
echo 'Di macOS, menu itu ada di Services. Atau jalankan sh copot.sh.'
