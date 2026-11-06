<section class="slide active-slide" data-step="1">
    <div class="content">
        <div class="section">
            {{-- Bagian: data akademik --}}
            <div class="section-title">
                <i class="fas fa-graduation-cap"></i> Akademik Mahasiswa
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="nim" class="required-label">NIM</label>
                    <input
                        type="text"
                        id="nim"
                        name="nim"
                        class="is-account-locked"
                        value="{{ $student?->identity_number ?? '' }}"
                        placeholder="1313*******"
                        autocomplete="off"
                        readonly
                        aria-readonly="true"
                        required
                    >
                    <small class="field-hint">Diisi otomatis dari akun yang sedang masuk dan tidak dapat diubah.</small>
                    @error('nim')
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="student_name" class="required-label">Nama Mahasiswa</label>
                    <input
                        type="text"
                        id="student_name"
                        name="student_name"
                        class="is-account-locked"
                        value="{{ $student?->name ?? '' }}"
                        placeholder="Nama Lengkap"
                        autocomplete="name"
                        readonly
                        aria-readonly="true"
                        required
                    >
                    <small class="field-hint">Diisi otomatis dari akun yang sedang masuk dan tidak dapat diubah.</small>
                    @error('student_name')
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                @php
                    $selectedProgram = $studyPrograms->firstWhere('study_program', old('program_study'));
                    $selectedProgramLabel = $selectedProgram
                        ? trim(($selectedProgram->cluster ? $selectedProgram->cluster.' — ' : '').$selectedProgram->study_program)
                        : null;

                    $selectedType = $finalProjectTypes->firstWhere('id', (int) old('final_project_type_id'))
                        ?? $finalProjectTypes->firstWhere('level', old('degree_level'));
                    $selectedTypeLabel = $selectedType
                        ? trim($selectedType->level.($selectedType->type ? ' — '.$selectedType->type : ''))
                        : null;
                @endphp

                <div class="form-group">
                    <label class="required-label">Program Studi</label>
                    <div
                        class="custom-select searchable @error('program_study') is-invalid @enderror"
                        id="program_study_select"
                        data-searchable="true"
                    >
                        <div class="custom-select-trigger" tabindex="0" role="button" aria-haspopup="listbox">
                            <span @class(['contain' => $selectedProgramLabel])>
                                {{ $selectedProgramLabel ?: 'Cari / pilih program studi' }}
                            </span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="custom-select-dropdown" role="listbox">
                            <div class="custom-select-search">
                                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                                <input
                                    type="text"
                                    class="custom-select-search-input"
                                    placeholder="Cari jurusan atau program studi..."
                                    autocomplete="off"
                                    spellcheck="false"
                                >
                            </div>
                            <div class="custom-select-options" id="program_study_options">
                                @forelse ($studyPrograms as $studyProgram)
                                    @php
                                        $programLabel = trim(($studyProgram->cluster ? $studyProgram->cluster.' — ' : '').$studyProgram->study_program);
                                    @endphp
                                    <div
                                        class="custom-option @if (old('program_study') === $studyProgram->study_program) selected @endif"
                                        data-value="{{ $studyProgram->study_program }}"
                                        data-cluster="{{ $studyProgram->cluster }}"
                                        data-id="{{ $studyProgram->id }}"
                                        data-label="{{ $programLabel }}"
                                        role="option"
                                    >
                                        <span class="option-title">{{ $studyProgram->study_program }}</span>
                                        @if ($studyProgram->cluster)
                                            <span class="option-subtitle">{{ $studyProgram->cluster }}</span>
                                        @endif
                                    </div>
                                @empty
                                    <div class="custom-select-empty" data-static-empty="true">
                                        Belum ada data program studi
                                    </div>
                                @endforelse
                            </div>
                            <div class="custom-select-empty" hidden>Tidak ada opsi yang cocok</div>
                        </div>
                        <input type="hidden" id="program_study" name="program_study" value="{{ old('program_study') }}" required>
                    </div>
                    @error('program_study')
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="required-label">Jenjang</label>
                    <div
                        class="custom-select searchable @error('degree_level') is-invalid @enderror"
                        id="degree_level_select"
                        data-searchable="true"
                    >
                        <div class="custom-select-trigger" tabindex="0" role="button" aria-haspopup="listbox">
                            <span @class(['contain' => $selectedTypeLabel])>
                                {{ $selectedTypeLabel ?: 'Cari / pilih jenjang' }}
                            </span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="custom-select-dropdown" role="listbox">
                            <div class="custom-select-search">
                                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                                <input
                                    type="text"
                                    class="custom-select-search-input"
                                    placeholder="Cari jenjang atau jenis tugas akhir..."
                                    autocomplete="off"
                                    spellcheck="false"
                                >
                            </div>
                            <div class="custom-select-options">
                                @forelse ($finalProjectTypes as $finalProjectType)
                                    @php
                                        $typeLabel = trim($finalProjectType->level.($finalProjectType->type ? ' — '.$finalProjectType->type : ''));
                                        $isSelected = (int) old('final_project_type_id') === (int) $finalProjectType->id
                                            || (! old('final_project_type_id') && old('degree_level') === $finalProjectType->level);
                                    @endphp
                                    <div
                                        class="custom-option @if ($isSelected) selected @endif"
                                        data-value="{{ $finalProjectType->level }}"
                                        data-id="{{ $finalProjectType->id }}"
                                        data-label="{{ $typeLabel }}"
                                        data-type-id-target="final_project_type_id"
                                        role="option"
                                    >
                                        <span class="option-title">{{ $finalProjectType->level }}</span>
                                        @if ($finalProjectType->type)
                                            <span class="option-subtitle">{{ $finalProjectType->type }}</span>
                                        @endif
                                    </div>
                                @empty
                                    <div class="custom-select-empty" data-static-empty="true">
                                        Belum ada data jenjang
                                    </div>
                                @endforelse
                            </div>
                            <div class="custom-select-empty" hidden>Tidak ada opsi yang cocok</div>
                        </div>
                        <input type="hidden" id="degree_level" name="degree_level" value="{{ old('degree_level') }}" required>
                        <input type="hidden" id="final_project_type_id" name="final_project_type_id" value="{{ old('final_project_type_id') }}">
                    </div>
                    @error('degree_level')
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @enderror
                    @error('final_project_type_id')
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="submission_date_display" class="required-label">Tanggal Penyerahan</label>
                    <input
                        type="text"
                        id="submission_date_display"
                        class="date-picker"
                        value="{{ $submissionDateDisplay ?? now()->locale('id')->translatedFormat('j F Y') }}"
                        placeholder="Pilih Tanggal"
                        readonly
                        required
                    >
                    <input
                        type="hidden"
                        id="submission_date"
                        name="submission_date"
                        value="{{ $submissionDateStored ?? now()->toDateString() }}"
                    >
                    @error('submission_date')
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="semester_graduated" class="required-label">Semester Lulus</label>
                    <input
                        type="number"
                        id="semester_graduated"
                        name="semester_graduated"
                        value="{{ old('semester_graduated') }}"
                        placeholder="Contoh: 8"
                        min="1"
                        max="20"
                        required
                    >
                    @error('semester_graduated')
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="graduation_year" class="required-label">Tahun Lulus</label>
                    <input
                        type="text"
                        id="graduation_year"
                        name="graduation_year"
                        class="years-picker"
                        value="{{ old('graduation_year') }}"
                        placeholder="Pilih Tahun"
                        readonly
                        required
                    >
                    @error('graduation_year')
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="final_project_year" class="required-label">Tahun Tugas Akhir</label>
                    <input
                        type="text"
                        id="final_project_year"
                        name="final_project_year"
                        class="years-picker"
                        value="{{ old('final_project_year') }}"
                        placeholder="Pilih Tahun"
                        readonly
                        required
                    >
                    @error('final_project_year')
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group full">
                    <label for="final_project_title" class="required-label">Judul Tugas Akhir</label>
                    <textarea
                        id="final_project_title"
                        name="final_project_title"
                        placeholder="Judul Tugas Akhir"
                        required
                    >{{ old('final_project_title') }}</textarea>
                    @error('final_project_title')
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Bagian: dokumen digital --}}
            <div class="section-title section-title--spaced">
                <i class="fas fa-file-pdf"></i> Dokumen Digital
            </div>

            <div class="upload-grid">
                <div class="upload-area @error('final_project_file') is-invalid @enderror" data-upload="final_project_file">
                    <input
                        type="file"
                        id="final_project_file"
                        name="final_project_file"
                        class="upload-input"
                        accept=".pdf,application/pdf"
                        hidden
                        required
                    >
                    <div class="upload-icon"><i class="fas fa-book"></i></div>
                    <label class="required-label" for="final_project_file">Berkas Tugas Akhir</label>
                    <p>Format PDF</p>
                    <p class="file-name">Drag &amp; Drop PDF</p>
                    @error('final_project_file')
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="upload-area @error('approval_sheet') is-invalid @enderror" data-upload="approval_sheet">
                    <input
                        type="file"
                        id="approval_sheet"
                        name="approval_sheet"
                        class="upload-input"
                        accept=".pdf,application/pdf"
                        hidden
                        required
                    >
                    <div class="upload-icon"><i class="fas fa-file-lines"></i></div>
                    <label class="required-label" for="approval_sheet">Lembar Persetujuan</label>
                    <p>Format PDF</p>
                    <p class="file-name">Drag &amp; Drop PDF</p>
                    @error('approval_sheet')
                        <div class="field-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-primary next-btn">
                    Lanjut Tahap Berikutnya <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        @include('student.partials.submission-summary', [
            'stage' => '1 / 3',
            'progress' => '33%',
            'status' => 'Tugas Akhir',
            'info' => 'Pastikan dokumen yang diunggah berformat PDF.',
        ])
    </div>

    <div class="footer">
        © {{ date('Y') }} Perpustakaan FMIPA Universitas Negeri Jakarta
    </div>
</section>
