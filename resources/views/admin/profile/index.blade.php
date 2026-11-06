@extends('module.outline.apps')

@php($profileUser = $user ?? auth()->user())
@section('hero-title', ($profileUser?->isOfficer() || $profileUser?->isCoordinator()) ? 'Profil Pengguna' : 'Pengaturan Profil')
@section('hero-text', 'Perbarui data akun Anda pada sistem arsip tugas akhir FMIPA Universitas Negeri Jakarta.')

@section('content')

<div class="table-card profile-card">

    @if (session('success'))
        <div class="profile-alert profile-alert--success" role="status">
            <i class="fas fa-circle-check"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="profile-alert profile-alert--error" role="alert">
            <i class="fas fa-circle-exclamation"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if (isset($errors) && $errors->any())
        <div class="profile-alert profile-alert--error" role="alert">
            <i class="fas fa-circle-exclamation"></i>
            <div>
                <strong>Data belum lengkap</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form
        id="profileform"
        class="profile-form"
        method="POST"
        action="{{ route('store-profile') }}"
        novalidate
    >
        @csrf

        <div class="form-section">

            <div class="section-title">
                <span class="section-badge">
                    <i class="fas fa-id-card"></i>
                </span>
                DATA PROFIL
            </div>

            <div class="form-grid">

                <div class="form-group">
                    <label for="profileidentitynumber">NIP</label>
                    <input
                        type="text"
                        id="profileidentitynumber"
                        name="identity_number"
                        value="{{ old('identity_number', $profileUser->identity_number) }}"
                        placeholder="NIP"
                        autocomplete="username"
                    >
                    <small class="field-hint">Nomor Induk Pegawai. Opsional, dan tidak mengubah peran akun.</small>
                    @error('identity_number')
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="required-label" for="profilename">Nama</label>
                    <input
                        type="text"
                        id="profilename"
                        name="name"
                        value="{{ old('name', $profileUser->name) }}"
                        placeholder="Nama Lengkap"
                        autocomplete="name"
                        required
                    >
                    @error('name')
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="required-label" for="profileemail">Email</label>
                    <input
                        type="email"
                        id="profileemail"
                        name="email"
                        value="{{ old('email', $profileUser->email) }}"
                        placeholder="email@example.com"
                        autocomplete="email"
                        required
                    >
                    @error('email')
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="profilerole">Peran</label>
                    <input
                        type="text"
                        id="profilerole"
                        value="{{ $profileUser->role }}"
                        readonly
                        aria-readonly="true"
                    >
                    <small class="field-hint">Peran Petugas atau Koordinator tidak dapat diubah dari halaman ini.</small>
                </div>

                <div class="form-group">
                    <label for="profilepassword">Kata Sandi Baru</label>
                    <input
                        type="password"
                        id="profilepassword"
                        name="password"
                        placeholder="Kosongkan jika tidak diganti"
                        autocomplete="new-password"
                    >
                    @error('password')
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="profilepassword_confirmation">Konfirmasi Kata Sandi</label>
                    <input
                        type="password"
                        id="profilepassword_confirmation"
                        name="password_confirmation"
                        placeholder="Ulangi kata sandi baru"
                        autocomplete="new-password"
                    >
                </div>

            </div>

        </div>

        <div class="info-box">
            <i class="fas fa-circle-info"></i>
            <span>Kata sandi dienkripsi dengan Argon2id. Peran tidak dapat diubah dari halaman profil.</span>
        </div>

        <div class="profile-actions">
            <button type="submit" class="btn btn-theme" id="buttonprofile">
                <i class="fas fa-circle-check"></i>
                Simpan Profil
            </button>
        </div>

    </form>

</div>
@endsection
