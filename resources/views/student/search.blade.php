@extends('auth.outline')

@section('title', 'ARSIP TA FMIPA UNJ — Cari Tugas Akhir')
@section('body-class', 'auth-search')

@push('styles')
<link rel="stylesheet" href="{{ asset_version('css/student-datatable.css') }}">
<link rel="stylesheet" href="{{ asset_version('css/student-search.css') }}">
@endpush

@section('standalone')
<div class="page">
    <div class="container">
        <div class="portal-card">

            <div class="header">
                <div class="brand">
                    <img src="{{ asset('images/logo.png') }}" alt="Logo UNJ" class="logo" width="95" height="94">
                    <div>
                        <small>Universitas Negeri Jakarta</small>
                        <h1>Perpustakaan FMIPA</h1>
                    </div>
                </div>

                <div class="schedule">
                    <i class="fa-regular fa-clock"></i> Senin - Jumat • 08.00 - 16.00
                </div>
            </div>

            <div class="hero">
                <h2>Cari Tugas Akhir</h2>
                <p>
                    Semua arsip tugas akhir Perpustakaan FMIPA tampil di tabel. Gunakan kata kunci
                    untuk mempersempit hasil (NIM, nama, judul, program studi, jenjang, tahun, no. panggil, dan lainnya).
                </p>
            </div>

            <div class="search-toolbar">
                <form id="publicsearchform" class="search-form" role="search">
                    <label class="search-label" for="search-q">Kata kunci</label>
                    <div class="search-field">
                        <i class="fas fa-magnifying-glass search-field-icon" aria-hidden="true"></i>
                        <input
                            type="search"
                            id="search-q"
                            name="q"
                            class="search-input"
                            placeholder="NIM, nama, judul, program studi, tahun…"
                            autocomplete="off"
                        >
                    </div>
                    <div class="search-actions">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                            <span>Cari</span>
                        </button>
                        <button type="button" id="search-reset" class="search-reset" hidden>
                            <i class="fas fa-xmark" aria-hidden="true"></i>
                            <span>Reset</span>
                        </button>
                    </div>
                </form>

                <div class="search-toolbar-side">
                    <div class="search-length-slot" id="search-length-slot"></div>
                </div>
            </div>

            <div class="student-dt" id="publicsearchdt">
                <table id="publicsearchtable" class="student-dt-table search-table" data-index-url="{{ $searchIndexUrl }}">
                    <thead>
                        <tr>
                            <th scope="col" class="col-no no-sort">No</th>
                            <th scope="col" class="col-nim">NIM</th>
                            <th scope="col" class="col-name">Nama</th>
                            <th scope="col" class="col-title">Judul Tugas Akhir</th>
                            <th scope="col" class="col-study-program">Program Studi</th>
                            <th scope="col" class="col-degree">Jenjang</th>
                            <th scope="col" class="col-year">Tahun</th>
                            <th scope="col" class="col-callno">No. Panggil</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="search-footer">
                © {{ date('Y') }} Perpustakaan FMIPA Universitas Negeri Jakarta
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset_version('js/vendor/jquery-3.7.1.min.js') }}" defer></script>
<script src="{{ asset_version('js/vendor/jquery.dataTables.min.js') }}" defer></script>
<script src="{{ asset_version('js/format-count.js') }}" defer></script>
<script src="{{ asset_version('js/student-search.js') }}" defer></script>
@endpush
