{{-- Hero kiri — formulir daftar kunjungan publik (mahasiswa) --}}
<section class="hero">
    <div class="hero-content">
        <div class="logo">
            <img src="{{ asset('images/logo.png') }}" alt="Logo UNJ" width="72" height="72" decoding="async">
        </div>

        <small>PERPUSTAKAAN FMIPA UNJ</small>

        <h1>Daftar Pengunjung</h1>

        <p>
            Aplikasi pencatatan kunjungan perpustakaan FMIPA Universitas Negeri Jakarta
            untuk mendukung layanan informasi, peminjaman koleksi, dan aktivitas
            akademik secara terintegrasi.
        </p>

        <div class="stats">
            <div class="stat">
                <h3>{{ $visitorCountLabel }}</h3>
                <p>Total Kunjungan</p>
            </div>
            <div class="stat">
                <h3>{{ $studyProgramCount }}</h3>
                <p>Program Studi</p>
            </div>
            <div class="stat">
                <h3>{{ $visitorAccessOpen ? 'Buka' : 'Tutup' }}</h3>
                <p>Akses Form</p>
            </div>
        </div>
    </div>
</section>
