<div class="device-scanner-overlay" id="devicescanneroverlay">

    <div class="device-scanner-modal">

        <button type="button" class="device-scanner-close" id="closedevicescanner">
            <i class="fas fa-times"></i>
        </button>

        <div class="device-scanner-header">

            <div class="device-scanner-icon">
                <i class="fas fa-barcode"></i>
            </div>

            <h2>Pindai Alat</h2>

            <p>
                Hubungkan pemindai kode batang (USB atau mode papan ketik), lalu arahkan ke kode batang untuk menampilkan data yang cocok di tabel.
            </p>

        </div>

        <div class="device-scanner-body">

            <div class="device-scanner-connection is-disconnected" id="devicescannerconnection">
                <i class="fas fa-plug" id="devicescannerconnectionicon"></i>
                <div>
                    <strong id="devicescannerconnectionlabel">Tidak terhubung</strong>
                    <span id="devicescannerconnectionhint">Klik Mulai untuk menghubungkan alat pemindai.</span>
                </div>
            </div>

            <label class="device-scanner-input-label" for="devicescannerinput">Masukan pemindaian</label>
            <input
                type="text"
                id="devicescannerinput"
                class="device-scanner-input"
                autocomplete="off"
                autocorrect="off"
                autocapitalize="off"
                spellcheck="false"
                placeholder="Fokus di sini, lalu pindai kode batang dengan alat..."
                readonly
            >

            <div class="device-scanner-status" id="devicescannerstatus">
                <i class="fas fa-circle-info"></i>
                Alat belum dihubungkan
            </div>

            <div class="device-scanner-tips">

                <i class="fas fa-lightbulb"></i>

                <div>
                    <strong>Tips Alat Scanner</strong>

                    <p>
                        Pastikan pemindai USB tersambung ke komputer.
                        Setelah status Terhubung, arahkan alat ke kode batang hingga berbunyi.
                    </p>
                </div>

            </div>

        </div>

        <div class="device-scanner-footer">

            <button type="button" class="device-scanner-btn primary" id="startdevicescanner">

                <i class="fas fa-plug"></i>
                Mulai

            </button>

            <button type="button" class="device-scanner-btn secondary" id="stopdevicescanner">

                Putuskan
                <i class="fas fa-plug-circle-xmark"></i>

            </button>

        </div>

    </div>

</div>
