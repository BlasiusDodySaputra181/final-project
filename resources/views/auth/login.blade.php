@extends('auth.outline')

@section('title', 'ARSIP TA FMIPA UNJ — Masuk')

@section('auth-card')
    @php
        $roleSessions = $roleSessions ?? [];
        $allRolesActive = count($roleSessions) >= 3;
    @endphp

    <div class="login-header">
        <img src="{{ asset('images/logo-256.webp') }}" alt="Logo UNJ" width="120" height="118" decoding="async">
        <p class="login-kicker">Perpustakaan FMIPA UNJ</p>
        <h2>ARSIP TA</h2>
        <p>
            @if ($allRolesActive)
                Semua peran sudah masuk di peramban ini
            @elseif ($roleSessions)
                Masuk peran lain tanpa mengeluarkan sesi yang aktif
            @else
                Masuk ke sistem pengarsipan tugas akhir
            @endif
        </p>
    </div>

    @if (session('success'))
        <div class="login-alert login-alert--success" role="status">
            @include('auth.partials.icon', ['name' => 'check'])
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @php
        $authError = collect($errors->all())->first(
            fn ($message) => str_contains($message, 'tidak sesuai')
                || str_contains($message, 'Akses hanya')
                || str_contains($message, 'Pendaftaran publik')
                || str_contains($message, 'Sesi peran')
                || str_contains($message, 'beberapa akun')
        );
    @endphp

    @if ($authError)
        <div class="login-alert" role="alert">
            @include('auth.partials.icon', ['name' => 'warning'])
            <span>{{ $authError }}</span>
        </div>
    @endif

    @include('auth.partials.role-sessions')

    @if ($allRolesActive)
        <p class="login-sessions-full">
            Keluar salah satu peran di atas jika ingin mengganti akun peran tersebut.
        </p>
    @else
        <form id="loginform" method="POST" action="{{ route('login.submit') }}" novalidate>
            @csrf

            <div class="form-group">
                <label for="name">Nama / Email / NIM</label>
                <div class="input-icon">
                    @include('auth.partials.icon', ['name' => 'user'])
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-control @error('name') is-invalid @enderror"
                        value="{{ old('name') }}"
                        placeholder="Nama, email, atau NIM/NIP"
                        autocomplete="username"
                        required
                        autofocus>
                </div>
                @error('name')
                    @unless (str_contains($message, 'tidak sesuai') || str_contains($message, 'Akses hanya'))
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @endunless
                @enderror
            </div>

            <div class="form-group">
                <label for="password">Kata Sandi</label>
                <div class="input-icon">
                    @include('auth.partials.icon', ['name' => 'lock'])
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control @error('password') is-invalid @enderror"
                        placeholder="Kata Sandi"
                        autocomplete="current-password"
                        required>
                </div>
                @error('password')
                    <div class="field-error" role="alert">{{ $message }}</div>
                @enderror
            </div>

            <div class="options">
                <label class="remember">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                    Ingat saya
                </label>
            </div>

            <button type="submit" class="login-btn" id="loginbtn">
                <span>{{ $roleSessions ? 'Masuk peran lain' : 'Masuk' }}</span>
                @include('auth.partials.icon', ['name' => 'arrow-right', 'class' => 'login-icon'])
            </button>
        </form>

        @if ($roleSessions)
            <p class="login-sessions-hint">Akun peran yang sama akan diganti; peran lain tetap masuk.</p>
        @endif
    @endif

    @if (($canRegister ?? false) && ! $allRolesActive)
        <div class="auth-switch">
            Belum punya akun?
            <a href="{{ route('register') }}">Daftar Akun</a>
        </div>
    @endif

    <div class="auth-switch">
        Butuh akun mahasiswa?
        Hubungi Petugas untuk dibuatkan akses layanan.
    </div>

    <div class="footer">
        © {{ date('Y') }} Perpustakaan FMIPA Universitas Negeri Jakarta
    </div>
@endsection
