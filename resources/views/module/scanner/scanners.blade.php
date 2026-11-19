<div class="camera-scanner-overlay" id="camerascanneroverlay">

    <div class="camera-scanner-modal">

        <button type="button" class="camera-scanner-close" id="closecamerascanner">
            <i class="fas fa-times"></i>
        </button>

        <div class="camera-scanner-header">

            <div class="camera-scanner-icon">
                <i class="fas fa-camera"></i>
            </div>

            <h2>Pindai Kamera</h2>

            <p>
                Arahkan kode batang Code 128 ke kamera, baik dari kertas, layar monitor, maupun layar ponsel. Atau pilih gambar dari galeri.
            </p>

        </div>

        <div class="camera-scanner-body">

            <div class="camera-scanner-frame">

                <div id="camerascannerreader"></div>
                <div class="camera-scanner-line"></div>

            </div>

            <div class="camera-scanner-status" id="camerascannerstatus">

                <i class="fas fa-circle-info"></i>
                Belum ada kode batang terdeteksi

            </div>

            <div class="camera-scanner-tips">

                <i class="fas fa-lightbulb"></i>

                <div>
                    <strong>Tips Pemindaian</strong>

                    <p>
                        Bisa memindai dari kertas, layar monitor, atau layar ponsel.
                        Isi batang kode di bingkai, dekatkan 15–30 cm, hindari pantulan cahaya.
                        Untuk foto galeri, tekan Pilih Gambar.
                    </p>
                </div>

            </div>

        </div>

        <div class="camera-scanner-footer">

            <button type="button" class="camera-scanner-btn primary" id="startcamerascanner">

                <i class="fas fa-camera"></i>
                Mulai Ulang

            </button>

            <button type="button" class="camera-scanner-btn primary" id="pickbarcodeimage">

                <i class="fas fa-image"></i>
                Pilih Gambar

            </button>

            <button type="button" class="camera-scanner-btn secondary" id="stopcamerascanner">

                Berhenti
                <i class="fas fa-stop"></i>

            </button>

        <input type="file" id="camerascannerimageinput" accept="image/*" hidden>

        </div>

    </div>

</div>
