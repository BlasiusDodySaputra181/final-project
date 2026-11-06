@extends('module.outline.apps')

@section('content')
<div class="table-card">

    <div class="table-header">

        <div class="action-row">

            <button class="btn btn-theme" id="buttonaddimport" type="button">
                <i class="fas fa-plus"></i>
                Impor
            </button>

            <button class="btn btn-success" id="buttonsaveimport" type="button">
                <i class="fas fa-floppy-disk"></i>
                Simpan Impor
            </button>

            <button class="btn btn-danger" id="buttondeleteallimport" type="button">
                <i class="fas fa-trash-can"></i>
                Hapus Dipilih
            </button>

        </div>

    </div>

    <div class="info-box" id="ai-import-online-note">
        <i class="fas fa-circle-info"></i>
        Analisis sampul dengan AI (Groq) membutuhkan koneksi daring. Simpan impor hasil analisis tetap mendukung antrean luring.
    </div>

    <div class="info-box info-box--warning" id="ai-import-offline-note" hidden>
        <i class="fas fa-wifi"></i>
        Mode luring aktif — tombol Impor AI dinonaktifkan. Sambungkan jaringan untuk membaca data berkas lewat perintah kecerdasan buatan.
    </div>

    <table id="importtable"></table>

</div>
@endsection
