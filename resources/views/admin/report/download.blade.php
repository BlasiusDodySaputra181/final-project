<div class="modal-overlay" id="reportdownloadmodal">

    <div class="card-modal">

        <div class="modal-header">

            <div class="modal-title-wrap">

                <div class="modal-icon">
                    <i class="fas fa-download"></i>
                </div>

                <div>
                    <h2>Unduh Laporan Tahun TA</h2>
                    <p>
                        Pilih tahun tugas akhir untuk membuka laporan di tab baru.
                    </p>
                </div>

            </div>

            <button class="modal-close modal-exit">
                <i class="fas fa-xmark"></i>
            </button>

        </div>

        <form id="reportdownloadform">

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-calendar"></i>
                    </span>

                    TAHUN TUGAS AKHIR

                </div>

                <div class="form-group">
                    <label class="required-label">Tahun Tugas Akhir</label>

                    <div class="custom-select">
                        <div class="custom-select-trigger">
                            <span>Pilih Tahun Tugas Akhir</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>

                        <div class="custom-select-dropdown" id="reportdownloadyearoptions">
                        </div>

                        <input type="hidden" id="reportdownloadfinalprojectyear" name="reportdownloadfinalprojectyear">
                    </div>
                </div>

            </div>

            <div class="info-box">

                <i class="fas fa-circle-info"></i>

                Data diambil dari tabel laporan. Hanya baris dengan tahun tugas akhir yang dipilih yang ditampilkan.

            </div>

            <div class="modal-footer">

                <button type="button" class="btn-modal-cancel modal-exit">
                    Batal
                </button>

                <button type="submit" class="btn-modal-save" id="buttonreportdownload">

                    <i class="fas fa-up-right-from-square"></i>

                    Buka Laporan

                </button>

            </div>

        </form>

    </div>

</div>
