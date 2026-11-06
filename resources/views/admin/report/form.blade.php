<div class="modal-overlay" id="reportmodal">

    <div class="card-modal">

        <div class="modal-header">

            <div class="modal-title-wrap">

                <div class="modal-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>

                <div>
                    <h2>Formulir Laporan</h2>
                    <p>
                        Lengkapi data laporan dan tautkan ke tugas akhir.
                    </p>
                </div>

            </div>

            <button class="modal-close modal-exit">
                <i class="fas fa-xmark"></i>
            </button>

        </div>

        <form id="reportform">

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

                        <input type="hidden" id="reportfinalprojectid" name="reportfinalprojectid">
                    </div>
                </div>

            </div>

            <!-- DATA LAPORAN -->

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-clipboard-check"></i>
                    </span>

                    DATA LAPORAN

                </div>

                <div class="form-group">
                    <label class="required-label">Keterangan</label>
                    <textarea rows="5" id="reportdescription" name="reportdescription" placeholder="Keterangan laporan"></textarea>
                </div>

            </div>

            <div class="info-box">

                <i class="fas fa-circle-info"></i>

                Data akademik, kode batang, hibah buku, bebas pustaka, batas waktu, dan keterangan arsip diambil otomatis dari relasi tugas akhir. Penyunting laporan diisi otomatis dari pengguna yang sedang masuk.

            </div>

            <div class="modal-footer">

                <button type="button" class="btn-modal-cancel modal-exit">
                    Batal
                </button>

                <button type="submit" class="btn-modal-save" id="buttonreport" data-id='{"id":null}'>

                    <i class="fas fa-circle-check"></i>

                    Simpan

                </button>

            </div>

        </form>

    </div>

</div>
