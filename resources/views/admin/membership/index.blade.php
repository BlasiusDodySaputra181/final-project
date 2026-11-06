@extends('module.outline.apps')

@section('content')
<div class="table-card">

    <div class="table-header">

        <div class="action-row">

            <button class="btn btn-theme" id="buttonaddmembership">
                <i class="fas fa-plus"></i>
                Tambah
            </button>

            <button class="btn btn-danger" id="buttondeleteallmembership">
                <i class="fas fa-trash-can"></i>
                Hapus Dipilih
            </button>

        </div>

    </div>

    <table id="membershiptable"></table>

</div>
@endsection
