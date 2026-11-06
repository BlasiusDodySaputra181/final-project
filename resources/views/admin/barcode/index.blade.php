@extends('module.outline.apps')

@section('content')
<div class="table-card">

    <div class="table-header">

        <div class="action-row">

            <button class="btn btn-theme" id="buttonaddbarcode">
                <i class="fas fa-plus"></i>
                Tambah
            </button>

            <button class="btn btn-primary" id="buttondownloadbarcode">
                <i class="fas fa-download"></i>
                Unduh Kode Batang
            </button>

            <button class="btn btn-success" id="buttonscandevicebarcode">
                <i class="fas fa-barcode"></i>
                Pindai Alat
            </button>

            <button class="btn btn-warning" id="buttonscancamerabarcode">
                <i class="fas fa-camera"></i>
                Pindai Kamera
            </button>

            <button class="btn btn-primary" id="buttonscanimagebarcode">
                <i class="fas fa-image"></i>
                Pindai Gambar
            </button>

            <button class="btn btn-danger" id="buttondeleteallbarcode">
                <i class="fas fa-trash-can"></i>
                Hapus Dipilih
            </button>

        </div>

        <div class="barcode-scan-result" id="barcodescanresult" hidden>
            <span id="barcodescanresulttext"></span>
            <button type="button" class="btn btn-default" id="buttonresetbarcodescan">
                <i class="fas fa-rotate-left"></i>
                Tampilkan Semua
            </button>
        </div>

    </div>

    <table id="barcodetable"></table>

</div>
@endsection
