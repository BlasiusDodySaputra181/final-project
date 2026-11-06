<div class="modal-overlay" id="bookgrantmodal">

    <div class="card-modal">

        <div class="modal-header">

            <div class="modal-title-wrap">

                <div class="modal-icon">
                    <i class="fas fa-book"></i>
                </div>

                <div>
                    <h2>Formulir Hibah Buku</h2>
                    <p>
                        Lengkapi data hibah buku dan tautkan ke tugas akhir.
                    </p>
                </div>

            </div>

            <button class="modal-close modal-exit">
                <i class="fas fa-xmark"></i>
            </button>

        </div>

        <form id="bookgrantform">

            <!-- DATA TUGAS AKHIR -->

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-graduation-cap"></i>
                    </span>

                    DATA TUGAS AKHIR

                </div>

                <div class="form-grid">

                    <div class="form-group">
                        <label class="required-label">Tugas Akhir</label>

                        <div class="custom-select" data-remote-url="{{ route('options-finalproject') }}">
                            <div class="custom-select-trigger">
                                <span>Pilih Tugas Akhir</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>

                            <div class="custom-select-dropdown"></div>

                            <input type="hidden" id="bookgrantfinalprojectid" name="bookgrantfinalprojectid">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="required-label">Terdaftar Keanggotaan</label>

                        <div class="custom-select">
                            <div class="custom-select-trigger">
                                <span>Pilih Status Keanggotaan</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>

                            <div class="custom-select-dropdown">
                                <div class="custom-option" data-id="Terdaftar" data-value="Terdaftar">
                                    Terdaftar
                                </div>
                                <div class="custom-option" data-id="Belum Terdaftar" data-value="Belum Terdaftar">
                                    Belum Terdaftar
                                </div>
                            </div>

                            <input type="hidden" id="bookgrantmembership" name="bookgrantmembership">
                        </div>
                    </div>

                </div>

            </div>

            <!-- DATA BUKU -->

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-book"></i>
                    </span>

                    DATA BUKU

                </div>

                <div class="form-group">
                    <label class="required-label">Judul Buku</label>
                    <textarea rows="5" id="bookgranttitle" name="bookgranttitle" placeholder="Judul Buku"></textarea>
                </div>

                <div class="form-grid">

                    <div class="form-group">
                        <label class="required-label">Tahun Buku</label>
                        <input type="text" id="bookgrantyear" name="bookgrantyear" placeholder="Tahun Buku" readonly>
                    </div>

                    <div class="form-group">
                        <label class="required-label">Pengarang</label>
                        <input type="text" id="bookgrantauthor" name="bookgrantauthor" placeholder="Nama Pengarang">
                    </div>

                </div>

            </div>

            <div class="info-box">

                <i class="fas fa-circle-info"></i>

                Hibah buku ditautkan ke tugas akhir. Jurusan/rumpun, program studi, dan jenjang diambil dari data tugas akhir.

            </div>

            <div class="modal-footer">

                <button type="button" class="btn-modal-cancel modal-exit">
                    Batal
                </button>

                <button type="submit" class="btn-modal-save" id="buttonbookgrant" data-id='{"id":null}'>

                    <i class="fas fa-circle-check"></i>

                    Simpan

                </button>

            </div>

        </form>

    </div>

</div>
