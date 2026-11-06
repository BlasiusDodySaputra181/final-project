<div class="modal-overlay" id="informationimportmodal">

    <div class="card-modal">

        <div class="modal-header">

            <div class="modal-title-wrap">

                <div class="modal-icon">
                    <i class="fa-brands fa-openai"></i>
                </div>

                <div>
                    <h2>Informasi Impor</h2>
                    <p>
                        Impor data dengan dukungan kecerdasan buatan.
                    </p>
                </div>

            </div>

            <button class="modal-close modal-exit">
                <i class="fas fa-xmark"></i>
            </button>

        </div>

        <!-- DATA AKADEMIK -->

        <div class="form-section">

            <div class="section-title">

                <span class="section-badge">
                    <i class="fa-solid fa-hexagon-nodes"></i>
                </span>

                KECERDASAN BUATAN

            </div>

            <div class="form-grid">

                <div class="item-info">
                    <div class="required-label label-info">Model</div>
                    <div class="value-info" id="importmodel"></div>
                </div>

                <div class="item-info">
                    <div class="required-label label-info">Temperatur</div>
                    <div class="value-info" id="importtemperature"></div>
                </div>
                       
                <div class="item-info">
                    <div class="required-label label-info">Waktu Proses Milidetik</div>
                    <div class="value-info" id="importprocessingtimems"></div>
                </div>

                <div class="item-info">
                    <div class="required-label label-info">Prompt Token</div>
                    <div class="value-info" id="importprompttokens"></div>
                </div>

                <div class="item-info">
                    <div class="required-label label-info">Token Selesai</div>
                    <div class="value-info" id="importcompletiontokens"></div>
                </div>

                <div class="item-info">
                    <div class="required-label label-info">Total Token</div>
                    <div class="value-info" id="importtotaltokens"></div>
                </div>

                <div class="item-info">
                    <div class="required-label label-info">Alasan Selesai</div>
                    <div class="value-info" id="importfinishreason"></div>
                </div>

                <div class="item-info">
                    <div class="required-label label-info">Bidang Terekstraksi</div>
                    <div class="value-info" id="importextractedfields"></div>
                </div>

                <div class="item-info">
                    <div class="required-label label-info">Total Bidang</div>
                    <div class="value-info" id="importtotalfields"></div>
                </div>

                <div class="item-info">
                    <div class="required-label label-info">Persentase Penyelesaian</div>
                    <div class="value-info" id="importcompletionpercentage"></div>
                </div>

            </div>

        </div>

        <div class="info-box">

            <i class="fas fa-circle-info"></i>

            Pastikan setiap elemen kecerdasan buatan memuat keterangan yang optimal.

        </div>

        <div class="modal-footer">

            <button type="button" class="btn-modal-cancel modal-exit">
                Keluar
            </button>

        </div>

    </div>

</div>