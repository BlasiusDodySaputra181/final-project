@extends('module.outline.apps')

@section('content')
<div class="table-card profile-card local-server" id="localserver" data-phase="{{ $server['phase'] }}" data-status-url="{{ route('status-local-server', absolute: false) }}" data-browse-url="{{ route('browse-local-server', absolute: false) }}" data-database-ready="{{ $server['database_ready'] ? '1' : '0' }}">

    <div class="form-section">
        <div class="section-title">
            <span class="section-badge">
                <i class="fas fa-desktop"></i>
            </span>
            PELADEN DI KOMPUTER INI
        </div>

        <div class="info-box">
            <i class="fas fa-circle-info"></i>
            <span>
                Satu klik memasang yang dibutuhkan ke folder aplikasi ini:
                peladen web (pengganti Apache), basis data (pengganti MySQL dan phpMyAdmin),
                serta satu tautan Worker Cloudflare.
                Tidak perlu sewa peladen. Akun Cloudflare tidak diatur di dalam aplikasi.
                Saat komputer <strong>luring</strong>, hanya petugas di komputer ini yang dapat menjalankan aplikasi.
                Saat <strong>daring</strong>, Wi-Fi, data seluler, laptop, dan perangkat lain membuka tautan Worker yang sama, dari lokasi mana pun. Alamat IP tidak dipakai karena berubah saat jaringan berganti.
                Komputer ini harus tetap menyala.
            </span>
        </div>

        <div class="local-server-grid" id="localserverparts">
            @foreach ($server['components'] as $component)
                <article class="local-server-card">
                    <h3>{{ $component['title'] }}</h3>
                    @if ($component['replaces'] !== '')
                        <p class="local-server-replaces">Pengganti {{ $component['replaces'] }}</p>
                    @endif
                    <p>{{ $component['detail'] }}</p>
                </article>
            @endforeach
        </div>

        @include('admin.partials.device-capacity')

        <div class="storage-status-row" id="localserverstate">
            <span @class([
                'storage-badge',
                'storage-badge--ready' => $server['listening'],
                'storage-badge--default' => ! $server['listening'],
            ]) id="localserverphase">{{ $server['phase_label'] }}</span>
            <span class="storage-badge storage-badge--default">
                {{ $server['listening'] ? ($server['online'] ? 'Daring untuk klien' : 'Hanya komputer ini') : 'Peladen mandiri belum menyala' }}
            </span>
        </div>

        <div class="info-box" id="localservermessage" @if ($server['message'] === '') hidden @endif>
            <i class="fas fa-circle-info"></i>
            <span id="localservermessagetext">{{ $server['message'] }}</span>
        </div>

        <div class="local-server-links">
            <div class="local-server-link">
                <div>
                    <strong>Komputer ini</strong>
                    <code id="localserverlocal">{{ $server['local_url'] }}</code>
                </div>
                <button type="button" class="btn btn-default" data-copy-target="#localserverlocal">Salin</button>
            </div>
            <div class="local-server-link" id="localserversharebox">
                <div>
                    <strong>Tautan untuk klien</strong>
                    <code id="localserverpublic" data-empty="Belum ada. Simpan satu tautan Worker.">{{ $server['public_url'] ?: 'Belum ada. Simpan satu tautan Worker.' }}</code>
                </div>
                <button type="button" class="btn btn-default" data-copy-target="#localserverpublic">Salin</button>
            </div>
        </div>

        <div class="local-notes">
            @if ($cloudflareLink !== '')
                <p class="storage-path-hint" id="localserverqrrow">
                    Kode QR memakai tautan Worker yang tersimpan.
                    <a id="localserverqr" href="{{ route('page-dashboard') }}">Buka pratinjau di dasbor</a>
                    untuk dilihat dan dicetak. Tautan itu tetap satu, termasuk setelah ganti jaringan.
                </p>
            @endif

            <p class="storage-path-hint">
                Berkas basis data peladen mandiri:
                <strong id="localserverfile">{{ $server['database_file'] }}</strong>
                @if ($server['database_ready'])
                    ({{ number_format($server['database_bytes'] / 1024, 0, ',', '.') }} KB)
                @else
                    (belum dibuat)
                @endif
            </p>
            <p class="storage-path-hint">
                Aplikasi yang sedang terbuka memakai
                <strong>{{ strtoupper($server['driver']) }}</strong>
                @if ($server['driver'] === 'sqlite')
                    pada berkas di folder ini.
                @else
                    ({{ $server['driver_database'] }}). Pintasan ARSIP-TA memakai berkas SQLite di folder aplikasi. Pilihan di bawah menyalin berkas lama ke folder ini, atau membuat basis data baru.
                @endif
            </p>
        </div>

        @if (session('success'))
            <div class="info-box">
                <i class="fas fa-circle-info"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="info-box info-box--warning">
                <i class="fas fa-triangle-exclamation"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <div class="local-placement" id="cloudflaresettings">
            <h3>Tautan QR</h3>
            <p class="storage-path-hint">
                Kode QR memakai satu tautan Worker Cloudflare.
                Simpan sekali. Setelah tersimpan, tautan itu tidak dapat diganti, supaya hasil cetak tetap dapat dibuka.
                @if ($cloudflareLink !== '')
                    Tautan ini sudah terkunci.
                @else
                    Tempel tautan workers.dev, atau nama host https tetap dari Worker itu.
                @endif
            </p>
            @if ($cloudflareLink === '')
                <form method="POST" action="{{ route('store-local-cloudflare') }}" novalidate>
                    @csrf
                    <div class="form-group">
                        <label for="cloudflarelink">Tautan Worker</label>
                        <input type="text" name="link" id="cloudflarelink" value="{{ old('link') }}" placeholder="https://arsip.nama.workers.dev" spellcheck="false" autocomplete="off" inputmode="url" maxlength="200">
                    </div>
                    <div class="local-server-actions">
                        <button type="submit" class="btn btn-theme" id="cloudflaresave">
                            <i class="fas fa-qrcode"></i>
                            Simpan tautan
                        </button>
                    </div>
                </form>
            @else
                <div class="form-group">
                    <label for="cloudflarelink">Tautan Worker</label>
                    <input type="text" id="cloudflarelink" value="{{ $cloudflareLink }}" readonly>
                </div>
                <img class="local-static-qr" src="{{ route('access-qr-png') }}?v={{ md5($cloudflareLink) }}" alt="Kode QR tautan internet" width="180" height="180">
                <div class="local-server-actions">
                    <a class="btn btn-default" href="{{ route('pdf-access-qrcode') }}" target="_blank" rel="noopener">Lihat dan cetak</a>
                </div>
            @endif
        </div>

        <div class="local-placement local-split" id="installplacement">
            <div class="local-block">
                <h3>Basis data</h3>
                @if ($server['database_ready'])
                    <label class="local-check" for="databasemodekeep">
                        <input type="radio" name="database_mode" id="databasemodekeep" value="keep" checked>
                        <span>Pakai basis data yang sudah ada di folder aplikasi ini.</span>
                    </label>
                @endif
                <label class="local-check" for="databasemodenew">
                    <input type="radio" name="database_mode" id="databasemodenew" value="new" @checked(! $server['database_ready'])>
                    <span>Buat basis data baru. Berkas lama, jika ada, disimpan sebagai cadangan.</span>
                </label>
                <label class="local-check" for="databasemodeexisting">
                    <input type="radio" name="database_mode" id="databasemodeexisting" value="existing">
                    <span>Pakai berkas basis data yang sudah ada. Berkas itu disalin ke folder aplikasi ini.</span>
                </label>
                <div class="form-group">
                    <label for="databasefilepath">Berkas basis data</label>
                    <div class="storage-path-row">
                        <input type="text" id="databasefilepath" spellcheck="false" autocomplete="off" placeholder="D:\Cadangan\database.sqlite" disabled>
                        <button type="button" class="btn btn-primary" id="pickdatabasefile" disabled>
                            <i class="fas fa-database"></i>
                            Pilih berkas
                        </button>
                    </div>
                </div>
                @if ($canCopy)
                    <label class="local-check" for="copydatabase">
                        <input type="checkbox" id="copydatabase" checked>
                        <span>Salin data MySQL yang ada ke berkas di folder ini. Tidak dipakai bila Anda memilih berkas basis data yang sudah ada.</span>
                    </label>
                    <label class="local-check" for="adoptdatabase">
                        <input type="checkbox" id="adoptdatabase">
                        <span>Jadikan berkas ini basis data aplikasi yang sedang terbuka, termasuk saat dibuka lewat Laragon. Pengaturan MySQL disimpan sebagai cadangan .env.mysql-backup.</span>
                    </label>
                @endif
            </div>

            <div class="local-block">
                <h3>Folder berkas ARSIP TUGAS AKHIR</h3>
                <p class="storage-path-hint">
                    Lokasi berkas saat ini:
                    <strong>{{ $usingDefaultArchive ? $archiveDefault : $archivePath }}</strong>
                </p>
                <label class="local-check" for="archivemodekeep">
                    <input type="radio" name="archive_mode" id="archivemodekeep" value="keep" checked>
                    <span>Ikuti lokasi yang tersimpan di basis data.</span>
                </label>
                <label class="local-check" for="archivemodenew">
                    <input type="radio" name="archive_mode" id="archivemodenew" value="new">
                    <span>Buat folder ARSIP TUGAS AKHIR baru. Kosong berarti folder bawaan aplikasi.</span>
                </label>
                <label class="local-check" for="archivemodeexisting">
                    <input type="radio" name="archive_mode" id="archivemodeexisting" value="existing">
                    <span>Pakai folder ARSIP TUGAS AKHIR yang sudah ada, atau folder induknya.</span>
                </label>
                <div class="form-group">
                    <label for="archivefilepath">Lokasi folder</label>
                    <div class="storage-path-row">
                        <input type="text" id="archivefilepath" spellcheck="false" autocomplete="off" placeholder="{{ $archiveDefault }}" disabled>
                        <button type="button" class="btn btn-primary" id="pickarchivefolder" disabled>
                            <i class="fas fa-folder-open"></i>
                            Pilih folder
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @include('admin.local-server.exchange')

        <div class="local-server-actions">
            <button type="button" class="btn btn-theme" id="localserverinstall" data-local-action="{{ route('install-local-server', absolute: false) }}">
                <i class="fas fa-download"></i>
                Pasang semua
            </button>
            <button type="button" class="btn btn-primary" id="localserverstart" data-local-action="{{ route('start-local-server', absolute: false) }}">
                <i class="fas fa-play"></i>
                Jalankan
            </button>
            <button type="button" class="btn btn-success" id="localservershare" data-local-action="{{ route('share-local-server', absolute: false) }}">
                <i class="fas fa-link"></i>
                Bagikan
            </button>
            <button type="button" class="btn btn-default" id="localserverunshare" data-local-action="{{ route('unshare-local-server', absolute: false) }}">
                Hentikan tautan
            </button>
            <button type="button" class="btn btn-danger" id="localserverstop" data-local-action="{{ route('stop-local-server', absolute: false) }}">
                <i class="fas fa-stop"></i>
                Hentikan peladen
            </button>
            <button type="button" class="btn btn-danger" id="localserveruninstall" data-local-action="{{ route('uninstall-local-server', absolute: false) }}">
                <i class="fas fa-trash"></i>
                Copot pemasangan
            </button>
            <button type="button" class="btn btn-default" data-pwa-install hidden>
                <i class="fas fa-mobile-screen"></i>
                Pasang jendela aplikasi
            </button>
            <a class="btn btn-default" id="localserverdatabase" href="{{ route('page-local-database') }}">
                <i class="fas fa-database"></i>
                Panel basis data
            </a>
        </div>

        <p class="storage-path-hint">
            Klik pertama mengunduh PHP dan cloudflared ke folder <strong>laravel-assets</strong>.
            Biarkan halaman ini terbuka sampai status berubah.
            Pasang ke perangkat: <strong>Pasang-ke-Perangkat.cmd</strong>.
            Copot berlaku di Windows, Linux, macOS, dan Unix lain.
            Klik kanan pintasan <strong>ARSIP-TA — Arsip Tugas Akhir FMIPA UNJ</strong>, pilih <strong>Copot</strong>.
            Atau jalankan <strong>Copot-ARSIP-TA.cmd</strong>, atau <strong>sh copot.sh</strong>.
            Copot pemasangan menimpa isi berkas, lalu menghapus folder aplikasi, registri, catatan, pintasan, dan sisa berkas secara permanen.
            Folder berkas yang berada di luar folder aplikasi tidak dihapus.
        </p>
    </div>

</div>

<div class="modal-overlay" id="localpickermodal">
    <div class="card-modal card-modal--storage">
        <div class="modal-header">
            <div class="modal-title-wrap">
                <div class="modal-icon">
                    <i class="fas fa-folder-tree"></i>
                </div>
                <div>
                    <h2 id="localpickertitle">Pilih lokasi</h2>
                    <p id="localpickerhint">Pilih di kandar yang terhubung ke komputer ini.</p>
                </div>
            </div>
            <button type="button" class="modal-close" id="localpickerclose">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <div class="storage-picker-body">
            <div class="storage-picker-toolbar">
                <button type="button" class="btn btn-default storage-picker-nav" id="localpickercomputer" title="Semua penyimpanan yang terhubung">
                    <i class="fas fa-computer"></i>
                </button>
                <button type="button" class="btn btn-default storage-picker-nav" id="localpickerup" title="Folder induk">
                    <i class="fas fa-arrow-up"></i>
                </button>
                <input type="text" id="localpickerpath" spellcheck="false" autocomplete="off">
                <button type="button" class="btn btn-primary" id="localpickergo">Buka</button>
            </div>
            <div class="storage-picker-layout">
                <aside class="storage-picker-shortcuts" id="localpickershortcuts"></aside>
                <div class="storage-picker-list-wrap">
                    <div class="storage-picker-status" id="localpickerstatus"></div>
                    <ul class="storage-picker-list" id="localpickerlist"></ul>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" id="localpickercancel">Batal</button>
            <button type="button" class="btn-modal-save" id="localpickeruse">Gunakan</button>
        </div>
    </div>
</div>
@endsection
