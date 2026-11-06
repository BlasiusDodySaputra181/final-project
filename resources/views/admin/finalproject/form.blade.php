<div class="modal-overlay" id="finalprojectmodal">

    <div class="card-modal">

        <div class="modal-header">

            <div class="modal-title-wrap">

                <div class="modal-icon">
                    <i class="fas fa-book"></i>
                </div>

                <div>
                    <h2 id="finalprojectformtitle">Formulir Tugas Akhir</h2>
                    <p id="finalprojectformsubtitle">
                        Lengkapi data akademik dan unggah dokumen tugas akhir.
                    </p>
                </div>

            </div>

            <button class="modal-close modal-exit">
                <i class="fas fa-xmark"></i>
            </button>

        </div>

        <form id="finalprojectform" enctype="multipart/form-data">

            <!-- DATA AKADEMIK -->

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-graduation-cap"></i>
                    </span>

                    DATA AKADEMIK

                </div>

                <div class="form-grid">

                    <div class="form-group">
                        <label class="required-label">NIM</label>
                        <input type="text" id="nim" name="nim" placeholder="1313xxxxxx">
                    </div>

                    <div class="form-group">
                        <label class="required-label">Nama Mahasiswa</label>
                        <input type="text" id="studentsname" name="studentsname" placeholder="Nama Lengkap">
                    </div>

                    <div class="form-group">
                        <label class="required-label">Tahun Tugas Akhir</label>
                        <input type="text" id="finalyearproject" name="finalyearproject" placeholder="Pilih Tahun" readonly>
                    </div>

                    <div class="form-group">
                        <label class="required-label">Tanggal Penyerahan</label>
                        <input type="text" id="submissiondate" name="submissiondate" placeholder="Pilih Tanggal" readonly>
                    </div>

                </div>

                <div class="form-group">
                    <label class="required-label">Program Studi</label>

                    <div class="custom-select">
                        <div class="custom-select-trigger">
                            <span>Pilih Program Studi</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>

                        <div class="custom-select-dropdown">
                            @foreach ($studyPrograms as $studyProgram)
                                <div class="custom-option" data-id="{{ $studyProgram->id }}" data-value="{{ $studyProgram->study_program }}">
                                    {{ $studyProgram->study_program }}
                                </div>
                            @endforeach
                        </div>

                        <input type="hidden" id="studyprogramme" name="studyprogramme">
                    </div>
                </div>

                <div class="form-group">
                    <label class="required-label">Judul Tugas Akhir</label>

                    <textarea rows="5" id="finalprojecttitle" name="finalprojecttitle" placeholder="Judul Tugas Akhir"></textarea>
                </div>

            </div>

            <!-- DOKUMEN -->

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-paperclip"></i>
                    </span>

                    DOKUMEN DIGITAL

                </div>

                <div class="upload-grid">

                    <div>

                        <label class="upload-label required-label">
                            Berkas Tugas Akhir
                        </label>

                        <div class="upload-box">

                            <i class="fas fa-file-pdf"></i>

                            <h4>Klik atau Tarik PDF</h4>

                            <span class="upload-info">
                                Format PDF
                            </span>

                            <div class="file-name">
                                Belum ada file dipilih
                            </div>

                            <input type="file" id="finalprojectfile" name="finalprojectfile" accept=".pdf">

                        </div>

                    </div>

                    <div>

                        <label class="upload-label required-label">
                            Lembar Persetujuan
                        </label>

                        <div class="upload-box">

                            <i class="fas fa-file-pdf"></i>

                            <h4>Klik atau Tarik PDF</h4>

                            <span class="upload-info">
                                Format PDF
                            </span>

                            <div class="file-name">
                                Belum ada file dipilih
                            </div>

                            <input type="file" id="approvalsheet" name="approvalsheet" accept=".pdf">

                        </div>

                    </div>

                </div>

            </div>

            <div class="info-box">

                <i class="fas fa-circle-info"></i>

                Pastikan dokumen yang diunggah berformat PDF.

            </div>

            <div class="modal-footer">

                <button type="button" class="btn-modal-cancel modal-exit">
                    Batal
                </button>

                <button type="submit" class="btn-modal-save" id="buttonfinalproject">

                    <i class="fas fa-circle-check"></i>

                    Simpan

                </button>

            </div>

        </form>

    </div>

</div>
