<div class="meta-section-title">
    <i class="fas fa-user-graduate"></i> Informasi Mahasiswa
</div>

<div class="meta-grid">
    <div class="meta-item">
        <div class="meta-label">NIM</div>
        <div class="meta-value">{{ $finalProject->nim ?: '-' }}</div>
    </div>

    <div class="meta-item">
        <div class="meta-label">Nama Mahasiswa</div>
        <div class="meta-value">{{ $finalProject->student_name ?: '-' }}</div>
    </div>

    <div class="meta-item">
        <div class="meta-label">Program Studi</div>
        <div class="meta-value">
            {{ $finalProject->program_study ?: ($studyProgram?->study_program ?: '-') }}
        </div>
    </div>

    <div class="meta-item">
        <div class="meta-label">Jenjang</div>
        <div class="meta-value">{{ $finalProject->degree_level ?: '-' }}</div>
    </div>

    <div class="meta-item">
        <div class="meta-label">Jurusan / Rumpun</div>
        <div class="meta-value">{{ $studyProgram?->cluster ?: '-' }}</div>
    </div>

    <div class="meta-item">
        <div class="meta-label">No. HP</div>
        <div class="meta-value">{{ $libraryClearance?->phone_number ?: '-' }}</div>
    </div>

    <div class="meta-item full">
        <div class="meta-label">Email</div>
        <div class="meta-value">{{ $libraryClearance?->email ?: '-' }}</div>
    </div>
</div>
