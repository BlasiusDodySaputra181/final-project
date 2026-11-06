<div class="modal-overlay" id="importmodal">

    <div class="card-modal">

        <div class="modal-header">

            <div class="modal-title-wrap">

                <div class="modal-icon">
                    <i class="fas fa-file-import"></i>
                </div>

                <div>
                    <h2>Formulir Impor</h2>
                    <p>
                        Periksa dan perbaiki data tugas akhir hasil baca sebelum disimpan.
                    </p>
                </div>

            </div>

            <button class="modal-close modal-exit">
                <i class="fas fa-xmark"></i>
            </button>

        </div>

        <form id="importform" enctype="multipart/form-data">

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
                        <input type="text" id="importnim" name="importnim" placeholder="1313xxxxxx">
                    </div>

                    <div class="form-group">
                        <label class="required-label">Nama Mahasiswa</label>
                        <input type="text" id="importstudentsname" name="importstudentsname" placeholder="Nama Lengkap">
                    </div>

                    <div class="form-group">
                        <label class="required-label">Tahun Tugas Akhir</label>
                        <input type="text" id="importfinalyearproject" name="importfinalyearproject" placeholder="Pilih Tahun" readonly>
                    </div>

                    <div class="form-group">
                        <label class="required-label">Tanggal Penyerahan</label>
                        <input type="text" id="importsubmissiondate" name="importsubmissiondate" placeholder="Pilih Tanggal" readonly>
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

                            <input type="hidden" id="importstudyprogramme" name="importstudyprogramme">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="required-label">Jenjang</label>

                        <div class="custom-select">
                            <div class="custom-select-trigger">
                                <span>Pilih Jenjang</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>

                            <div class="custom-select-dropdown">
                                @include('admin.partials.degree-level-options')
                            </div>

                            <input type="hidden" id="importdegreelevel" name="importdegreelevel">
                            <input type="hidden" id="importfinalprojecttypeid" data-sync="type-id" value="">
                        </div>
                    </div>

                </div>

                <div class="form-group">
                    <label class="required-label">Judul Tugas Akhir</label>

                    <textarea rows="5" id="importfinalprojecttitle" name="importfinalprojecttitle" placeholder="Judul Tugas Akhir"></textarea>
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

                        <label class="upload-label">
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

                            <input type="file" id="importfinalprojectfile" name="importfinalprojectfile" accept=".pdf">

                        </div>

                    </div>

                    <div>

                        <label class="upload-label">
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

                            <input type="file" id="importapprovalsheet" name="importapprovalsheet" accept=".pdf">

                        </div>

                    </div>

                </div>

            </div>

            <div class="info-box">

                <i class="fas fa-circle-info"></i>

                Perubahan ini memperbarui baris pratinjau. Berkas yang sudah terpilih tetap dipakai jika tidak diganti. Simpan permanen lewat tombol Simpan Impor.

            </div>

            <div class="modal-footer">

                <button type="button" class="btn-modal-cancel modal-exit">
                    Batal
                </button>

                <button type="submit" class="btn-modal-save" id="buttonimport" data-id='{"id":null}'>

                    <i class="fas fa-circle-check"></i>

                    Simpan

                </button>

            </div>

        </form>

    </div>

</div>
