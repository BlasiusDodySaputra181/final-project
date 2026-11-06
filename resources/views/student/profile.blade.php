@extends('auth.outline')

@section('title', 'ARSIP TA FMIPA UNJ — Profil Mahasiswa')
@section('body-class', 'auth-submission auth-profile')

@push('styles')
<link rel="stylesheet" href="{{ asset_version('css/student-submission.css') }}">
@endpush

@section('standalone')
<div class="page">
    <div class="container">
        <div class="portal-card">

            @include('student.partials.submission-header')

            <div class="hero">
                <h2>Profil Mahasiswa</h2>
                <p>
                    Perbarui NIM, nama, email, dan kata sandi akun Anda.
                    NIM dipakai otomatis pada keanggotaan, penyerahan tugas akhir,
                    dan bukti. Peran tidak dapat diubah dari halaman ini.
                </p>
            </div>

            @include('student.partials.submission-alert', [
                'type' => 'success',
                'title' => 'Profil Diperbarui',
                'message' => session('success'),
            ])

            @include('student.partials.submission-alert', [
                'type' => 'error',
                'title' => 'Profil Gagal Disimpan',
                'message' => session('error'),
            ])

            @include('student.partials.submission-alert', [
                'type' => 'error',
                'title' => 'Data Belum Lengkap',
                'messages' => $errors->all(),
            ])

            <form
                id="profilepublicform"
                method="POST"
                action="{{ route('store-public-profile') }}"
                novalidate
            >
                @csrf

                <section class="slide active-slide">
                    <div class="content">
                        <div class="section">
                            <div class="section-title">
                                <i class="fas fa-user-pen"></i> Data Profil
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="profileidentitynumber" class="required-label">NIM</label>
                                    <input
                                        type="text"
                                        id="profileidentitynumber"
                                        name="identity_number"
                                        value="{{ old('identity_number', $user->identity_number) }}"
                                        placeholder="NIM"
                                        autocomplete="username"
                                        required
                                        autofocus
                                    >
                                    <small class="field-hint">Nomor Induk Mahasiswa. Dipakai pada keanggotaan dan penyerahan tugas akhir.</small>
                                    @error('identity_number')
                                        <div class="field-error" role="alert">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="profilename" class="required-label">Nama</label>
                                    <input
                                        type="text"
                                        id="profilename"
                                        name="name"
                                        value="{{ old('name', $user->name) }}"
                                        placeholder="Nama Lengkap"
                                        autocomplete="name"
                                        required
                                    >
                                    @error('name')
                                        <div class="field-error" role="alert">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="profileemail" class="required-label">Email</label>
                                    <input
                                        type="email"
                                        id="profileemail"
                                        name="email"
                                        value="{{ old('email', $user->email) }}"
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
                                        class="is-account-locked"
                                        value="{{ $user->role }}"
                                        readonly
                                        aria-readonly="true"
                                    >
                                    <small class="field-hint">Peran tidak dapat diubah dari halaman profil.</small>
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

                            <div class="actions">
                                <button type="submit" class="btn btn-primary" id="buttonprofile">
                                    Simpan Profil <i class="fas fa-circle-check"></i>
                                </button>
                            </div>
                        </div>

                        @include('student.partials.submission-summary', [
                            'items' => [
                                ['label' => 'Peran', 'value' => $user->role],
                                ['label' => 'NIM', 'value' => $user->identity_number ?: '—'],
                                ['label' => 'Nama', 'value' => $user->name ?: '—'],
                                ['label' => 'Email', 'value' => $user->email ?: '—'],
                            ],
                            'info' => 'Kata sandi dienkripsi dengan Argon2id. NIM dan nama pada formulir keanggotaan serta penyerahan mengikuti data akun ini.',
                        ])
                    </div>

                    <div class="footer">
                        © {{ date('Y') }} Perpustakaan FMIPA Universitas Negeri Jakarta
                    </div>
                </section>
            </form>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset_version('js/student-profile.js') }}" defer></script>
@endpush
