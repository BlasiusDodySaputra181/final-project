@extends('module.outline.apps')

@section('content')
<div class="table-card profile-card">

    {{-- Bagian: akses formulir mahasiswa --}}
    <div class="form-section" id="accesssettings">

        <div class="section-title">
            <span class="section-badge">
                <i class="fas fa-toggle-on"></i>
            </span>
            AKSES MAHASISWA
        </div>

        <div class="info-box">
            <i class="fas fa-circle-info"></i>
            <span>
                Sakelar ini berlaku <strong>secara global</strong> untuk seluruh mahasiswa.
                Nyala = <strong>buka</strong> (formulir atau pindai dapat dipakai).
                Mati = <strong>tutup</strong> (mahasiswa melihat bahwa akses sedang ditutup oleh petugas).
                Perubahan tersimpan langsung, tanpa tombol Simpan Pengaturan.
            </span>
        </div>

        <div class="access-setting-list">

            <div class="access-setting-item" id="visitoraccessitem">
                <div class="access-setting-copy">
                    <h3>Pengunjung</h3>
                    <p>
                        <strong>Buka:</strong> mahasiswa dapat mengisi formulir daftar kunjungan di
                        <strong>/kunjungan</strong>.
                        <strong>Tutup:</strong> formulir kunjungan ditutup untuk semua mahasiswa;
                        halaman menampilkan bahwa akses sedang ditutup oleh petugas.
                    </p>
                </div>
                <label class="switch-access visitor-access-toggle" id="visitoraccesstoggle" title="Buka atau tutup formulir kunjungan publik">
                    <input type="checkbox" id="visitoraccessswitch" role="switch" @checked($visitorAccessOpen)>
                    <span class="switch-access-track"></span>
                    <span class="switch-access-label" id="visitoraccesslabel">{{ $visitorAccessOpen ? 'Buka' : 'Tutup' }}</span>
                </label>
            </div>

            <div class="access-setting-item" id="submissionaccessitem">
                <div class="access-setting-copy">
                    <h3>Penyerahan</h3>
                    <p>
                        <strong>Buka:</strong> mahasiswa dapat mengisi formulir penyerahan tugas akhir di
                        <strong>/serah</strong>, selama akses penyerahan akunnya juga terbuka di modul Pengguna.
                        <strong>Tutup:</strong> formulir penyerahan ditutup untuk semua mahasiswa, terlepas dari
                        sakelar per akun. Gunakan modul Pengguna jika hanya ingin menutup akses satu mahasiswa.
                    </p>
                </div>
                <label class="switch-access visitor-access-toggle" id="submissionaccesstoggle" title="Buka atau tutup formulir penyerahan publik">
                    <input type="checkbox" id="submissionaccessswitch" role="switch" @checked($submissionAccessOpen)>
                    <span class="switch-access-track"></span>
                    <span class="switch-access-label" id="submissionaccesslabel">{{ $submissionAccessOpen ? 'Buka' : 'Tutup' }}</span>
                </label>
            </div>

            <div class="access-setting-item" id="barcodeaccessitem">
                <div class="access-setting-copy">
                    <h3>Kode Batang</h3>
                    <p>
                        <strong>Buka:</strong> mahasiswa dapat memakai halaman pindai kode batang publik
                        <strong>/pindai</strong> tanpa masuk sebagai petugas.
                        <strong>Tutup:</strong> pemindaian kode batang publik ditutup; mahasiswa melihat
                        bahwa akses pindai sedang ditutup oleh petugas.
                    </p>
                </div>
                <label class="switch-access visitor-access-toggle" id="barcodeaccesstoggle" title="Buka atau tutup pemindaian kode batang publik">
                    <input type="checkbox" id="barcodeaccessswitch" role="switch" @checked($barcodeAccessOpen)>
                    <span class="switch-access-track"></span>
                    <span class="switch-access-label" id="barcodeaccesslabel">{{ $barcodeAccessOpen ? 'Buka' : 'Tutup' }}</span>
                </label>
            </div>

        </div>

    </div>

    {{-- Bagian: lokasi berkas --}}
    <form id="settingform" class="profile-form" novalidate data-can-edit="{{ $canEdit ? '1' : '0' }}">

        <div class="form-section">

            <div class="section-title">
                <span class="section-badge">
                    <i class="fas fa-inbox"></i>
                </span>
                LOKASI BERKAS
            </div>
            
            <div class="info-box">
                <i class="fas fa-circle-info"></i>
                <span>
                    Lokasi ini dipakai secara menyeluruh untuk mengunggah dan mengunduh
                    <strong>berkas tugas akhir</strong> serta <strong>lembar persetujuan</strong>
                    dari modul Tugas Akhir, Penyerahan, dan Impor Tugas Akhir
                    (petugas, koordinator, dan mahasiswa). Pilih folder pada penyimpanan
                    internal atau eksternal yang terhubung, misalnya di lokasi D:.
                    Saat lokasi dipilih atau diganti, folder induk
                    <strong>ARSIP TUGAS AKHIR</strong> dibuat atau dipindahkan ke lokasi itu
                    dan disusun menurut tugas akhir yang tersedia di basis data,
                    bukan seluruh kombinasi katalog:
                    <strong>jenis tugas akhir</strong>
                    (misalnya Skripsi (S1), Tesis (S2), Disertasi (S3), atau Proyek Akhir (S1)),
                    <strong>tahun tugas akhir</strong>,
                    <strong>rumpun ilmu</strong>
                    (misalnya Rumpun Ilmu Formal atau Rumpun Ilmu Alam),
                    lalu <strong>program studi</strong>
                    (misalnya Ilmu Komputer, Matematika, Fisika, atau Biologi).
                    Tugas akhir dari rumpun yang sama disatukan dalam satu folder rumpun
                    di depan folder program studi. Folder yang namanya sama digabung dan tidak digandakan.
                    Folder <strong>BERKAS TUGAS AKHIR</strong> atau
                    <strong>LEMBAR PERSETUJUAN</strong> hanya dibuat jika ada berkas di dalamnya.
                    Folder kosong tidak disimpan.
                    Unggahan dari modul Penyerahan, Impor Tugas Akhir, dan Tugas Akhir
                    memakai susunan yang sama.
                    Saat lokasi disimpan atau diganti, progres pemindahan folder dan berkas
                    tampil secara langsung sampai proses selesai.
                    Nama berkas tetap <strong>nama lengkap_NIM</strong>.
                    Kosongkan pilihan atau pilih bawaan untuk menyimpan di folder aplikasi.
                </span>
            </div>

            <div class="storage-status-row">
                <span @class(['storage-badge', 'storage-badge--default' => $usingDefault, 'storage-badge--custom' => ! $usingDefault])>
                    {{ $usingDefault ? 'Bawaan aplikasi' : 'Folder khusus' }}
                </span>
                <span @class(['storage-badge', 'storage-badge--ready' => $storageReady, 'storage-badge--error' => ! $storageReady])>
                    {{ $storageReady ? 'Siap ditulisi' : 'Tidak dapat ditulisi' }}
                </span>
            </div>

            <div class="form-group full">
                <label for="settingstoragepath">Folder penyimpanan</label>
                <div class="storage-path-row">
                    <input
                        type="text"
                        id="settingstoragepath"
                        name="storage_path"
                        value="{{ $usingDefault ? '' : $storagePath }}"
                        placeholder="{{ $defaultPath }}"
                        autocomplete="off"
                        spellcheck="false"
                        @disabled(! $canEdit)
                    >
                    @if ($canEdit)
                        <button type="button" class="btn btn-primary" id="buttonpickstorage">
                            <i class="fas fa-folder-open"></i>
                            Pilih Folder
                        </button>
                    @endif
                </div>
                <p class="storage-path-hint" id="settingstoragehint">
                    Folder aktif:
                    <strong id="settingresolvedpath">{{ $resolvedPath }}</strong>
                </p>
            </div>

            @if ($canEdit && $shortcuts !== [])
                <div class="storage-shortcut-wrap">
                    <span class="storage-shortcut-label">Pintasan</span>
                    <div class="storage-shortcut-list" id="storageshortcuts">
                        @foreach ($shortcuts as $shortcut)
                            <button
                                type="button"
                                class="storage-shortcut"
                                data-path="{{ $shortcut['path'] }}"
                                data-kind="{{ $shortcut['kind'] }}"
                            >
                                <i class="fas {{ match ($shortcut['kind']) {
                                    'computer' => 'fa-computer',
                                    'default' => 'fa-box-archive',
                                    'drive' => 'fa-hard-drive',
                                    'removable' => 'fa-usb',
                                    'network' => 'fa-server',
                                    default => 'fa-folder',
                                } }}"></i>
                                {{ $shortcut['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($canEdit)
                <div class="profile-actions storage-actions">
                    <button type="button" class="btn btn-default" id="buttonresetstorage">
                        <i class="fas fa-rotate-left"></i>
                        Kembalikan Default
                    </button>
                    <button type="submit" class="btn btn-theme" id="buttonsetting">
                        <i class="fas fa-circle-check"></i>
                        Simpan Pengaturan
                    </button>
                </div>
            @else
                <div class="info-box">
                    <i class="fas fa-lock"></i>
                    Hanya Petugas yang dapat mengubah lokasi penyimpanan. Koordinator dapat melihat folder yang sedang dipakai.
                </div>
            @endif

        </div>

    </form>

</div>

@if ($canEdit)
{{-- Bagian: pemilih folder --}}
<div class="modal-overlay" id="storagepickermodal">
    <div class="card-modal card-modal--storage">
        <div class="modal-header">
            <div class="modal-title-wrap">
                <div class="modal-icon">
                    <i class="fas fa-folder-tree"></i>
                </div>
                <div>
                    <h2>Pilih Folder</h2>
                    <p>Pilih folder di drive internal atau eksternal yang terhubung ke komputer ini.</p>
                </div>
            </div>
            <button type="button" class="modal-close" id="storagepickerclose">
                <i class="fas fa-xmark"></i>
            </button>
        </div>

        <div class="storage-picker-body">
            <div class="storage-picker-toolbar">
                <button type="button" class="btn btn-default storage-picker-nav" id="storagepickercomputer" title="Semua penyimpanan yang terhubung">
                    <i class="fas fa-computer"></i>
                </button>
                <button type="button" class="btn btn-default storage-picker-nav" id="storagepickerup" title="Folder induk">
                    <i class="fas fa-arrow-up"></i>
                </button>
                <input type="text" id="storagepickerpath" spellcheck="false" autocomplete="off" placeholder="D:\ atau C:\Users\Public\Documents">
                <button type="button" class="btn btn-primary" id="storagepickergo">
                    Buka
                </button>
            </div>

            <div class="storage-picker-layout">
                <aside class="storage-picker-shortcuts" id="storagepickershortcuts"></aside>
                <div class="storage-picker-list-wrap">
                    <div class="storage-picker-status" id="storagepickerstatus"></div>
                    <ul class="storage-picker-list" id="storagepickerlist"></ul>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" id="storagepickercancel">
                Batal
            </button>
            <button type="button" class="btn-modal-save" id="storagepickeruse">
                <i class="fas fa-circle-check"></i>
                Gunakan Folder Ini
            </button>
        </div>
    </div>
</div>
@endif
@endsection
