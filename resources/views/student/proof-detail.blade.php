@extends('auth.outline')

@section('title', 'ARSIP TA FMIPA UNJ — Detail Bukti Penyerahan')
@section('body-class', 'auth-proof')

@push('styles')
<link rel="stylesheet" href="{{ asset_version('css/student-proof.css') }}">
@endpush

@section('standalone')
<div class="page">
    <div class="container">
        <div class="portal-card">

            {{-- Section: header --}}
            <div class="header">
                <div class="brand">
                    <img src="{{ asset('images/logo.png') }}" alt="Logo UNJ" class="logo" width="95" height="94">
                    <div>
                        <small>Universitas Negeri Jakarta</small>
                        <h1>Perpustakaan FMIPA</h1>
                    </div>
                </div>

                <a href="{{ route('page-public-proof') }}" class="proof-back-link">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    <span>Kembali</span>
                </a>
            </div>

            {{-- Section: introduction --}}
            <div class="hero">
                <h2>Bukti Penyerahan dan Keanggotaan</h2>
                <p>
                    Ringkasan data tugas akhir yang diserahkan beserta keanggotaan, bebas pustaka
                    dan hibah buku yang tercatat pada sistem.
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

            {{-- Section: final project --}}
            <section class="proof-section">
                <div class="proof-section-title">
                    <i class="fas fa-graduation-cap" aria-hidden="true"></i>
                    <span>Tugas Akhir</span>
                </div>

                <div class="proof-panel">
                    <div class="proof-meta-grid">
                        <div class="proof-meta-item">
                            <div class="proof-meta-label">NIM</div>
                            <div class="proof-meta-value">{{ $finalProject->nim ?: '-' }}</div>
                        </div>
                        <div class="proof-meta-item">
                            <div class="proof-meta-label">Nama Mahasiswa</div>
                            <div class="proof-meta-value">{{ $finalProject->student_name ?: '-' }}</div>
                        </div>
                        <div class="proof-meta-item full">
                            <div class="proof-meta-label">Judul Tugas Akhir</div>
                            <div class="proof-meta-value">{{ $finalProject->final_project_title ?: '-' }}</div>
                        </div>
                        <div class="proof-meta-item">
                            <div class="proof-meta-label">Program Studi</div>
                            <div class="proof-meta-value">{{ $finalProject->program_study ?: '-' }}</div>
                        </div>
                        <div class="proof-meta-item">
                            <div class="proof-meta-label">Jenjang</div>
                            <div class="proof-meta-value">{{ $finalProject->degree_level ?: '-' }}</div>
                        </div>
                        <div class="proof-meta-item">
                            <div class="proof-meta-label">Tahun Tugas Akhir</div>
                            <div class="proof-meta-value">{{ $finalProject->final_project_year ?: '-' }}</div>
                        </div>
                        <div class="proof-meta-item">
                            <div class="proof-meta-label">Tanggal Penyerahan</div>
                            <div class="proof-meta-value">
                                {{ $finalProject->submission_date?->locale('id')->translatedFormat('j F Y') ?: '-' }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Section: library clearance --}}
            <section class="proof-section">
                <div class="proof-section-title">
                    <i class="fas fa-file-circle-check" aria-hidden="true"></i>
                    <span>Bebas Pustaka</span>
                </div>

                @if ($libraryClearances->isEmpty())
                    <div class="proof-empty">Belum ada data bebas pustaka untuk penyerahan ini.</div>
                @else
                    <div class="proof-stack">
                        @foreach ($libraryClearances as $clearance)
                            <article class="proof-panel">
                                <div class="proof-panel-head">
                                    <div class="proof-panel-head-text">
                                        <span class="proof-panel-kicker">Status keanggotaan</span>
                                        <h3 class="proof-panel-title">Data Bebas Pustaka</h3>
                                    </div>
                                    <span @class([
                                        'proof-badge',
                                        'proof-badge--success' => membership_is_registered($clearance->membership_registered),
                                        'proof-badge--muted' => ! membership_is_registered($clearance->membership_registered),
                                    ])>
                                        <i class="fas {{ membership_is_registered($clearance->membership_registered) ? 'fa-circle-check' : 'fa-clock' }}" aria-hidden="true"></i>
                                        {{ membership_status_label($clearance->membership_registered) }}
                                    </span>
                                </div>

                                <div class="proof-meta-grid proof-meta-grid--clearance">
                                    <div class="proof-meta-item">
                                        <div class="proof-meta-label">Surel</div>
                                        <div class="proof-meta-value">{{ $clearance->email ?: '-' }}</div>
                                    </div>
                                    <div class="proof-meta-item">
                                        <div class="proof-meta-label">No. Telepon</div>
                                        <div class="proof-meta-value">{{ $clearance->phone_number ?: '-' }}</div>
                                    </div>
                                    <div class="proof-meta-item">
                                        <div class="proof-meta-label">Tahun Lulus</div>
                                        <div class="proof-meta-value">{{ $clearance->graduation_year ?: '-' }}</div>
                                    </div>
                                    <div class="proof-meta-item">
                                        <div class="proof-meta-label">Semester Lulus</div>
                                        <div class="proof-meta-value">{{ $clearance->semester_graduated ?: '-' }}</div>
                                    </div>
                                </div>

                                <div class="proof-panel-actions">
                                    <a
                                        href="{{ route('letter-public-libraryclearance', $clearance->id) }}"
                                        class="proof-download-btn"
                                        data-proof-letter
                                        data-membership="{{ $clearance->membership_registered }}"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        <i class="fas fa-file-arrow-down" aria-hidden="true"></i>
                                        <span>Unduh Surat Bebas Pustaka</span>
                                    </a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Section: book grant --}}
            <section class="proof-section">
                <div class="proof-section-title">
                    <i class="fas fa-book" aria-hidden="true"></i>
                    <span>Hibah Buku</span>
                </div>

                @if ($bookGrants->isEmpty())
                    <div class="proof-empty">Tidak ada hibah buku pada penyerahan ini.</div>
                @else
                    <div class="proof-book-grid">
                        @foreach ($bookGrants as $index => $grant)
                            <article class="proof-book-card">
                                <div class="proof-book-card-top">
                                    <span class="proof-book-index">{{ $index + 1 }}</span>
                                    <span @class([
                                        'proof-badge',
                                        'proof-badge--success' => membership_is_registered($grant->membership_registered),
                                        'proof-badge--muted' => ! membership_is_registered($grant->membership_registered),
                                    ])>
                                        {{ membership_status_label($grant->membership_registered) }}
                                    </span>
                                </div>
                                <h3 class="proof-book-title">{{ $grant->book_title ?: '-' }}</h3>
                                <div class="proof-book-meta">
                                    <div>
                                        <span class="proof-meta-label">Pengarang</span>
                                        <span class="proof-meta-value">{{ $grant->author ?: '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="proof-meta-label">Tahun</span>
                                        <span class="proof-meta-value">{{ $grant->book_year ?: '-' }}</span>
                                    </div>
                                </div>

                                <a
                                    href="{{ route('letter-public-bookgrant', $grant->id) }}"
                                    class="proof-download-btn"
                                    data-proof-letter
                                    data-membership="{{ $grant->membership_registered }}"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <i class="fas fa-file-arrow-down" aria-hidden="true"></i>
                                    <span>Unduh Surat Hibah Buku</span>
                                </a>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Section: footer --}}
            <div class="proof-footer">
                © {{ date('Y') }} Perpustakaan FMIPA Universitas Negeri Jakarta
            </div>

        </div>
    </div>
</div>

@include('student.partials.proof-letter-modal')
@endsection

@push('scripts')
<script src="{{ asset_version('js/student-proof.js') }}" defer></script>
@endpush
