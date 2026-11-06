<div class="modal-overlay" id="studyprogrammodal">

    <div class="card-modal">

        <div class="modal-header">

            <div class="modal-title-wrap">

                <div class="modal-icon">
                    <i class="fas fa-building-columns"></i>
                </div>

                <div>
                    <h2>Formulir Program Studi</h2>
                    <p>
                        Lengkapi data program studi.
                    </p>
                </div>

            </div>

            <button class="modal-close modal-exit">
                <i class="fas fa-xmark"></i>
            </button>

        </div>

        <form id="studyprogramform">

            <!-- DATA PROGRAM STUDI -->

            <div class="form-section">

                <div class="section-title">

                    <span class="section-badge">
                        <i class="fas fa-building-columns"></i>
                    </span>

                    DATA PROGRAM STUDI

                </div>

                <div class="form-grid">

                    <div class="form-group">
                        <label class="required-label">Program Studi</label>
                        <input type="text" id="studyprogramname" name="studyprogramname" placeholder="Nama Program Studi">
                    </div>

                    <div class="form-group">
                        <label class="required-label">Rumpun Ilmu</label>
                        <input type="text" id="studyprogramcluster" name="studyprogramcluster" placeholder="Nama Rumpun Ilmu">
                    </div>

                </div>

            </div>

            <div class="info-box">

                <i class="fas fa-circle-info"></i>

                Program studi akan tampil sebagai pilihan pada formulir tugas akhir.

            </div>

            <div class="modal-footer">

                <button type="button" class="btn-modal-cancel modal-exit">
                    Batal
                </button>

                <button type="submit" class="btn-modal-save" id="buttonstudyprogram" data-id='{"id":null}'>

                    <i class="fas fa-circle-check"></i>

                    Simpan

                </button>

            </div>

        </form>

    </div>

</div>
