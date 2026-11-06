<div class="modal-overlay" id="finalprojecttypemodal">

    <div class="card-modal">

        <div class="modal-header">

            <div class="modal-title-wrap">

                <div class="modal-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>

                <div>
                    <h2>Formulir Jenis Tugas Akhir</h2>
                    <p>
                        Lengkapi data jenis tugas akhir.
                    </p>
                </div>

            </div>

            <button class="modal-close modal-exit">
                <i class="fas fa-xmark"></i>
            </button>

        </div>

        <form id="finalprojecttypeform">

            <!-- DATA JENIS TUGAS AKHIR -->

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-graduation-cap"></i>
                    </span>

                    DATA JENIS TUGAS AKHIR

                </div>

                <div class="form-grid">

                    <div class="form-group">
                        <label class="required-label">Jenjang</label>
                        <input type="text" id="finalprojecttypelevel" name="finalprojecttypelevel" placeholder="Contoh: S1, S2, S3">
                    </div>

                    <div class="form-group">
                        <label class="required-label">Jenis</label>
                        <input type="text" id="finalprojecttypename" name="finalprojecttypename" placeholder="Contoh: Skripsi, Tesis, Disertasi">
                    </div>

                </div>

            </div>

            <div class="info-box">

                <i class="fas fa-circle-info"></i>

                Jenis tugas akhir akan tampil sebagai menu opsional pada navigasi Tugas Akhir, dan jenjangnya dipakai saat menyimpan data.

            </div>

            <div class="modal-footer">

                <button type="button" class="btn-modal-cancel modal-exit">
                    Batal
                </button>

                <button type="submit" class="btn-modal-save" id="buttonfinalprojecttype" data-id='{"id":null}'>

                    <i class="fas fa-circle-check"></i>

                    Simpan

                </button>

            </div>

        </form>

    </div>

</div>
