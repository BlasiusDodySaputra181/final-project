<div class="modal-overlay" id="usermodal">

    <div class="card-modal">

        <div class="modal-header">

            <div class="modal-title-wrap">

                <div class="modal-icon">
                    <i class="fas fa-user-gear"></i>
                </div>

                <div>
                    <h2>Formulir Pengguna</h2>
                    <p>
                        Lengkapi data pengguna sistem.
                    </p>
                </div>

            </div>

            <button class="modal-close modal-exit">
                <i class="fas fa-xmark"></i>
            </button>

        </div>

        <form id="userform">

            <!-- DATA PENGGUNA -->

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-user-gear"></i>
                    </span>

                    DATA PENGGUNA

                </div>

                <div class="form-grid">

                    <div class="form-group">
                        <label class="required-label">NIP / NIM</label>
                        <input type="text" id="useridentitynumber" name="useridentitynumber" placeholder="NIP (petugas) atau NIM (mahasiswa)">
                    </div>

                    <div class="form-group">
                        <label class="required-label">Nama</label>
                        <input type="text" id="username" name="username" placeholder="Nama Lengkap">
                    </div>

                    <div class="form-group full">
                        <label class="required-label">Email</label>
                        <input type="email" id="useremail" name="useremail" placeholder="email@example.com">
                    </div>

                    <div class="form-group">
                        <label class="required-label" id="userpasswordlabel">Kata Sandi</label>
                        <input type="password" id="userpassword" name="userpassword" placeholder="Kata sandi">
                    </div>

                    <div class="form-group">
                        <label class="required-label">Peran</label>

                        <div class="custom-select">
                            <div class="custom-select-trigger">
                                <span>Pilih Peran</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>

                            <div class="custom-select-dropdown">
                                <div class="custom-option" data-id="Petugas" data-value="Petugas">
                                    Petugas
                                </div>
                                <div class="custom-option" data-id="Koordinator" data-value="Koordinator">
                                    Koordinator
                                </div>
                                <div class="custom-option" data-id="Mahasiswa" data-value="Mahasiswa">
                                    Mahasiswa
                                </div>
                            </div>

                            <input type="hidden" id="userrole" name="userrole">
                        </div>
                    </div>

                </div>

            </div>

            <div class="info-box">

                <i class="fas fa-circle-info"></i>

                Kata sandi dienkripsi secara otomatis. Saat mengubah data, biarkan kata sandi kosong jika tidak ingin diganti. Peran Mahasiswa hanya dapat ditambahkan oleh petugas dan dipakai untuk masuk ke layanan kunjungan, pencarian tugas akhir, pemindaian kode batang, penyerahan tugas akhir, serta bukti penyerahan. Akses penyerahan akun Mahasiswa selalu tertutup saat ditambah atau diubah; hanya petugas yang dapat membukanya dari tabel.

            </div>

            <div class="modal-footer">

                <button type="button" class="btn-modal-cancel modal-exit">
                    Batal
                </button>

                <button type="submit" class="btn-modal-save" id="buttonuser" data-id='{"id":null}'>

                    <i class="fas fa-circle-check"></i>

                    Simpan

                </button>

            </div>

        </form>

    </div>

</div>
