<div class="modal-overlay" id="barcodemodal">

    <div class="card-modal">

        <div class="modal-header">

            <div class="modal-title-wrap">

                <div class="modal-icon">
                    <i class="fas fa-barcode"></i>
                </div>

                <div>
                    <h2>Formulir Kode Batang</h2>
                    <p>
                        Lengkapi data kode batang dan tautkan ke tugas akhir.
                    </p>
                </div>

            </div>

            <button class="modal-close modal-exit">
                <i class="fas fa-xmark"></i>
            </button>

        </div>

        <form id="barcodeform">

            <!-- DATA TUGAS AKHIR -->

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-graduation-cap"></i>
                    </span>

                    DATA TUGAS AKHIR

                </div>

                <div class="form-grid">

                    <div class="form-group full">
                        <label class="required-label">Tugas Akhir</label>

                        <div class="custom-select" data-remote-url="{{ route('options-finalproject') }}">
                            <div class="custom-select-trigger">
                                <span>Pilih Tugas Akhir</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>

                            <div class="custom-select-dropdown"></div>

                            <input type="hidden" id="barcodefinalprojectid" name="barcodefinalprojectid">
                        </div>
                    </div>

                </div>

            </div>

            <!-- DATA KODE BATANG -->

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-barcode"></i>
                    </span>

                    DATA KODE BATANG

                </div>

                <div class="form-grid">

                    <div class="form-group">
                        <label class="required-label">Label Kode Batang</label>
                        <input type="text" id="barcodelabel" name="barcodelabel" placeholder="Label kode batang">
                    </div>

                    <div class="form-group">
                        <label class="required-label">Kode Batang</label>
                        <input type="text" id="barcodecode" name="barcodecode" placeholder="Kode batang" autocomplete="off">
                    </div>

                </div>

            </div>

            <div class="info-box">

                <i class="fas fa-circle-info"></i>

                Kode batang harus unik di basis data. NIM, nama, judul, program studi, jenjang, tahun tugas akhir, dan tanggal penyerahan diambil dari tugas akhir yang ditautkan. Tahun lulus serta semester lulus diambil dari bebas pustaka terkait.

            </div>

            <div class="modal-footer">

                <button type="button" class="btn-modal-cancel modal-exit">
                    Batal
                </button>

                <button type="submit" class="btn-modal-save" id="buttonbarcode" data-id='{"id":null}'>

                    <i class="fas fa-circle-check"></i>

                    Simpan

                </button>

            </div>

        </form>

    </div>

</div>
