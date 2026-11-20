<div class="confirm-modal" id="alertsmodal">

    <div class="confirm-popup">

        <button class="confirm-close">
            <i class="fas fa-times"></i>
        </button>

        <div class="confirm-left confirm-errors">

            <div class="dot-pattern"></div>

            <div class="icon-wrapper">

                <div class="loading-ring"></div>

                <div class="icon-circle">
                    <i class="fas fa-minus"></i>
                </div>

            </div>

        </div>

        <div class="confirm-right">

            <h2></h2>

            <div class="confirm-line errors-line"></div>

            <p></p>

            <div class="confirm-actions">

                <button class="btn-cancel">
                    Batal
                </button>

                <button class="btn-submit-confirm" type="button">
                    <svg class="trash-anim" viewBox="0 0 448 512" aria-hidden="true" focusable="false">
                        <use class="trash-lid" href="{{ asset('svg/confirm-trash.svg') }}#lid" x="0" y="0" width="448" height="96"></use>
                        <use class="trash-body" href="{{ asset('svg/confirm-trash.svg') }}#body" x="0" y="0" width="448" height="512"></use>
                    </svg>
                    Hapus
                </button>

            </div>

        </div>

    </div>

</div>