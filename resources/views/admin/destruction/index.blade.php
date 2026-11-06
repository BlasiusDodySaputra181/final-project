@extends('module.outline.apps')

@section('content')
<div class="table-card">

    <div class="table-header">

        <div class="action-row">

            <button class="btn btn-theme" id="buttonadddestruction">
                <i class="fas fa-plus"></i>
                Tambah
            </button>

            <button class="btn btn-danger" id="buttondeletealldestruction">
                <i class="fas fa-trash-can"></i>
                Hapus Dipilih
            </button>

        </div>

    </div>

    <table id="destructiontable"></table>

</div>
@endsection
