<div class="modal-overlay" id="visitormodal">

    <div class="card-modal">

        <div class="modal-header">

            <div class="modal-title-wrap">

                <div class="modal-icon">
                    <i class="fas fa-users"></i>
                </div>

                <div>
                    <h2>Formulir Pengunjung</h2>
                    <p>
                        Lengkapi data pengunjung perpustakaan.
                    </p>
                </div>

            </div>

            <button class="modal-close modal-exit">
                <i class="fas fa-xmark"></i>
            </button>

        </div>

        <form id="visitorform">

            <!-- DATA PENGUNJUNG -->

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-users"></i>
                    </span>

                    DATA PENGUNJUNG

                </div>

                <div class="form-grid">

                    <div class="form-group">
                        <label class="required-label">NIM</label>
                        <input type="text" id="visitornim" name="visitornim" placeholder="1313xxxxxx">
                    </div>

                    <div class="form-group">
                        <label class="required-label">Nama Mahasiswa</label>
                        <input type="text" id="visitorstudentname" name="visitorstudentname" placeholder="Nama Mahasiswa">
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

                        <input type="hidden" id="visitorprogramstudy" name="visitorprogramstudy">
                    </div>
                </div>

                <div class="form-group">
                    <label class="required-label">Keperluan</label>
                    <textarea rows="5" id="visitorneeds" name="visitorneeds" placeholder="Keperluan kunjungan"></textarea>
                </div>

            </div>

            <div class="info-box">

                <i class="fas fa-circle-info"></i>

                Program studi diambil dari data program studi. Pengawasan diisi otomatis dari pengguna yang sedang masuk, atau melalui kotak centang di tabel.

            </div>

            <div class="modal-footer">

                <button type="button" class="btn-modal-cancel modal-exit">
                    Batal
                </button>

                <button type="submit" class="btn-modal-save" id="buttonvisitor" data-id='{"id":null}'>

                    <i class="fas fa-circle-check"></i>

                    Simpan

                </button>

            </div>

        </form>

    </div>

</div>
