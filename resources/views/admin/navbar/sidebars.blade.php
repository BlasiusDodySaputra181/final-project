<div class="sidebar-body">

    <div class="menu">

        <a href="{{ route('page-dashboard') }}">
            <i class="fas fa-chart-pie"></i>
            <span>Dasbor</span>
        </a>

        <div class="menu-group">

            <a href="#" class="menu-collapse" id="menufinalproject">

                <div class="menu-left">
                    <i class="fas fa-book"></i>
                    <span>Tugas Akhir</span>
                </div>

                <i class="fas fa-chevron-down arrow"></i>

            </a>

            <div class="submenu" id="submenufinalproject">

                @forelse ($finalProjectTypes ?? [] as $finalProjectType)
                    <a href="{{ route('page-finalproject', ['type' => $finalProjectType->slug]) }}">
                        <i class="fas fa-graduation-cap"></i>
                        <span>{{ $finalProjectType->type }}</span>
                    </a>
                @empty
                    <a href="#" class="disabled">
                        <i class="fas fa-graduation-cap"></i>
                        <span>Belum ada jenis tugas akhir</span>
                    </a>
                @endforelse

            </div>

        </div>

        <a href="{{ route('page-barcode') }}">
            <i class="fas fa-barcode"></i>
            <span>Kode Batang</span>
        </a>

        <a href="{{ route('page-archive') }}">
            <i class="fas fa-box-archive"></i>
            <span>Arsip</span>
        </a>

        <a href="{{ route('page-report') }}">
            <i class="fas fa-clipboard-check"></i>
            <span>Laporan</span>
        </a>

    </div>

    <div class="sidebar-footer">

        @if (auth()->user()?->isOfficer())
            <a href="{{ route('page-setting') }}" class="help-link" id="menusetting" title="Pengaturan folder penyimpanan dan akses mahasiswa">
                <i class="fas fa-gears"></i>
                <span>Pengaturan</span>
            </a>
        @endif

        <a href="#" class="help-link" id="helptourbtn" title="Mulai petunjuk fitur halaman ini">
            <i class="fas fa-circle-question"></i>
            <span>Bantuan</span>
        </a>

    </div>

</div>
