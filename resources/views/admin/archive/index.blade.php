@extends('module.outline.apps')

@section('content')
<div class="table-card">

    <div class="table-header">

        <div class="action-row">

            <button class="btn btn-theme" id="buttonaddarchive">
                <i class="fas fa-plus"></i>
                Tambah
            </button>

            <button class="btn btn-primary" id="buttondownloadarchive">
                <i class="fas fa-download"></i>
                Unduh Arsip
            </button>

            <button class="btn btn-success" id="buttondownloadarchivestudyprogram">
                <i class="fas fa-download"></i>
                Unduh Arsip Prodi
            </button>

            <button class="btn btn-danger" id="buttondeleteallarchive">
                <i class="fas fa-trash-can"></i>
                Hapus Dipilih
            </button>

        </div>

    </div>

    <table id="archivetable"></table>

</div>
@endsection
