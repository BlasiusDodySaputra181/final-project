@extends('auth.outline')

@section('title', 'ARSIP TA FMIPA UNJ — Akses Ditutup')
@section('body-class', 'auth-access')

@section('standalone')
<div class="access-card">

    <div class="access-topbar">
        <div class="access-badge">
            AKSES SISTEM DINONAKTIFKAN
        </div>
    </div>

    @php
        $errorText = session('error') ?? ($errorMessage ?? null);
    @endphp

    @if ($errorText)
        <div class="login-alert" role="alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ $errorText }}</span>
        </div>
    @endif

    <div class="access-icon-wrapper">
        <img src="{{ asset('svg/access-shield.svg') }}" alt="" width="68" height="68">
    </div>

    <h1 class="access-title">Akses Ditutup</h1>

    <p class="access-subtitle">
        Sistem layanan Perpustakaan FMIPA Universitas Negeri Jakarta
        sedang tidak tersedia sementara waktu. Silakan mengakses
        kembali sesuai jadwal operasional yang telah ditetapkan.
    </p>

    <div class="access-illustration">
        <div class="access-circle"></div>
        <div class="access-block-icon">
            <img src="{{ asset('svg/access-blocked.svg') }}" alt="" width="72" height="72">
        </div>
    </div>

    <div class="access-info">
        <div class="access-info-icon">
            <i class="fas fa-circle-info"></i>
        </div>
        <div>
            <h3>Informasi Operasional</h3>
            <p>
                Akses layanan akan dibuka kembali sesuai jadwal operasional
                perpustakaan. Untuk kebutuhan informasi lebih lanjut,
                silakan menghubungi petugas atau administrator sistem
                Perpustakaan FMIPA Universitas Negeri Jakarta.
            </p>
        </div>
    </div>

    <div class="auth-actions">
        <a
            href="https://fmipa.unj.ac.id/"
            class="login-btn login-btn--ghost"
            rel="noopener noreferrer"
        >
            <i class="fas fa-headset login-icon login-icon--left"></i>
            <span>Hubungi Petugas</span>
        </a>

        @auth('student')
            <form action="{{ route('logout') }}" method="POST" class="access-logout-form">
                @csrf
                <input type="hidden" name="guard" value="student">
                <button type="submit" class="login-btn">
                    <span>Keluar</span>
                    <i class="fas fa-right-from-bracket login-icon"></i>
                </button>
            </form>
        @else
            <button type="button" class="login-btn" id="accessexit">
                <span>Keluar</span>
                <i class="fas fa-arrow-right login-icon"></i>
            </button>
        @endauth
    </div>

    <div class="footer">
        © {{ date('Y') }} Perpustakaan FMIPA Universitas Negeri Jakarta
    </div>

</div>
@endsection
