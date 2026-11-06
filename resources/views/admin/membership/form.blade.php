<div class="modal-overlay" id="membershipmodal">

    <div class="card-modal">

        <div class="modal-header">

            <div class="modal-title-wrap">

                <div class="modal-icon">
                    <i class="fas fa-id-card"></i>
                </div>

                <div>
                    <h2>Formulir Keanggotaan</h2>
                    <p>
                        Lengkapi data keanggotaan perpustakaan mahasiswa.
                    </p>
                </div>

            </div>

            <button class="modal-close modal-exit">
                <i class="fas fa-xmark"></i>
            </button>

        </div>

        <form id="membershipform">

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-user"></i>
                    </span>

                    DATA MAHASISWA

                </div>

                <div class="form-grid">

                    <div class="form-group">
                        <label class="required-label">Mahasiswa</label>

                        <div class="custom-select" data-remote-url="{{ route('options-user') }}">
                            <div class="custom-select-trigger">
                                <span>Pilih Mahasiswa</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>

                            <div class="custom-select-dropdown"></div>

                            <input type="hidden" id="membershipuserid" name="membershipuserid">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="required-label">Program Studi</label>

                        <div class="custom-select">
                            <div class="custom-select-trigger">
                                <span>Pilih Program Studi</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>

                            <div class="custom-select-dropdown">
                                @foreach ($studyPrograms ?? [] as $studyProgram)
                                    <div class="custom-option" data-id="{{ $studyProgram->id }}" data-value="{{ $studyProgram->id }}">
                                        {{ $studyProgram->study_program }}
                                    </div>
                                @endforeach
                            </div>

                            <input type="hidden" id="membershipstudyprogramme" name="membershipstudyprogramme">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="required-label">Jenis Kelamin</label>

                        <div class="custom-select">
                            <div class="custom-select-trigger">
                                <span>Pilih Jenis Kelamin</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>

                            <div class="custom-select-dropdown">
                                <div class="custom-option" data-id="Laki-laki" data-value="Laki-laki">
                                    Laki-laki
                                </div>
                                <div class="custom-option" data-id="Perempuan" data-value="Perempuan">
                                    Perempuan
                                </div>
                            </div>

                            <input type="hidden" id="membershipgender" name="membershipgender">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="required-label">Tanggal Lahir</label>
                        <input type="text" id="membershipbirthdate" name="membershipbirthdate" placeholder="Pilih Tanggal" readonly>
                    </div>

                    <div class="form-group full">
                        <label class="required-label">Tempat Lahir</label>
                        <input type="text" id="membershipbirthplace" name="membershipbirthplace" placeholder="Contoh: Bekasi">
                    </div>

                </div>

            </div>

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-location-dot"></i>
                    </span>

                    DATA ALAMAT

                </div>

                <div class="form-grid">

                    <div class="form-group">
                        <label class="required-label">Alamat Asal</label>
                        <textarea rows="4" id="membershiporigin" name="membershiporigin" placeholder="Alamat asal"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="required-label">Alamat Sekarang</label>
                        <textarea rows="4" id="membershipcurrent" name="membershipcurrent" placeholder="Alamat sekarang"></textarea>
                    </div>

                </div>

            </div>

            <div class="info-box">

                <i class="fas fa-circle-info"></i>

                Setiap mahasiswa hanya memiliki satu data keanggotaan. Relasi ke bebas pustaka dan hibah buku akan menandai status Terdaftar Keanggotaan secara otomatis.

            </div>

            <div class="modal-footer">

                <button type="button" class="btn-modal-cancel modal-exit">
                    Batal
                </button>

                <button type="submit" class="btn-modal-save" id="buttonmembership" data-id='{"id":null}'>

                    <i class="fas fa-circle-check"></i>

                    Simpan

                </button>

            </div>

        </form>

    </div>

</div>
