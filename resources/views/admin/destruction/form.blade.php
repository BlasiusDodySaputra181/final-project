<div class="modal-overlay" id="destructionmodal">

    <div class="card-modal">

        <div class="modal-header">

            <div class="modal-title-wrap">

                <div class="modal-icon">
                    <i class="fas fa-boxes-packing"></i>
                </div>

                <div>
                    <h2>Formulir Pemusnahan</h2>
                    <p>
                        Lengkapi data pemusnahan untuk tugas akhir yang tidak berlaku.
                    </p>
                </div>

            </div>

            <button class="modal-close modal-exit">
                <i class="fas fa-xmark"></i>
            </button>

        </div>

        <form id="destructionform">

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-graduation-cap"></i>
                    </span>

                    DATA TUGAS AKHIR

                </div>

                <div class="form-group">
                    <label class="required-label">Tugas Akhir</label>

                    <div class="custom-select" data-remote-url="{{ route('options-finalproject') }}?expired=1">
                        <div class="custom-select-trigger">
                            <span>Pilih Tugas Akhir (Tidak Berlaku)</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>

                        <div class="custom-select-dropdown"></div>

                        <input type="hidden" id="destructionfinalprojectid" name="destructionfinalprojectid">
                    </div>
                </div>

            </div>

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-boxes-packing"></i>
                    </span>

                    DATA PEMUSNAHAN

                </div>

                <div class="form-group">
                    <label class="required-label">Keterangan</label>
                    <textarea rows="5" id="destructiondescription" name="destructiondescription" placeholder="Keterangan pemusnahan"></textarea>
                </div>

            </div>

            <div class="info-box">

                <i class="fas fa-circle-info"></i>

                Status awal selalu Belum Disetujui. Persetujuan hanya dilakukan Koordinator melalui centang di tabel pemusnahan.

            </div>

            <div class="modal-footer">

                <button type="button" class="btn-modal-cancel modal-exit">
                    Batal
                </button>

                <button type="submit" class="btn-modal-save" id="buttondestruction" data-id='{"id":null}'>

                    <i class="fas fa-circle-check"></i>

                    Simpan

                </button>

            </div>

        </form>

    </div>

</div>
