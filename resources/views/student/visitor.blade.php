@extends('auth.outline')

@section('title', 'ARSIP TA FMIPA UNJ — Daftar Kunjungan')
@section('body-class', 'auth-visitor')

@section('hero')
    @include('student.partials.hero-visitor')
@endsection

@section('auth-card')
    <div class="login-header">
        <img src="{{ asset('images/logo.png') }}" alt="Logo UNJ" width="96" height="95" decoding="async">
        <p class="login-kicker">Perpustakaan FMIPA UNJ</p>
        <h2>Formulir Pengunjung</h2>
        <p>Pendaftaran Kunjungan Perpustakaan FMIPA</p>
        <div class="open-hours">
            <i class="fa-regular fa-clock"></i>
            Senin - Jumat • 08.00 - 16.00
        </div>
    </div>

    @if (session('success'))
        <div class="login-alert login-alert--success" role="status">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="login-alert" role="alert">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <form id="visitorpublicform" method="POST" action="{{ route('store-public-visitor') }}" novalidate>
        @csrf

        <div class="form-group">
            <label for="visitor_nim" class="required-label">NIM / KTP</label>
            <div class="input-icon">
                <i class="fas fa-id-card"></i>
                <input
                    type="text"
                    id="visitor_nim"
                    name="nim"
                    class="form-control @error('nim') is-invalid @enderror"
                    value="{{ old('nim') }}"
                    placeholder="NIM atau KTP"
                    autocomplete="off"
                    required
                    autofocus>
            </div>
            @error('nim')
                <div class="field-error" role="alert">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="visitor_student_name" class="required-label">Nama Mahasiswa</label>
            <div class="input-icon">
                <i class="fa-regular fa-user"></i>
                <input
                    type="text"
                    id="visitor_student_name"
                    name="student_name"
                    class="form-control @error('student_name') is-invalid @enderror"
                    value="{{ old('student_name') }}"
                    placeholder="Nama Lengkap"
                    autocomplete="name"
                    required>
            </div>
            @error('student_name')
                <div class="field-error" role="alert">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="required-label">Program Studi</label>
            <div class="custom-select @error('program_study') is-invalid @enderror" id="visitor_program_select">
                <div class="custom-select-trigger" tabindex="0" role="button" aria-haspopup="listbox">
                    <div class="select-label">
                        <i class="fas fa-graduation-cap"></i>
                        <span @class(['contain' => old('program_study')])>
                            {{ old('program_study') ?: 'Pilih Program Studi' }}
                        </span>
                    </div>
                    <i class="fas fa-chevron-down rotate"></i>
                </div>

                <div class="custom-select-dropdown" role="listbox">
                    @foreach ($studyPrograms as $studyProgram)
                        <div
                            class="custom-option{{ old('program_study') === $studyProgram->study_program ? ' selected' : '' }}"
                            data-value="{{ $studyProgram->study_program }}"
                            role="option"
                        >
                            {{ $studyProgram->study_program }}
                        </div>
                    @endforeach
                </div>

                <input
                    type="hidden"
                    id="visitor_program_study"
                    name="program_study"
                    value="{{ old('program_study') }}"
                    required>
            </div>
            @error('program_study')
                <div class="field-error" role="alert">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="visitor_needs" class="required-label">Keperluan</label>
            <div class="input-icon textarea-icon">
                <i class="fas fa-clipboard-list"></i>
                <textarea
                    id="visitor_needs"
                    name="needs"
                    class="form-control form-textarea @error('needs') is-invalid @enderror"
                    placeholder="Contoh: Membaca, Riset dan Pengembalian Buku"
                    required>{{ old('needs') }}</textarea>
            </div>
            @error('needs')
                <div class="field-error" role="alert">{{ $message }}</div>
            @enderror
        </div>

        <div class="auth-actions">
            <button type="reset" class="login-btn login-btn--ghost" id="visitorresetbtn">
                <i class="fas fa-rotate-left login-icon login-icon--left"></i>
                <span>Reset</span>
            </button>

            <button type="submit" class="login-btn" id="visitorsubmitbtn">
                <span>Simpan</span>
                <i class="fas fa-floppy-disk login-icon"></i>
            </button>
        </div>
    </form>

    <div class="footer">
        © {{ date('Y') }} Perpustakaan FMIPA Universitas Negeri Jakarta
    </div>
@endsection
