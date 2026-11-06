@extends('auth.outline')

@section('title', 'ARSIP TA FMIPA UNJ — Metadata Tugas Akhir')
@section('body-class', 'auth-metadata')

@push('styles')
<link rel="stylesheet" href="{{ asset_version('css/student-barcode.css') }}">
@endpush

@section('standalone')
<div class="meta-card">

    @include('student.partials.metadata-header')

    @if (session('success'))
        <div class="scan-alert scan-alert--success" role="status">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="scan-alert scan-alert--error" role="alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @include('student.partials.metadata-hero')

    @include('student.partials.metadata-student')

    @include('student.partials.metadata-project')

    <div class="meta-actions">
        <a href="{{ route('page-public-barcode') }}" class="scan-btn scan-btn--secondary">
            <i class="fas fa-camera"></i> Pindai
        </a>

        <button type="button" class="scan-btn scan-btn--primary scan-btn--next" id="accessexit">
            <span>Keluar</span>
            <i class="fas fa-arrow-right"></i>
        </button>
    </div>

    <div class="meta-footer">
        © {{ date('Y') }} Perpustakaan FMIPA Universitas Negeri Jakarta
    </div>

</div>

@include('student.partials.proof-letter-modal')
@endsection

@push('scripts')
<script src="{{ asset_version('js/student-barcode.js') }}" defer></script>
@endpush
