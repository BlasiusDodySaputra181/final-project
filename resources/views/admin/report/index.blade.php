@extends('module.outline.apps')

@section('content')
<div class="table-card">

    <div class="table-header">

        <div class="action-row">

            <button class="btn btn-theme" id="buttonaddreport">
                <i class="fas fa-plus"></i>
                Tambah
            </button>

            <button class="btn btn-primary" id="buttondownloadreport">
                <i class="fas fa-download"></i>
                Unduh Laporan
            </button>

            <button class="btn btn-success" id="buttondownloadreportfinalprojectyear">
                <i class="fas fa-download"></i>
                Unduh Laporan Tahun TA
            </button>

            <button class="btn btn-danger" id="buttondeleteallreport">
                <i class="fas fa-trash-can"></i>
                Hapus Dipilih
            </button>

        </div>

    </div>

    <table id="reporttable"></table>

</div>
@endsection
