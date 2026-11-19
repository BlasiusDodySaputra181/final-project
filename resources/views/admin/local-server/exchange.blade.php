<div class="local-exchange" id="localdatabaseexchange" data-import-url="{{ route('import-local-database', absolute: false) }}">
    <h3>Pindah basis data</h3>
    <p class="storage-path-hint">
        Ekspor mengunduh berkas basis data yang sedang dipakai.
        Simpan berkas itu, lalu impor di komputer lain, setelah pemasangan ulang, atau di aplikasi ARSIP-TA yang lain
        agar data akun, tugas akhir, dan pengaturan yang sama tersedia di setiap pemasangan.
        Berkas yang terganti disimpan sebagai cadangan di folder basis data.
        Folder berkas <strong>ARSIP TUGAS AKHIR</strong> tidak ikut di dalam berkas ini.
        Salin folder itu secara terpisah, lalu pilih foldernya saat pemasangan atau di Pengaturan.
    </p>
    <div class="local-server-actions">
        <a class="btn btn-default" id="localdatabaseexport" href="{{ route('export-local-database') }}">
            <i class="fas fa-download"></i>
            Ekspor basis data
        </a>
        <button type="button" class="btn btn-theme" id="localdatabaseimport">
            <i class="fas fa-upload"></i>
            Impor basis data
        </button>
        <input type="file" id="localdatabasefile" accept=".sqlite,.sqlite3,.db,application/octet-stream" hidden>
    </div>
</div>
