<nav class="module-nav">

    <div class="module-nav-container">

        <a href="{{ route('page-submission') }}" class="module-link">
            <i class="fas fa-box"></i>
            Penyerahan
        </a>

        <a href="{{ route('page-visitor') }}" class="module-link">
            <i class="fas fa-users"></i>
            Pengunjung
        </a>

        <a href="{{ route('page-destruction') }}" class="module-link">
            <i class="fas fa-boxes-packing"></i>
            Pemusnahan
        </a>

        <a href="{{ route('page-membership') }}" class="module-link">
            <i class="fas fa-id-card"></i>
            Keanggotaan
        </a>

        <a href="{{ route('page-libraryclearance') }}" class="module-link">
            <i class="fas fa-file-circle-check"></i>
            Bebas Pustaka
        </a>

        <a href="{{ route('page-bookgrant') }}" class="module-link">
            <i class="fas fa-book"></i>
            Hibah Buku
        </a>

        <a href="{{ route('page-studyprogram') }}" class="module-link">
            <i class="fas fa-building-columns"></i>
            Prodi
        </a>

        <a href="{{ route('page-finalprojecttype') }}" class="module-link">
            <i class="fas fa-graduation-cap"></i>
            Jenis TA
        </a>

        @if (auth()->user()->isOfficer())
            <a href="{{ route('page-import') }}" class="module-link">
                <i class="fas fa-file-import"></i>
                Impor Tugas Akhir
            </a>
        @endif

        <a href="{{ route('page-user') }}" class="module-link">
            <i class="fas fa-user-gear"></i>
            Pengguna
        </a>
        
    </div>

</nav>
