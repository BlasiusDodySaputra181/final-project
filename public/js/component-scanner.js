const stopCameraScan = async () => {
    const instance = html5QrCode;
    html5QrCode = null;

    if (!instance || typeof BarcodeCamera === 'undefined') {
        return;
    }

    await BarcodeCamera.stop(instance);
};

const closeCameraScanner = () => {
    cameraScannerOverlay?.classList.remove('show');
    document.body.style.overflow = '';

    // Hentikan kamera setelah overlay tertutup; tunggu agar reopen tidak race
    return stopCameraScan();
};

let cameraBarcodeScanLocked = false;
let cameraScannerStarting = false;

const setCameraScannerStatus = (icon, message) => {
    if (!cameraScannerStatus) return;

    appendStatus(cameraScannerStatus, icon, message);
};

const startCameraScan = async () => {
    if (cameraScannerStarting) return;

    if (typeof BarcodeCamera === 'undefined') {
        setCameraScannerStatus('fa-circle-exclamation', 'Pustaka kamera gagal dimuat');
        return;
    }

    cameraScannerStarting = true;
    cameraBarcodeScanLocked = false;

    try {
        if (html5QrCode) {
            await stopCameraScan();
        }

        setCameraScannerStatus('fa-spinner fa-spin', 'Mengaktifkan kamera...');

        html5QrCode = BarcodeCamera.create('camerascannerreader');

        await BarcodeCamera.start(
            html5QrCode,
            decodedText => {
                applyScannedBarcode(decodedText, 'camera');
            }
        );

        setCameraScannerStatus('fa-circle-info', 'Arahkan batang kode ke kamera, termasuk dari layar');
    } catch (error) {
        console.error(error);
        cameraBarcodeScanLocked = false;
        await stopCameraScan();
        const message = (typeof BarcodeCamera !== 'undefined' && typeof BarcodeCamera.explainError === 'function')
            ? BarcodeCamera.explainError(error)
            : 'Kamera tidak dapat diakses';
        setCameraScannerStatus('fa-circle-exclamation', message);
    } finally {
        cameraScannerStarting = false;
    }
};

const openCameraScanner = () => {
    cameraBarcodeScanLocked = false;
    setCameraScannerStatus('fa-circle-info', 'Menyiapkan kamera...');
    cameraScannerOverlay?.classList.add('show');
    document.body.style.overflow = 'hidden';

    // Jeda 1 frame agar overlay ter-layout sebelum getUserMedia
    requestAnimationFrame(() => {
        startCameraScan();
    });
};

const applyScannedBarcode = (code, source) => {
    const value = String(code || '').trim();

    if (!value || cameraBarcodeScanLocked) {
        return;
    }

    cameraBarcodeScanLocked = true;

    setCameraScannerStatus(
        'fa-check-circle',
        statusSentence('Kode batang ditemukan: ', value, '')
    );

    void stopCameraScan();

    document.dispatchEvent(new CustomEvent('barcode-scanned', {
        detail: { code: value, source }
    }));

    cameraScannerOverlay?.classList.remove('show');
    document.body.style.overflow = '';
};

const scanBarcodeImageFile = async file => {
    if (!file || typeof BarcodeCamera === 'undefined' || typeof BarcodeCamera.scanImage !== 'function') {
        if (typeof showError === 'function') {
            showError('Gagal membaca gambar.', 'Pustaka pemindai gambar belum dimuat.');
        }
        return;
    }

    setCameraScannerStatus('fa-spinner fa-spin', 'Membaca gambar kode batang...');

    try {
        const code = await BarcodeCamera.scanImage(file);
        applyScannedBarcode(code, 'image');
    } catch (error) {
        console.error(error);
        cameraBarcodeScanLocked = false;

        const message = error?.message || 'Gambar kode batang tidak dapat dibaca.';
        setCameraScannerStatus('fa-circle-exclamation', message);

        if (typeof showError === 'function') {
            showError('Gambar tidak terbaca.', message);
        }
    }
};

if (stopCameraScannerButton) {
    stopCameraScannerButton.onclick = () => {
        stopCameraScan().then(() => {
            setCameraScannerStatus('fa-circle-info', 'Pemindaian dihentikan');
        });
    };
}

$(document).ready(function() {
    if (!cameraScannerOverlay) return;

    cameraScannerOverlay.onclick = event => {
        if (event.target === cameraScannerOverlay) {
            closeCameraScanner();
        }
    };

    byId('closecamerascanner')?.addEventListener('click', () => {
        closeCameraScanner();
    });

    $('body').on('click', '#buttonscancamerabarcode', event => {
        event.preventDefault();
        openCameraScanner();
    });

    const imageInput = byId('camerascannerimageinput');

    const openImagePicker = () => {
        cameraBarcodeScanLocked = false;
        imageInput?.click();
    };

    imageInput?.addEventListener('change', event => {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (file) {
            scanBarcodeImageFile(file);
        }
    });

    $('body').on('click', '#buttonscanimagebarcode', event => {
        event.preventDefault();
        openImagePicker();
    });

    byId('pickbarcodeimage')?.addEventListener('click', event => {
        event.preventDefault();
        openImagePicker();
    });

    if (startCameraScannerButton) {
        startCameraScannerButton.onclick = event => {
            event.preventDefault();
            startCameraScan();
        };
    }
});
