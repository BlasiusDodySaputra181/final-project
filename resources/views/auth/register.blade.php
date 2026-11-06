@extends('auth.outline')

@section('title', 'ARSIP TA FMIPA UNJ — Daftar')

@section('auth-card')
    <div class="login-header">
        <img src="{{ asset('images/logo-256.webp') }}" alt="Logo UNJ" width="120" height="118" decoding="async">
        <p class="login-kicker">Perpustakaan FMIPA UNJ</p>
        <h2>Daftar Akun</h2>
        <p>Buat akun Petugas pertama untuk membuka sistem pengarsipan tugas akhir</p>
    </div>

    @if (session('success'))
        <div class="login-alert login-alert--success" role="status">
            @include('auth.partials.icon', ['name' => 'check'])
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @php
        $bannerError = collect($errors->getMessages())
            ->reject(fn ($messages, $key) => in_array($key, ['useridentitynumber', 'username', 'useremail', 'userpassword'], true))
            ->flatten()
            ->first();
    @endphp

    @if ($bannerError)
        <div class="login-alert" role="alert">
            @include('auth.partials.icon', ['name' => 'warning'])
            <span>{{ $bannerError }}</span>
        </div>
    @endif

    <form id="registerform" method="POST" action="{{ route('register.submit') }}" novalidate>
        @csrf

        <input type="hidden" id="userrole" name="userrole" value="{{ \App\Models\User::ROLE_OFFICER }}">

        <div class="form-group">
            <label for="useridentitynumber">Nomor Identitas</label>
            <div class="input-icon">
                @include('auth.partials.icon', ['name' => 'id-card'])
                <input
                    type="text"
                    id="useridentitynumber"
                    name="useridentitynumber"
                    class="form-control @error('useridentitynumber') is-invalid @enderror"
                    value="{{ old('useridentitynumber') }}"
                    placeholder="NIP"
                    inputmode="numeric"
                    autocomplete="off"
                    required
                    autofocus>
            </div>
            @error('useridentitynumber')
                <div class="field-error" role="alert">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="reg_username">Nama</label>
            <div class="input-icon">
                @include('auth.partials.icon', ['name' => 'user'])
                <input
                    type="text"
                    id="reg_username"
                    name="username"
                    class="form-control @error('username') is-invalid @enderror"
                    value="{{ old('username') }}"
                    placeholder="Nama lengkap"
                    autocomplete="name"
                    required>
            </div>
            @error('username')
                <div class="field-error" role="alert">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="useremail">Email</label>
            <div class="input-icon">
                @include('auth.partials.icon', ['name' => 'envelope'])
                <input
                    type="email"
                    id="useremail"
                    name="useremail"
                    class="form-control @error('useremail') is-invalid @enderror"
                    value="{{ old('useremail') }}"
                    placeholder="email@example.com"
                    autocomplete="email"
                    required>
            </div>
            @error('useremail')
                <div class="field-error" role="alert">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="userpassword">Kata Sandi</label>
            <div class="input-icon">
                @include('auth.partials.icon', ['name' => 'lock'])
                <input
                    type="password"
                    id="userpassword"
                    name="userpassword"
                    class="form-control @error('userpassword') is-invalid @enderror"
                    placeholder="Minimal 8 karakter"
                    autocomplete="new-password"
                    required>
            </div>
            @error('userpassword')
                <div class="field-error" role="alert">{{ $message }}</div>
            @enderror
        </div>

        <p class="login-sessions-hint">
            Akun ini selalu berperan sebagai Petugas. Akun Koordinator dan Mahasiswa dibuat nanti dari menu Pengguna.
        </p>

        <button type="submit" class="login-btn" id="registerbtn">
            <span>Daftar</span>
            @include('auth.partials.icon', ['name' => 'arrow-right', 'class' => 'login-icon'])
        </button>
    </form>

    <div class="auth-switch">
        Sudah punya akun?
        <a href="{{ route('login') }}">Masuk</a>
    </div>

    <div class="footer">
        © {{ date('Y') }} Perpustakaan FMIPA Universitas Negeri Jakarta
    </div>
@endsection
