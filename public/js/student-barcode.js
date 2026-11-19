(() => {
    initApprovalSheetPreview();

    const resultBox = document.getElementById('scanresult');
    const startBtn = document.getElementById('startScan');
    const stopBtn = document.getElementById('stopScan');
    const imageBtn = document.getElementById('scanImageBtn');
    const imageInput = document.getElementById('barcodeImageInput');
    const lookupForm = document.getElementById('barcodeLookupForm');
    const lookupInput = document.getElementById('lookupBarcodeInput');
    const lookupError = document.getElementById('lookupBarcodeError');
    const alertSlot = document.getElementById('scanalertslot');

    if (!lookupForm || !lookupInput || !resultBox) {
        return;
    }

    let html5QrCode = null;
    let isScanning = false;
    let isStarting = false;
    let isSubmitting = false;

    const isOffline = () =>
        (typeof PwaRegister !== 'undefined' && typeof PwaRegister.isOffline === 'function')
            ? PwaRegister.isOffline()
            : !navigator.onLine;

    const setResult = text => {
        resultBox.textContent = text ?? '';
    };

    const setResultText = (...lines) => {
        resultBox.replaceChildren();
        lines.forEach((line, index) => {
            if (index > 0) resultBox.appendChild(document.createElement('br'));
            if (typeof line === 'string') {
                resultBox.appendChild(document.createTextNode(line));
                return;
            }
            const strong = document.createElement('strong');
            strong.textContent = line.text;
            resultBox.appendChild(strong);
        });
    };

    const showAlert = (type, message) => {
        if (!alertSlot) return;

        alertSlot.replaceChildren();
        const ok = type === 'success';
        const alert = document.createElement('div');
        alert.className = `scan-alert ${ok ? 'scan-alert--success' : 'scan-alert--error'}`;
        alert.setAttribute('role', ok ? 'status' : 'alert');
        const messageNode = document.createElement('span');
        messageNode.textContent = message ?? '';
        alert.append(faIcon(ok ? 'fa-circle-check' : 'fa-circle-exclamation'), messageNode);
        alertSlot.append(alert);
    };

    const showFieldError = message => {
        lookupInput.classList.add('is-invalid');
        if (!lookupError) return;
        lookupError.textContent = message;
        lookupError.hidden = false;
    };

    const clearFieldError = () => {
        lookupInput.classList.remove('is-invalid');
        if (!lookupError) return;
        lookupError.textContent = '';
        lookupError.hidden = true;
    };

    const stopScanner = async () => {
        const instance = html5QrCode;
        html5QrCode = null;
        isScanning = false;

        if (!instance) return;

        if (typeof BarcodeCamera !== 'undefined') {
            await BarcodeCamera.stop(instance);
            return;
        }

        try {
            await instance.stop();
        } catch (_) {
            /* ignore */
        }

        try {
            await instance.clear();
        } catch (_) {
            /* ignore */
        }
    };

    const goLookup = async code => {
        const value = String(code || '').trim();

        if (!value || isSubmitting) {
            return;
        }

        // Offline: match a previously cached IndexedDB copy.
        if (isOffline() && typeof OfflineDatabase !== 'undefined') {
            isSubmitting = true;
            lookupInput.value = value;
            await stopScanner();

            try {
                const rows = await OfflineDatabase.getIndexRows('barcodes');
                const match = rows.find(row => String(row[13] || '').trim() === value);

                if (match) {
                    setResultText(
                        { text: 'Ditemukan (mode luring)' },
                        `${match[3] || '-'} — ${match[2] || '-'}`,
                        `${match[4] || '-'}`,
                        `Kode: ${value}`
                    );
                    showAlert(
                        'success',
                        'Kode batang ditemukan di simpanan perangkat (dari sesi petugas sebelumnya). Sambungkan jaringan untuk data yang lengkap.'
                    );
                } else {
                    setResultText({ text: 'Tidak ditemukan secara luring' }, value);
                    showAlert(
                        'error',
                        'Simpanan kode batang belum tersedia di perangkat ini. Buka modul Kode Batang saat daring, atau sambungkan jaringan lalu ulangi.'
                    );
                }
            } catch (error) {
                console.error(error);
                showAlert('error', 'Gagal membaca simpanan luring.');
            }

            isSubmitting = false;
            return;
        }

        isSubmitting = true;
        lookupInput.value = value;
        setResultText({ text: 'Memuat data...' }, value);
        showAlert('success', 'Kode batang terbaca. Mengalihkan ke data...');

        await stopScanner();
        lookupForm.submit();
    };

    const startScanner = async () => {
        if (isScanning || isStarting) return;

        if (typeof BarcodeCamera === 'undefined') {
            setResult('Pustaka pemindai belum dimuat. Gunakan isian manual.');
            showAlert('error', 'Pustaka kamera gagal dimuat. Masukkan kode batang secara manual.');
            return;
        }

        isStarting = true;
        isSubmitting = false;

        try {
            setResult('Mengaktifkan kamera...');

            if (html5QrCode) {
                await stopScanner();
            }

            html5QrCode = BarcodeCamera.create('reader');

            await new Promise(resolve => {
                requestAnimationFrame(() => requestAnimationFrame(resolve));
            });

            await BarcodeCamera.start(html5QrCode, decodedText => {
                goLookup(decodedText);
            });

            isScanning = true;
            setResult('Arahkan batang kode ke kamera, termasuk dari layar');
        } catch (err) {
            console.error(err);
            await stopScanner();
            setResult('Gagal membuka kamera');
            showAlert(
                'error',
                (typeof BarcodeCamera !== 'undefined' && typeof BarcodeCamera.explainError === 'function')
                    ? BarcodeCamera.explainError(err)
                    : 'Kamera tidak dapat diakses. Izinkan akses kamera atau masukkan kode batang secara manual.'
            );
        } finally {
            isStarting = false;
        }
    };

    lookupForm.addEventListener('submit', async event => {
        const value = lookupInput.value.trim();

        if (!value) {
            event.preventDefault();
            const message = 'Kode batang wajib diisi.';
            showAlert('error', message);
            showFieldError(message);
            lookupInput.focus();
            return;
        }

        clearFieldError();

        if (isOffline() && typeof OfflineDatabase !== 'undefined') {
            event.preventDefault();
            await goLookup(value);
            return;
        }

        isSubmitting = true;
        setResultText({ text: 'Memuat metadata...' }, value);
        stopScanner();
    });

    lookupInput.addEventListener('input', () => {
        if (lookupInput.classList.contains('is-invalid') && lookupInput.value.trim()) {
            clearFieldError();
        }
    });

    imageBtn?.addEventListener('click', event => {
        event.preventDefault();
        imageInput?.click();
    });

    imageInput?.addEventListener('change', async event => {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (!file) {
            return;
        }

        if (typeof BarcodeCamera === 'undefined' || typeof BarcodeCamera.scanImage !== 'function') {
            showAlert('error', 'Pustaka pemindai gambar gagal dimuat.');
            return;
        }

        setResult('Membaca gambar kode batang...');

        try {
            const code = await BarcodeCamera.scanImage(file);
            await goLookup(code);
        } catch (error) {
            console.error(error);
            setResult('Gambar tidak dapat dibaca');
            showAlert(
                'error',
                error?.message || 'Pilih gambar kode batang yang lebih jelas, atau ketik kodenya secara manual.'
            );
        }
    });

    startBtn?.addEventListener('click', event => {
        event.preventDefault();
        startScanner();
    });

    stopBtn?.addEventListener('click', event => {
        event.preventDefault();
        stopScanner().then(() => {
            setResult('Pemindaian dihentikan');
        });
    });

    function initApprovalSheetPreview() {
        const modal = document.getElementById('prooflettermodal');
        const titleEl = document.getElementById('prooflettertitle');
        const textEl = document.getElementById('prooflettertext');

        if (!document.querySelector('[data-approval-preview]')) {
            return;
        }

        const previewOffline = () =>
            (typeof PwaRegister !== 'undefined' && typeof PwaRegister.isOffline === 'function')
                ? PwaRegister.isOffline()
                : !navigator.onLine;

        const showError = (title, text) => {
            if (!modal || !titleEl || !textEl) return;
            titleEl.textContent = title;
            textEl.textContent = text;
            modal.removeAttribute('hidden');
            modal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        };

        const closeError = () => {
            if (!modal) return;
            modal.classList.remove('is-open');
            modal.setAttribute('hidden', '');
            document.body.style.overflow = '';
        };

        document.addEventListener('click', event => {
            if (event.target === modal || event.target.closest('[data-proof-letter-close]')) {
                event.preventDefault();
                closeError();
                return;
            }

            const trigger = event.target.closest('[data-approval-preview]');
            if (!trigger) return;

            event.preventDefault();

            if (previewOffline()) {
                showError(
                    'Gagal unduh lembar persetujuan.',
                    'Unduh lembar persetujuan membutuhkan koneksi daring.'
                );
                return;
            }

            const url = trigger.getAttribute('data-preview-url');
            if (url) {
                window.open(url, '_blank', 'noopener');
            }
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && modal?.classList.contains('is-open')) {
                closeError();
            }
        });
    }
})();
