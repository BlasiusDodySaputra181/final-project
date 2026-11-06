<div class="modal-overlay" id="archivedownloadmodal">

    <div class="card-modal">

        <div class="modal-header">

            <div class="modal-title-wrap">

                <div class="modal-icon">
                    <i class="fas fa-download"></i>
                </div>

                <div>
                    <h2>Unduh Arsip Prodi</h2>
                    <p>
                        Pilih program studi untuk membuka arsip di tab baru.
                    </p>
                </div>

            </div>

            <button class="modal-close modal-exit">
                <i class="fas fa-xmark"></i>
            </button>

        </div>

        <form id="archivedownloadform">

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-building-columns"></i>
                    </span>

                    PROGRAM STUDI

                </div>

                <div class="form-group">
                    <label class="required-label">Program Studi</label>

                    <div class="custom-select">
                        <div class="custom-select-trigger">
                            <span>Pilih Program Studi</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>

                        <div class="custom-select-dropdown" id="archivedownloadstudydroptions">
                        </div>

                        <input type="hidden" id="archivedownloadstudyprogram" name="archivedownloadstudyprogram">
                    </div>
                </div>

            </div>

            <div class="info-box">

                <i class="fas fa-circle-info"></i>

                Data diambil dari tabel arsip. Hanya baris dengan program studi yang dipilih yang ditampilkan.

            </div>

            <div class="modal-footer">

                <button type="button" class="btn-modal-cancel modal-exit">
                    Batal
                </button>

                <button type="submit" class="btn-modal-save" id="buttonarchivedownload">

                    <i class="fas fa-up-right-from-square"></i>

                    Buka Arsip

                </button>

            </div>

        </form>

    </div>

</div>
