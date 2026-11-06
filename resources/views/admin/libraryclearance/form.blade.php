<div class="modal-overlay" id="libraryclearancemodal">

    <div class="card-modal">

        <div class="modal-header">

            <div class="modal-title-wrap">

                <div class="modal-icon">
                    <i class="fas fa-file-circle-check"></i>
                </div>

                <div>
                    <h2>Formulir Bebas Pustaka</h2>
                    <p>
                        Lengkapi data bebas pustaka dan tautkan ke tugas akhir.
                    </p>
                </div>

            </div>

            <button class="modal-close modal-exit">
                <i class="fas fa-xmark"></i>
            </button>

        </div>

        <form id="libraryclearanceform">

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

                            <input type="hidden" id="libraryclearancefinalprojectid" name="libraryclearancefinalprojectid">
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

                            <input type="hidden" id="libraryclearancemembership" name="libraryclearancemembership">
                        </div>
                    </div>

                </div>

            </div>

            <!-- DATA BEBAS PUSTAKA -->

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-file-circle-check"></i>
                    </span>

                    DATA BEBAS PUSTAKA

                </div>

                <div class="form-grid">

                    <div class="form-group">
                        <label class="required-label">Email</label>
                        <input type="email" id="libraryclearanceemail" name="libraryclearanceemail" placeholder="Email">
                    </div>

                    <div class="form-group">
                        <label class="required-label">Nomor Telepon</label>
                        <input type="text" id="libraryclearancephone" name="libraryclearancephone" placeholder="Nomor Telepon">
                    </div>

                    <div class="form-group">
                        <label class="required-label">Tahun Lulus</label>
                        <input type="text" id="libraryclearancegraduationyear" name="libraryclearancegraduationyear" placeholder="Tahun Lulus" readonly>
                    </div>

                    <div class="form-group">
                        <label class="required-label">Semester Lulus</label>
                        <input type="text" id="libraryclearancesemester" name="libraryclearancesemester" placeholder="Contoh: 123" inputmode="numeric">
                    </div>

                </div>

            </div>

            <div class="info-box">

                <i class="fas fa-circle-info"></i>

                Bebas pustaka ditautkan ke tugas akhir. NIM, nama, jurusan, program studi, dan jenjang diambil dari data tugas akhir.

            </div>

            <div class="modal-footer">

                <button type="button" class="btn-modal-cancel modal-exit">
                    Batal
                </button>

                <button type="submit" class="btn-modal-save" id="buttonlibraryclearance" data-id='{"id":null}'>

                    <i class="fas fa-circle-check"></i>

                    Simpan

                </button>

            </div>

        </form>

    </div>

</div>
