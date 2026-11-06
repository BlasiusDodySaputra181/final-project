@if ($membership ?? null)
    <section class="proof-section">
        <div class="proof-section-title">
            <i class="fas fa-id-card" aria-hidden="true"></i>
            <span>Keanggotaan</span>
        </div>

        <article class="proof-panel">
            <div class="proof-panel-head">
                <div class="proof-panel-head-text">
                    <span class="proof-panel-kicker">Status keanggotaan</span>
                    <h3 class="proof-panel-title">Data Anggota Perpustakaan</h3>
                </div>
                <span class="proof-badge proof-badge--success">
                    <i class="fas fa-circle-check" aria-hidden="true"></i>
                    Terdaftar
                </span>
            </div>

            <div class="proof-meta-grid">
                <div class="proof-meta-item">
                    <div class="proof-meta-label">NIM</div>
                    <div class="proof-meta-value">{{ $membership->user?->identity_number ?: ($student?->identity_number ?: '-') }}</div>
                </div>
                <div class="proof-meta-item">
                    <div class="proof-meta-label">Nama Mahasiswa</div>
                    <div class="proof-meta-value">{{ $membership->user?->name ?: ($student?->name ?: '-') }}</div>
                </div>
                <div class="proof-meta-item">
                    <div class="proof-meta-label">Rumpun Ilmu</div>
                    <div class="proof-meta-value">{{ $membership->studyProgram?->cluster ?: '-' }}</div>
                </div>
                <div class="proof-meta-item">
                    <div class="proof-meta-label">Program Studi</div>
                    <div class="proof-meta-value">{{ $membership->studyProgram?->study_program ?: '-' }}</div>
                </div>
                <div class="proof-meta-item">
                    <div class="proof-meta-label">Jenis Kelamin</div>
                    <div class="proof-meta-value">{{ $membership->gender ?: '-' }}</div>
                </div>
                <div class="proof-meta-item">
                    <div class="proof-meta-label">Tanggal Lahir</div>
                    <div class="proof-meta-value">{{ $membership->formattedDateOfBirth() ?: '-' }}</div>
                </div>
                <div class="proof-meta-item full">
                    <div class="proof-meta-label">Tempat Lahir</div>
                    <div class="proof-meta-value">{{ $membership->place_of_birth ?: '-' }}</div>
                </div>
                <div class="proof-meta-item full">
                    <div class="proof-meta-label">Alamat Asal</div>
                    <div class="proof-meta-value">{{ $membership->origin_address ?: '-' }}</div>
                </div>
                <div class="proof-meta-item full">
                    <div class="proof-meta-label">Alamat Sekarang</div>
                    <div class="proof-meta-value">{{ $membership->current_address ?: '-' }}</div>
                </div>
                <div class="proof-meta-item">
                    <div class="proof-meta-label">Hp/Telpon</div>
                    <div class="proof-meta-value">{{ $membership->relatedPhoneNumber() ?: '-' }}</div>
                </div>
                <div class="proof-meta-item">
                    <div class="proof-meta-label">E-mail</div>
                    <div class="proof-meta-value">{{ $membership->user?->email ?: ($student?->email ?: '-') }}</div>
                </div>
            </div>

            <div class="proof-panel-actions">
                <a
                    href="{{ route('letter-public-membership', $membership->id) }}"
                    class="proof-download-btn"
                    data-proof-letter
                    data-membership="Terdaftar"
                    target="_blank"
                    rel="noopener"
                >
                    <i class="fas fa-file-arrow-down" aria-hidden="true"></i>
                    <span>Unduh Surat Keanggotaan</span>
                </a>
            </div>
        </article>
    </section>
@else
    <section class="proof-section">
        <div class="proof-section-title">
            <i class="fas fa-id-card" aria-hidden="true"></i>
            <span>Keanggotaan</span>
        </div>

        <article class="proof-panel">
            <div class="proof-panel-head">
                <div class="proof-panel-head-text">
                    <span class="proof-panel-kicker">Status keanggotaan</span>
                    <h3 class="proof-panel-title">Data Anggota Perpustakaan</h3>
                </div>
                <span class="proof-badge proof-badge--muted">
                    <i class="fas fa-circle-minus" aria-hidden="true"></i>
                    Belum terdaftar
                </span>
            </div>

            <div class="proof-empty">
                Data keanggotaan belum diisi.
                <a href="{{ route('page-public-membership') }}" class="proof-empty-link">Daftarkan keanggotaan</a>
                agar data anggota tampil pada bukti ini.
            </div>
        </article>
    </section>
@endif
