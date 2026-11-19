@extends('module.outline.apps')

@section('content')
<div class="table-card">

    <div class="table-header">

        <div class="action-row">

            <button class="btn btn-theme" id="buttonaddvisitor">
                <i class="fas fa-plus"></i>
                Tambah
            </button>

            <button class="btn btn-primary" id="buttondownloadvisitor">
                <i class="fas fa-download"></i>
                Unduh Pengunjung
            </button>

            <button class="btn btn-danger" id="buttondeleteallvisitor">
                <i class="fas fa-trash-can"></i>
                Hapus Dipilih
            </button>

        </div>

    </div>

    <table id="visitortable"></table>

</div>
@endsection
