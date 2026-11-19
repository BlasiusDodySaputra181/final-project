<div class="meta-section-title">
    <i class="fas fa-book"></i> Informasi Tugas Akhir
</div>

<div class="meta-grid">
    <div class="meta-item full">
        <div class="meta-label">Judul Tugas Akhir</div>
        <div class="meta-value">{{ $finalProject->final_project_title ?: '-' }}</div>
    </div>

    <div class="meta-item">
        <div class="meta-label">Tahun Tugas Akhir</div>
        <div class="meta-value">{{ $finalProject->final_project_year ?: '-' }}</div>
    </div>

    <div class="meta-item">
        <div class="meta-label">Tahun Lulus</div>
        <div class="meta-value">{{ $libraryClearance?->graduation_year ?: '-' }}</div>
    </div>

    <div class="meta-item">
        <div class="meta-label">Semester Lulus</div>
        <div class="meta-value">{{ $libraryClearance?->semester_graduated ?: '-' }}</div>
    </div>

    <div class="meta-item">
        <div class="meta-label">Status Dokumen</div>
        <div class="meta-value">
            <span @class([
                'meta-status',
                'meta-status--ok' => $documentAvailable,
                'meta-status--off' => ! $documentAvailable,
            ])>
                <i class="fas {{ $documentAvailable ? 'fa-circle-check' : 'fa-circle-xmark' }}"></i>
                {{ $documentStatus }}
            </span>
        </div>
    </div>

    <div class="meta-item full">
        <div class="meta-label">Lembar Persetujuan</div>
        <div class="meta-value">
            @if ($approvalSheetName)
                <div class="meta-approval">
                    <div>{{ $approvalSheetName }}</div>
                    <button
                        type="button"
                        class="meta-download-btn"
                        data-approval-preview
                        data-preview-url="{{ route('download-public-approvalsheet', $barcode->barcode) }}"
                    >
                        <i class="fas fa-file-arrow-down" aria-hidden="true"></i>
                        <span>Unduh Lembar Persetujuan</span>
                    </button>
                </div>
            @else
                Tidak Tersedia
            @endif
        </div>
    </div>

    <div class="meta-item full">
        <div class="meta-label">Kode Batang</div>
        <div class="meta-value">{{ $barcode->barcode }}</div>
    </div>
</div>
