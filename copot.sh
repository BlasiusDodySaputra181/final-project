#!/bin/sh
# Permanently remove ARSIP-TA on Linux, macOS, BSD, and other Unix systems.
set -eu
ROOT=$(CDPATH= cd -- "$(dirname "$0")" && pwd)
echo 'Penghapusan ini permanen.'
echo 'Isi berkas aplikasi ditimpa, lalu folder ini dan salinan ARSIP-TA yang dikenali dihapus.'
echo 'Yang ikut terhapus: basis data di dalam folder, catatan, pintasan, dan sisa berkas pemasangan.'
echo 'Perintah ini berlaku di Linux, macOS, BSD, Solaris, dan Unix lain.'
echo 'Di desktop, klik kanan pintasan ARSIP-TA lalu pilih Copot.'
echo 'Folder berkas yang berada di luar folder aplikasi tidak dihapus.'
echo
printf 'Ketik HAPUS untuk melanjutkan: '
read -r confirm || confirm=''
if [ "$confirm" != 'HAPUS' ]; then
    echo 'Penghapusan dibatalkan.'
    exit 0
fi
sh "$ROOT/laravel-assets/scripts/runtime.sh" uninstall
echo 'Penghapusan berlanjut di latar belakang.'
