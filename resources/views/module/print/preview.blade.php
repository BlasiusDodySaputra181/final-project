<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Menyiapkan dokumen…</title>
<link rel="stylesheet" href="{{ asset_version('css/print-preview.css') }}">
</head>
<body>
    <div class="print-toolbar">
        <p>Pratinjau cetak • Kertas A4 • Landscape</p>
        <button type="button" id="printpreview">Cetak</button>
    </div>
    <div class="sheet">
        <header class="kop">
            <img src="{{ asset('images/logo.png') }}" alt="Logo UNJ">
            <div class="kop-text">
                <h1>Universitas Negeri Jakarta</h1>
                <h2>Fakultas Matematika dan Ilmu Pengetahuan Alam</h2>
                <p>Sistem Arsip Tugas Akhir (ARSIP TA)</p>
            </div>
        </header>
        <h3 class="doc-title" id="printdoctitle">Menyiapkan dokumen…</h3>
        <p class="doc-meta" id="printdocmeta">Mohon tunggu.</p>
        <div id="printbody">
            <p class="empty">Menyiapkan dokumen cetak…</p>
        </div>
    </div>
<script src="{{ asset_version('js/vendor/JsBarcode.all.min.js') }}"></script>
<script src="{{ asset_version('js/print-preview.js') }}"></script>
</body>
</html>
