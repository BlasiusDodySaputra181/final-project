@extends('module.outline.apps')

@section('content')
<div class="table-card"
     id="finalprojectcontext"
     data-type-id="{{ $finalProjectType->id }}"
     data-level="{{ $finalProjectType->level }}"
     data-type="{{ $finalProjectType->type }}">

    <div class="table-header">

        <div class="action-row">

            <button class="btn btn-theme" id="buttonaddfinalproject">
                <i class="fas fa-plus"></i>
                Tambah
            </button>

            <button class="btn btn-danger" id="buttondeleteallfinalproject">
                <i class="fas fa-trash-can"></i>
                Hapus Dipilih
            </button>

        </div>

    </div>

    <table id="finalprojecttable"></table>

</div>
@endsection
