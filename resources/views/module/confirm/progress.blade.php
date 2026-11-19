<div class="confirm-modal" id="progressmodal">

    <div class="confirm-popup">

        <button class="confirm-close">
            <i class="fas fa-times"></i>
        </button>

        <div class="confirm-left confirm-success">

            <div class="dot-pattern"></div>

            <div class="icon-wrapper">

                <div class="loading-ring"></div>

                <div class="icon-circle">
                    <i class="fas fa-check"></i>
                </div>

            </div>

        </div>

        <div class="confirm-right">

            <h2></h2>

            <div class="confirm-line success-line"></div>

            <p></p>

            <div class="progress-section">

                <div class="progress-header">
                    <span id="progresslabel">Mengirim data tesis...</span>
                    <span id="progresspercent">0%</span>
                </div>

                <div
                    class="progress"
                    id="progressbar"
                    role="progressbar"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="0"
                    aria-labelledby="progresslabel"
                >
                    <div class="progress-fill" id="progressfill"></div>
                </div>

                <div class="progress-meta" id="progressmeta" hidden></div>
                <div class="progress-file" id="progressfile" hidden></div>

                <div class="progress-status" id="progressstatus" aria-live="polite">
                    Mohon tunggu sebentar, proses ini mungkin memerlukan waktu.
                </div>

            </div>

            <div class="confirm-actions">

                <button class="btn-cancel">
                    Keluar
                </button>

            </div>

        </div>

    </div>

</div>
