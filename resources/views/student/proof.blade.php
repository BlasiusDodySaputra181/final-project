@extends('auth.outline')

@section('title', 'ARSIP TA FMIPA UNJ — Bukti Penyerahan')
@section('body-class', 'auth-proof')

@push('styles')
<link rel="stylesheet" href="{{ asset_version('css/student-datatable.css') }}">
<link rel="stylesheet" href="{{ asset_version('css/student-proof.css') }}">
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

            @if (! empty($accessBlocked) && filled($accessBadge ?? null))
                <div class="proof-access-badge" role="alert">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <span>{{ $accessBadge }}</span>
                </div>
            @endif

            <div class="hero">
                <h2>Bukti Penyerahan dan Keanggotaan</h2>
                <p>
                    Daftar tugas akhir yang telah Anda serahkan ke Perpustakaan FMIPA
                    Universitas Negeri Jakarta, beserta data keanggotaan, bebas pustaka, dan hibah buku terkait.
                </p>
            </div>

            @if (session('error'))
                <div class="proof-alert proof-alert--error" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if (session('success'))
                <div class="proof-alert proof-alert--success" role="status">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @include('student.partials.proof-membership')

            @if (! empty($accessBlocked))
                <div class="proof-empty">
                    Belum ada data penyerahan tugas akhir.
                    @if ($membership ?? null)
                        <a href="{{ route('page-public-submission') }}" class="proof-empty-link">Serahkan tugas akhir terlebih dahulu</a>.
                    @else
                        <a href="{{ route('page-public-membership') }}" class="proof-empty-link">Daftarkan keanggotaan terlebih dahulu</a>.
                    @endif
                </div>
            @else
                <div class="student-dt" id="publicproofdt">
                    <table
                        id="publicprooftable"
                        class="student-dt-table proof-table"
                        data-index-url="{{ $proofIndexUrl }}"
                        data-detail-prefix="{{ $proofDetailUrlTemplate }}"
                    >
                        <thead>
                            <tr>
                                <th scope="col" class="col-no no-sort">No</th>
                                <th scope="col" class="col-nim">NIM</th>
                                <th scope="col" class="col-name">Nama</th>
                                <th scope="col" class="col-title">Judul Tugas Akhir</th>
                                <th scope="col" class="col-study-program">Program Studi</th>
                                <th scope="col" class="col-degree">Jenjang</th>
                                <th scope="col" class="col-year">Tahun</th>
                                <th scope="col" class="col-date">Tanggal Serah</th>
                                <th scope="col" class="no-sort">Detail</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            @endif

            <div class="proof-footer">
                © {{ date('Y') }} Perpustakaan FMIPA Universitas Negeri Jakarta
            </div>

        </div>
    </div>
</div>

@include('student.partials.proof-letter-modal')
@endsection

@push('scripts')
@unless (! empty($accessBlocked))
<script src="{{ asset_version('js/vendor/jquery-3.7.1.min.js') }}" defer></script>
<script src="{{ asset_version('js/vendor/jquery.dataTables.min.js') }}" defer></script>
<script src="{{ asset_version('js/format-count.js') }}" defer></script>
@endunless
<script src="{{ asset_version('js/student-proof.js') }}" defer></script>
@endpush
