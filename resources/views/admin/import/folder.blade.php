<div class="modal-overlay" id="foldermodal">

    <div class="card-modal">

        <div class="modal-header">

            <div class="modal-title-wrap">

                <div class="modal-icon">
                    <i class="fas fa-file-import"></i>
                </div>

                <div>
                    <h2>Formulir Folder</h2>
                    <p>
                        Unggah folder dokumen tugas akhir untuk diimpor.
                    </p>
                </div>

            </div>

            <button class="modal-close modal-exit">
                <i class="fas fa-xmark"></i>
            </button>

        </div>

        <form id="folderform" enctype="multipart/form-data">

            <!-- DOKUMEN -->

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-paperclip"></i>
                    </span>

                    DOKUMEN DIGITAL

                </div>

                <div class="form-group">

                    <div>

                        <label class="upload-label required-label">
                            Folder Tugas Akhir
                        </label>

                        <div class="upload-box">

                            <i class="fas fa-file-pdf"></i>

                            <h4>Klik atau Tarik Folder</h4>

                            <span class="upload-info">
                                Folder Maks. 1000 Berkas
                            </span>

                            <div class="file-name">
                                Belum ada folder dipilih
                            </div>

                            <input type="file" id="folderfile" name="folderfile" accept=".pdf" webkitdirectory directory multiple>

                        </div>

                    </div>

                </div>

            </div>

            <div class="info-box">

                <i class="fas fa-circle-info"></i>

                Pastikan folder tidak bertingkat. Proses ini memanggil AI secara daring dan tidak dapat dijalankan saat luring.

            </div>

            <div class="modal-footer">

                <button type="button" class="btn-modal-cancel modal-exit">
                    Batal
                </button>

                <button type="submit" class="btn-modal-save" id="buttonfolder">

                    <i class="fas fa-circle-check"></i>

                    Simpan

                </button>

            </div>

        </form>

    </div>

</div>
