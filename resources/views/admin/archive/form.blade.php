<div class="modal-overlay" id="archivemodal">

    <div class="card-modal">

        <div class="modal-header">

            <div class="modal-title-wrap">

                <div class="modal-icon">
                    <i class="fas fa-box-archive"></i>
                </div>

                <div>
                    <h2>Formulir Arsip</h2>
                    <p>
                        Lengkapi data arsip dan tautkan ke tugas akhir.
                    </p>
                </div>

            </div>

            <button class="modal-close modal-exit">
                <i class="fas fa-xmark"></i>
            </button>

        </div>

        <form id="archiveform">

            <!-- DATA TUGAS AKHIR -->

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-graduation-cap"></i>
                    </span>

                    DATA TUGAS AKHIR

                </div>

                <div class="form-group">
                    <label class="required-label">Tugas Akhir</label>

                    <div class="custom-select" data-remote-url="{{ route('options-finalproject') }}">
                        <div class="custom-select-trigger">
                            <span>Pilih Tugas Akhir</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>

                        <div class="custom-select-dropdown"></div>

                        <input type="hidden" id="archivefinalprojectid" name="archivefinalprojectid">
                    </div>
                </div>

            </div>

            <!-- DATA ARSIP -->

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-box-archive"></i>
                    </span>

                    DATA ARSIP

                </div>

                <div class="form-group">
                    <label class="required-label">Keterangan</label>
                    <textarea rows="5" id="archivedescription" name="archivedescription" placeholder="Keterangan arsip"></textarea>
                </div>

            </div>

            <div class="info-box">

                <i class="fas fa-circle-info"></i>

                NIM, nama, judul, program studi, jenjang, tahun, tanggal penyerahan, status, dan batas waktu diambil dari data tugas akhir yang ditautkan.

            </div>

            <div class="modal-footer">

                <button type="button" class="btn-modal-cancel modal-exit">
                    Batal
                </button>

                <button type="submit" class="btn-modal-save" id="buttonarchive" data-id='{"id":null}'>

                    <i class="fas fa-circle-check"></i>

                    Simpan

                </button>

            </div>

        </form>

    </div>

</div>
