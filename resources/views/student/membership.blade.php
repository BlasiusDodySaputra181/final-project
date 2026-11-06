@extends('auth.outline')

@section('title', 'ARSIP TA FMIPA UNJ — Keanggotaan Perpustakaan')
@section('body-class', 'auth-submission')

@push('styles')
<link rel="stylesheet" href="{{ asset_version('css/vendor/air-datepicker.min.css') }}">
<link rel="stylesheet" href="{{ asset_version('css/student-submission.css') }}">
@endpush

@section('standalone')
<div class="page">
    <div class="container">
        <div class="portal-card">

            @include('student.partials.submission-header')

            {{-- Bagian: pengantar --}}
            <div class="hero">
                <h2>Formulir Keanggotaan Perpustakaan</h2>
                <p>
                    Lengkapi data keanggotaan satu kali. Menu Bukti tampil
                    setelah data keanggotaan atau penyerahan tugas akhir
                    tersedia, baik diisi mahasiswa maupun petugas. Status
                    Terdaftar pada bebas pustaka serta hibah buku terisi
                    otomatis.
                </p>
            </div>

            @include('student.partials.submission-alert', [
                'type' => 'success',
                'title' => 'Keanggotaan Berhasil',
                'message' => session('success'),
            ])

            @include('student.partials.submission-alert', [
                'type' => 'error',
                'title' => 'Keanggotaan Gagal',
                'message' => session('error'),
            ])

            @include('student.partials.submission-alert', [
                'type' => 'error',
                'title' => 'Data Belum Lengkap',
                'messages' => $errors->all(),
            ])

            <form
                id="membershippublicform"
                method="POST"
                action="{{ route('store-public-membership') }}"
                novalidate
            >
                @csrf

                {{-- Bagian: isian keanggotaan --}}
                <section class="slide active-slide">
                    <div class="content">
                        <div class="section">
                            <div class="section-title">
                                <i class="fas fa-id-card"></i> Data Keanggotaan
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="membership_nim" class="required-label">NIM</label>
                                    <input
                                        type="text"
                                        id="membership_nim"
                                        class="is-account-locked"
                                        value="{{ $student?->identity_number ?? '' }}"
                                        readonly
                                        aria-readonly="true"
                                    >
                                    <small class="field-hint">Diisi otomatis dari akun yang sedang masuk dan tidak dapat diubah.</small>
                                </div>

                                <div class="form-group">
                                    <label for="membership_name" class="required-label">Nama Mahasiswa</label>
                                    <input
                                        type="text"
                                        id="membership_name"
                                        class="is-account-locked"
                                        value="{{ $student?->name ?? '' }}"
                                        readonly
                                        aria-readonly="true"
                                    >
                                    <small class="field-hint">Diisi otomatis dari akun yang sedang masuk dan tidak dapat diubah.</small>
                                </div>

                                @php
                                    $selectedProgram = $studyPrograms->firstWhere('id', (int) old('study_program_id'));
                                    $selectedProgramLabel = $selectedProgram
                                        ? trim(($selectedProgram->cluster ? $selectedProgram->cluster.' — ' : '').$selectedProgram->study_program)
                                        : null;
                                    $selectedGender = old('gender');
                                @endphp

                                <div class="form-group">
                                    <label class="required-label">Program Studi</label>
                                    <div
                                        class="custom-select searchable @error('study_program_id') is-invalid @enderror"
                                        id="membership_program_select"
                                        data-searchable="true"
                                    >
                                        <div class="custom-select-trigger" tabindex="0" role="button" aria-haspopup="listbox">
                                            <span @class(['contain' => $selectedProgramLabel])>
                                                {{ $selectedProgramLabel ?: 'Cari / pilih program studi' }}
                                            </span>
                                            <i class="fas fa-chevron-down"></i>
                                        </div>
                                        <div class="custom-select-dropdown" role="listbox">
                                            <div class="custom-select-search">
                                                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                                                <input
                                                    type="text"
                                                    class="custom-select-search-input"
                                                    placeholder="Cari jurusan atau program studi..."
                                                    autocomplete="off"
                                                    spellcheck="false"
                                                >
                                            </div>
                                            <div class="custom-select-options">
                                                @forelse ($studyPrograms as $studyProgram)
                                                    @php
                                                        $programLabel = trim(($studyProgram->cluster ? $studyProgram->cluster.' — ' : '').$studyProgram->study_program);
                                                    @endphp
                                                    <div
                                                        class="custom-option @if ((int) old('study_program_id') === (int) $studyProgram->id) selected @endif"
                                                        data-value="{{ $studyProgram->id }}"
                                                        data-cluster="{{ $studyProgram->cluster }}"
                                                        data-id="{{ $studyProgram->id }}"
                                                        data-label="{{ $programLabel }}"
                                                        role="option"
                                                    >
                                                        <span class="option-title">{{ $studyProgram->study_program }}</span>
                                                        @if ($studyProgram->cluster)
                                                            <span class="option-subtitle">{{ $studyProgram->cluster }}</span>
                                                        @endif
                                                    </div>
                                                @empty
                                                    <div class="custom-select-empty" data-static-empty="true">
                                                        Belum ada data program studi
                                                    </div>
                                                @endforelse
                                            </div>
                                            <div class="custom-select-empty" hidden>Tidak ada opsi yang cocok</div>
                                        </div>
                                        <input type="hidden" id="study_program_id" name="study_program_id" value="{{ old('study_program_id') }}" required>
                                    </div>
                                    @error('study_program_id')
                                        <div class="field-error" role="alert">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label class="required-label">Jenis Kelamin</label>
                                    <div class="custom-select @error('gender') is-invalid @enderror" id="membership_gender_select">
                                        <div class="custom-select-trigger" tabindex="0" role="button" aria-haspopup="listbox">
                                            <span @class(['contain' => filled($selectedGender)])>
                                                {{ $selectedGender ?: 'Pilih jenis kelamin' }}
                                            </span>
                                            <i class="fas fa-chevron-down"></i>
                                        </div>
                                        <div class="custom-select-dropdown" role="listbox">
                                            <div class="custom-select-options">
                                                @foreach ($genders as $gender)
                                                    <div
                                                        class="custom-option @if ($selectedGender === $gender) selected @endif"
                                                        data-value="{{ $gender }}"
                                                        role="option"
                                                    >
                                                        {{ $gender }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        <input type="hidden" id="gender" name="gender" value="{{ $selectedGender }}" required>
                                    </div>
                                    @error('gender')
                                        <div class="field-error" role="alert">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="place_of_birth" class="required-label">Tempat Lahir</label>
                                    <input
                                        type="text"
                                        id="place_of_birth"
                                        name="place_of_birth"
                                        value="{{ old('place_of_birth') }}"
                                        placeholder="Contoh: Bekasi"
                                        required
                                    >
                                    @error('place_of_birth')
                                        <div class="field-error" role="alert">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="date_of_birth_display" class="required-label">Tanggal Lahir</label>
                                    <input
                                        type="text"
                                        id="date_of_birth_display"
                                        class="date-picker"
                                        value="{{ $birthDateDisplay ?? '' }}"
                                        placeholder="Pilih tanggal"
                                        readonly
                                        required
                                    >
                                    <input
                                        type="hidden"
                                        id="date_of_birth"
                                        name="date_of_birth"
                                        value="{{ $birthDateStored ?? '' }}"
                                    >
                                    @error('date_of_birth')
                                        <div class="field-error" role="alert">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="origin_address" class="required-label">Alamat Asal</label>
                                    <textarea
                                        id="origin_address"
                                        name="origin_address"
                                        rows="4"
                                        placeholder="Alamat asal"
                                        required
                                    >{{ old('origin_address') }}</textarea>
                                    @error('origin_address')
                                        <div class="field-error" role="alert">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group">
                                    <label for="current_address" class="required-label">Alamat Sekarang</label>
                                    <textarea
                                        id="current_address"
                                        name="current_address"
                                        rows="4"
                                        placeholder="Alamat sekarang"
                                        required
                                    >{{ old('current_address') }}</textarea>
                                    @error('current_address')
                                        <div class="field-error" role="alert">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="actions">
                                <button type="submit" class="btn btn-primary" id="submitMembershipBtn">
                                    Simpan Keanggotaan <i class="fas fa-floppy-disk"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Bagian: ringkasan sebelum simpan --}}
                        @include('student.partials.submission-summary', [
                            'items' => [
                                ['label' => 'Peran', 'value' => 'Mahasiswa'],
                                ['label' => 'NIM', 'value' => $student?->identity_number ?: '—'],
                                ['label' => 'Nama', 'value' => $student?->name ?: '—'],
                                ['label' => 'Status', 'value' => 'Belum terdaftar'],
                            ],
                            'info' => 'NIM dan nama terisi otomatis dari akun yang sedang masuk. Lengkapi program studi, jenis kelamin, tempat lahir, tanggal lahir, serta alamat asal dan sekarang. Keanggotaan hanya didaftarkan sekali per akun mahasiswa.',
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
<script src="{{ asset_version('js/vendor/air-datepicker.min.js') }}" defer></script>
<script src="{{ asset_version('js/student-membership.js') }}" defer></script>
@endpush
