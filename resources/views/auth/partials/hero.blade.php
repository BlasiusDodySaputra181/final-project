{{-- Hero kiri — dipakai login & daftar --}}
<section class="hero">
    <div class="hero-content">
        <div class="logo">
            <img src="{{ asset('images/logo-256.webp') }}" alt="Logo UNJ" width="72" height="72" decoding="async">
        </div>

        <small>PERPUSTAKAAN FMIPA UNJ</small>

        <h1>
            Aplikasi Arsip
            Tugas Akhir
            Digital
        </h1>

        <p>
            Sistem pengelolaan arsip tugas akhir, skripsi,
            tesis, dan disertasi yang terintegrasi untuk
            mendukung layanan perpustakaan digital FMIPA
            Universitas Negeri Jakarta.
        </p>

        <div class="stats">
            <div class="stat">
                <h3>{{ $archiveCountLabel ?? '0' }}</h3>
                <p>Dokumen Arsip</p>
            </div>
            <div class="stat">
                <h3>{{ $studyProgramCount ?? '0' }}</h3>
                <p>Program Studi</p>
            </div>
            <div class="stat">
                <h3>{{ $dailyVisitorCountLabel ?? '0' }}</h3>
                <p>Pengunjung Harian</p>
            </div>
        </div>
    </div>
</section>
