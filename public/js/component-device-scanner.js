let deviceScannerListening = false;
let deviceScannerBuffer = '';
let deviceScannerLastKeyAt = 0;
let deviceScannerFocusTimer = null;

const setDeviceScannerConnection = connected => {
    if (!deviceScannerConnection) return;

    deviceScannerConnection.classList.toggle('is-connected', connected);
    deviceScannerConnection.classList.toggle('is-disconnected', !connected);

    const icon = byId('devicescannerconnectionicon');
    const label = byId('devicescannerconnectionlabel');
    const hint = byId('devicescannerconnectionhint');

    if (icon) {
        icon.className = connected ? 'fas fa-check-circle' : 'fas fa-plug';
    }

    if (label) {
        label.textContent = connected ? 'Terhubung' : 'Tidak terhubung';
    }

    if (hint) {
        hint.textContent = connected
            ? 'Alat siap. Arahkan pemindai ke kode batang.'
            : 'Klik Mulai untuk menghubungkan alat pemindai.';
    }
};

const setDeviceScannerStatus = (message, icon = 'fa-circle-info') => {
    if (!deviceScannerStatus) return;

    appendStatus(deviceScannerStatus, icon, message);
};

const clearDeviceScannerBuffer = () => {
    deviceScannerBuffer = '';
    deviceScannerLastKeyAt = 0;

    if (deviceScannerInput) {
        deviceScannerInput.value = '';
    }
};

const stopDeviceScanner = () => {
    deviceScannerListening = false;
    clearDeviceScannerBuffer();

    if (deviceScannerFocusTimer) {
        clearInterval(deviceScannerFocusTimer);
        deviceScannerFocusTimer = null;
    }

    if (deviceScannerInput) {
        deviceScannerInput.readOnly = true;
        deviceScannerInput.blur();
    }

    setDeviceScannerConnection(false);
    setDeviceScannerStatus('Alat diputus atau tidak terhubung', 'fa-unlink');
};

const closeDeviceScanner = () => {
    stopDeviceScanner();
    deviceScannerOverlay?.classList.remove('show');
    document.body.style.overflow = '';
};

const keepDeviceScannerFocused = () => {
    if (!deviceScannerListening || !deviceScannerInput) return;

    if (document.activeElement !== deviceScannerInput) {
        deviceScannerInput.focus();
    }

    const focused = document.activeElement === deviceScannerInput;
    setDeviceScannerConnection(focused);

    if (!focused) {
        setDeviceScannerStatus('Fokus hilang — alat tidak terhubung', 'fa-circle-exclamation');
    }
};

const startDeviceScanner = () => {
    if (!deviceScannerInput) {
        setDeviceScannerConnection(false);
        setDeviceScannerStatus('Masukan pemindai tidak tersedia', 'fa-circle-exclamation');
        return;
    }

    deviceScannerListening = true;
    clearDeviceScannerBuffer();
    deviceScannerInput.readOnly = false;
    deviceScannerInput.focus();

    const connected = document.activeElement === deviceScannerInput;
    setDeviceScannerConnection(connected);

    if (connected) {
        setDeviceScannerStatus('Terhubung — menunggu pemindaian alat...', 'fa-check-circle');
    } else {
        setDeviceScannerStatus('Tidak terhubung — gagal memfokuskan masukan pemindai', 'fa-circle-exclamation');
    }

    if (deviceScannerFocusTimer) {
        clearInterval(deviceScannerFocusTimer);
    }

    deviceScannerFocusTimer = setInterval(keepDeviceScannerFocused, 700);
};

const openDeviceScanner = () => {
    stopDeviceScanner();
    setDeviceScannerStatus('Siap menghubungkan alat pemindai', 'fa-circle-info');
    deviceScannerOverlay?.classList.add('show');
    document.body.style.overflow = 'hidden';
};

const commitDeviceScannerCode = code => {
    const scanned = String(code || '').trim();

    if (!scanned) return;

    setDeviceScannerStatus(statusSentence('Kode batang ditemukan: ', scanned, ''), 'fa-check-circle');

    document.dispatchEvent(new CustomEvent('barcode-scanned', {
        detail: { code: scanned, source: 'device' }
    }));

    closeDeviceScanner();
};

const handleDeviceScannerKeydown = event => {
    if (!deviceScannerListening || !deviceScannerOverlay?.classList.contains('show')) {
        return;
    }

    if (event.target !== deviceScannerInput) {
        return;
    }

    const now = Date.now();
    const gap = now - deviceScannerLastKeyAt;
    deviceScannerLastKeyAt = now;

    // Input manual lambat dianggap bukan alat scanner
    if (deviceScannerBuffer && gap > 80) {
        deviceScannerBuffer = '';
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        const code = deviceScannerBuffer || String(deviceScannerInput.value || '');
        clearDeviceScannerBuffer();
        commitDeviceScannerCode(code);
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        closeDeviceScanner();
        return;
    }

    if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
        event.preventDefault();
        deviceScannerBuffer += event.key;
        deviceScannerInput.value = deviceScannerBuffer;
        setDeviceScannerConnection(true);
        setDeviceScannerStatus('Menerima data dari alat...', 'fa-spinner fa-spin');
    }
};

$(document).ready(function() {
    if (!deviceScannerOverlay) return;

    deviceScannerOverlay.onclick = event => {
        if (event.target === deviceScannerOverlay) closeDeviceScanner();
    };

    byId('closedevicescanner')?.addEventListener('click', closeDeviceScanner);

    $('body').on('click', '#buttonscandevicebarcode', event => {
        event.preventDefault();
        openDeviceScanner();
    });

    if (startDeviceScannerButton) {
        startDeviceScannerButton.onclick = event => {
            event.preventDefault();
            startDeviceScanner();
        };
    }

    if (stopDeviceScannerButton) {
        stopDeviceScannerButton.onclick = event => {
            event.preventDefault();
            stopDeviceScanner();
        };
    }

    deviceScannerInput?.addEventListener('keydown', handleDeviceScannerKeydown);

    deviceScannerInput?.addEventListener('blur', () => {
        if (!deviceScannerListening) return;

        // Beri jeda singkat agar klik tombol di modal tidak langsung putus
        setTimeout(() => {
            if (!deviceScannerListening) return;

            if (document.activeElement !== deviceScannerInput) {
                setDeviceScannerConnection(false);
                setDeviceScannerStatus('Tidak terhubung — fokus masukan hilang', 'fa-circle-exclamation');
            }
        }, 120);
    });
});
