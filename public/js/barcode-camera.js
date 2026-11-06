/**
 * Pemindaian barcode CODE_128 — petugas, koordinator, mahasiswa.
 *
 * html5-qrcode / ZXing membaca CODE_128 dari webcam laptop lebih andal
 * daripada BarcodeDetector native Chrome/Edge di desktop. Detektor native
 * tetap dipakai sebagai percepatan paralel (ponsel) setelah kamera hidup.
 *
 * Kamera laptop = menghadap pengguna (user), bukan environment (belakang).
 *
 * Bagian: konteks kamera, konfigurasi html5-qrcode, daftar kamera,
 * olah gambar, aliran start/native, pindai berkas.
 */
(() => {
    const code128Formats = () => (
        typeof Html5QrcodeSupportedFormats !== 'undefined'
            ? [Html5QrcodeSupportedFormats.CODE_128]
            : undefined
    );

    const isMobileCamera = () => /Android|webOS|iPhone|iPad|iPod|Mobile/i.test(
        navigator.userAgent || ''
    );

    const facingPreference = () => (isMobileCamera() ? 'environment' : 'user');

    const isSecureCameraContext = () => {
        if (window.isSecureContext) {
            return true;
        }

        const host = window.location.hostname;
        return host === 'localhost' || host === '127.0.0.1' || host === '::1';
    };

    const libraryConfig = () => {
        const config = {
            verbose: false,
            // ZXing untuk CODE_128 di webcam komputer; native API sering
            // "sukses membuka kamera" tapi tidak pernah mengembalikan kode 1D.
            useBarCodeDetectorIfSupported: false,
            experimentalFeatures: {
                useBarCodeDetectorIfSupported: false,
            },
        };

        const formats = code128Formats();
        if (formats) {
            config.formatsToSupport = formats;
        }

        return config;
    };

    const qrbox = (viewWidth, viewHeight) => {
        // html5-qrcode hanya menggambar siku jika sisa tepi ≥ 11px.
        const margin = 28;
        const width = Math.max(50, Math.min(
            Math.floor(viewWidth * 0.84),
            viewWidth - margin
        ));
        const height = Math.max(50, Math.min(
            Math.floor(Math.min(Math.max(viewHeight * 0.36, 110), viewHeight * 0.46)),
            viewHeight - margin
        ));

        return { width, height };
    };

    const html5VideoConstraints = (facingMode, deviceId) => {
        const constraints = {
            width: { ideal: 1280 },
            height: { ideal: 720 },
            frameRate: { ideal: 24 },
        };

        if (deviceId) {
            constraints.deviceId = { exact: deviceId };
            return constraints;
        }

        constraints.facingMode = { ideal: facingMode };
        return constraints;
    };

    const scanConfig = (facingMode, deviceId) => ({
        fps: 16,
        qrbox,
        disableFlip: false,
        videoConstraints: html5VideoConstraints(facingMode, deviceId),
    });

    const cameraAttempts = facingMode => [
        {
            audio: false,
            video: {
                facingMode: { ideal: facingMode },
                width: { ideal: 1280 },
                height: { ideal: 720 },
                frameRate: { ideal: 24 },
            },
        },
        {
            audio: false,
            video: { facingMode: { ideal: facingMode } },
        },
        {
            audio: false,
            video: { facingMode: { ideal: 'user' } },
        },
        {
            audio: false,
            video: { facingMode: { ideal: 'environment' } },
        },
        {
            audio: false,
            video: true,
        },
    ];

    const cameraLabel = camera => String(camera?.label || camera?.deviceId || '');

    const pickCameraId = cameras => {
        if (!cameras?.length) {
            return '';
        }

        const idOf = camera => camera.id || camera.deviceId || '';
        const usable = cameras.filter(camera => idOf(camera));

        if (!usable.length) {
            return '';
        }

        const back = usable.find(camera => /back|rear|environment|belakang/i.test(cameraLabel(camera)));
        const front = usable.find(camera => /front|user|face|webcam|integrated|internal/i.test(cameraLabel(camera)));

        if (isMobileCamera()) {
            return idOf(back || usable[usable.length - 1] || usable[0]);
        }

        return idOf(front || usable[0]);
    };

    const listCameras = async () => {
        if (!navigator.mediaDevices?.enumerateDevices) {
            return [];
        }

        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            return devices
                .filter(device => device.kind === 'videoinput' && device.deviceId)
                .map(device => ({
                    id: device.deviceId,
                    label: device.label,
                }));
        } catch (_) {
            return [];
        }
    };

    const explainError = error => {
        const name = error?.name || '';
        const message = String(error?.message || '');

        if (!isSecureCameraContext()) {
            return 'Kamera diblokir karena alamat situs tidak aman. Buka aplikasi lewat HTTPS atau localhost.';
        }

        if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
            return 'Izin kamera ditolak. Izinkan kamera di pengaturan peramban, lalu ulangi.';
        }

        if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
            return 'Kamera tidak ditemukan. Pastikan kamera laptop terpasang dan tidak dimatikan.';
        }

        if (name === 'NotReadableError' || name === 'TrackStartError') {
            return 'Kamera sedang dipakai aplikasi lain. Tutup Zoom, Teams, atau aplikasi kamera, lalu ulangi.';
        }

        if (name === 'OverconstrainedError' || name === 'ConstraintNotSatisfiedError') {
            return 'Kamera komputer tidak cocok dengan pengaturan kamera belakang. Coba ulangi, atau pilih gambar kode batang.';
        }

        if (/HTTPS|localhost|secure context/i.test(message)) {
            return 'Kamera hanya dapat digunakan di HTTPS atau localhost.';
        }

        return 'Kamera tidak dapat diakses. Izinkan akses kamera, atau masukkan kode batang secara manual.';
    };

    const firstValue = codes => {
        if (!codes?.length) {
            return '';
        }

        for (let i = 0; i < codes.length; i++) {
            const value = String(codes[i].rawValue || '').trim();
            if (value) {
                return value;
            }
        }

        return '';
    };

    const detectValue = async (detector, source) => {
        if (!detector || !source) {
            return '';
        }

        try {
            return firstValue(await detector.detect(source));
        } catch (_) {
            return '';
        }
    };

    const scaledSize = (width, height, maxWidth) => {
        if (width <= maxWidth) {
            return { width, height };
        }

        const scale = maxWidth / width;

        return {
            width: Math.max(1, Math.round(width * scale)),
            height: Math.max(1, Math.round(height * scale)),
        };
    };

    const ensureCanvas = scanner => {
        if (!scanner.workCanvas) {
            scanner.workCanvas = document.createElement('canvas');
        }

        return scanner.workCanvas;
    };

    const canvasContext = canvas => canvas.getContext('2d', {
        willReadFrequently: true,
        alpha: false,
    });

    const stretchContrast = (ctx, width, height) => {
        const image = ctx.getImageData(0, 0, width, height);
        const data = image.data;
        let min = 255;
        let max = 0;

        for (let i = 0; i < data.length; i += 4) {
            const y = (data[i] * 77 + data[i + 1] * 150 + data[i + 2] * 29) >> 8;
            data[i] = y;
            if (y < min) min = y;
            if (y > max) max = y;
        }

        const range = max - min || 1;

        for (let i = 0; i < data.length; i += 4) {
            const stretched = ((data[i] - min) * 255) / range;
            const value = stretched < 127
                ? stretched * 0.72
                : Math.min(255, 127 + (stretched - 127) * 1.45);
            data[i] = data[i + 1] = data[i + 2] = value;
        }

        ctx.putImageData(image, 0, 0);
    };

    const invertCanvas = (ctx, width, height) => {
        const image = ctx.getImageData(0, 0, width, height);
        const data = image.data;

        for (let i = 0; i < data.length; i += 4) {
            data[i] = 255 - data[i];
            data[i + 1] = 255 - data[i + 1];
            data[i + 2] = 255 - data[i + 2];
        }

        ctx.putImageData(image, 0, 0);
    };

    const drawSource = (ctx, source, sx, sy, sw, sh, dw, dh, smooth) => {
        ctx.imageSmoothingEnabled = smooth !== false;
        ctx.imageSmoothingQuality = smooth === 'medium' ? 'medium' : 'high';
        ctx.drawImage(source, sx, sy, sw, sh, 0, 0, dw, dh);
    };

    const detectDrawn = async (detector, scanner, source, options) => {
        const {
            sx = 0,
            sy = 0,
            sw = source.videoWidth || source.naturalWidth || source.width,
            sh = source.videoHeight || source.naturalHeight || source.height,
            maxWidth = 1400,
            enhance = false,
            invert = false,
            smooth = true,
        } = options || {};

        if (!sw || !sh) {
            return '';
        }

        const size = scaledSize(sw, sh, maxWidth);
        const canvas = ensureCanvas(scanner);
        const ctx = canvasContext(canvas);

        if (!ctx) {
            return '';
        }

        canvas.width = size.width;
        canvas.height = size.height;
        drawSource(ctx, source, sx, sy, sw, sh, size.width, size.height, smooth);

        if (enhance) {
            stretchContrast(ctx, size.width, size.height);
        }

        if (invert) {
            invertCanvas(ctx, size.width, size.height);
        }

        return detectValue(detector, canvas);
    };

    const detectScaledBitmap = async (detector, video, scale) => {
        const width = video.videoWidth;
        const height = video.videoHeight;

        if (!width || !height || typeof createImageBitmap !== 'function') {
            return '';
        }

        const resizeWidth = Math.max(1, Math.round(width * scale));
        const resizeHeight = Math.max(1, Math.round(height * scale));
        let bitmap;

        try {
            bitmap = await createImageBitmap(video, {
                resizeWidth,
                resizeHeight,
                resizeQuality: scale < 1 ? 'medium' : 'high',
            });

            return await detectValue(detector, bitmap);
        } catch (_) {
            return '';
        } finally {
            if (bitmap && typeof bitmap.close === 'function') {
                bitmap.close();
            }
        }
    };

    const detectScreenPass = async (detector, scanner, video, pass) => {
        const width = video.videoWidth;
        const height = video.videoHeight;

        if (!width || !height) {
            return '';
        }

        const bandHeight = Math.max(96, Math.round(height * 0.46));
        const bandY = Math.round((height - bandHeight) / 2);
        const mode = pass % 8;

        if (mode <= 3) {
            return detectValue(detector, video);
        }

        if (mode === 4) {
            const scale = width >= 1280 ? 0.5 : 0.72;
            const fromBitmap = await detectScaledBitmap(detector, video, scale);
            if (fromBitmap) {
                return fromBitmap;
            }

            return detectDrawn(detector, scanner, video, {
                sw: width,
                sh: height,
                maxWidth: Math.max(480, Math.round(width * scale)),
                smooth: 'medium',
            });
        }

        if (mode === 5) {
            return detectDrawn(detector, scanner, video, {
                sy: bandY,
                sh: bandHeight,
                maxWidth: 1600,
            });
        }

        if (mode === 6) {
            return detectDrawn(detector, scanner, video, {
                sy: bandY,
                sh: bandHeight,
                maxWidth: 1400,
                enhance: true,
                smooth: 'medium',
            });
        }

        return detectDrawn(detector, scanner, video, {
            sy: bandY,
            sh: bandHeight,
            maxWidth: 1200,
            enhance: true,
            invert: true,
            smooth: 'medium',
        });
    };

    const loadImageFile = file => new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve(image);
        };

        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('Gambar tidak valid'));
        };

        image.src = url;
    });

    const canvasToFile = canvas => new Promise((resolve, reject) => {
        canvas.toBlob(blob => {
            if (!blob) {
                reject(new Error('Gagal menyiapkan gambar'));
                return;
            }

            resolve(new File([blob], 'barcode.png', { type: blob.type || 'image/png' }));
        }, 'image/png');
    });

    const clearContainer = elementId => {
        const container = document.getElementById(elementId);
        if (container) {
            container.replaceChildren();
        }
        return container;
    };

    const findVideo = scanner => scanner.video
        || document.getElementById(scanner.elementId)?.querySelector('video');

    const createDetector = async () => {
        if (typeof BarcodeDetector === 'undefined') {
            return null;
        }

        try {
            return new BarcodeDetector({ formats: ['code_128'] });
        } catch (_) {
            try {
                return new BarcodeDetector();
            } catch (__) {
                return null;
            }
        }
    };

    const create = elementId => ({
        elementId,
        engine: null,
        html5: null,
        video: null,
        stream: null,
        rafId: 0,
        timerId: 0,
        running: false,
        detecting: false,
        pass: 0,
        workCanvas: null,
    });

    const clearTimers = scanner => {
        if (scanner.timerId) {
            clearTimeout(scanner.timerId);
            scanner.timerId = 0;
        }

        if (scanner.rafId) {
            cancelAnimationFrame(scanner.rafId);
            scanner.rafId = 0;
        }
    };

    const scheduleTick = (scanner, tick) => {
        if (!scanner.running) {
            return;
        }

        clearTimers(scanner);
        scanner.timerId = setTimeout(() => {
            scanner.rafId = requestAnimationFrame(tick);
        }, 8);
    };

    const openCameraStream = async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error('Kamera tidak didukung di peramban ini');
        }

        if (!isSecureCameraContext()) {
            throw new Error('Kamera hanya dapat digunakan di HTTPS atau localhost.');
        }

        let lastError;
        const attempts = cameraAttempts(facingPreference());

        for (let i = 0; i < attempts.length; i++) {
            try {
                return await navigator.mediaDevices.getUserMedia(attempts[i]);
            } catch (error) {
                lastError = error;
            }
        }

        throw lastError || new Error('Kamera tidak dapat diakses');
    };

    const tuneTrack = async track => {
        if (!track || typeof track.getCapabilities !== 'function' || typeof track.applyConstraints !== 'function') {
            return;
        }

        let caps;

        try {
            caps = track.getCapabilities() || {};
        } catch (_) {
            return;
        }

        const advanced = [];

        if (Array.isArray(caps.focusMode) && caps.focusMode.includes('continuous')) {
            advanced.push({ focusMode: 'continuous' });
        }

        if (caps.exposureCompensation && typeof caps.exposureCompensation.min === 'number') {
            const min = caps.exposureCompensation.min;
            const max = caps.exposureCompensation.max;
            const target = Math.max(min, Math.min(max, -0.7));
            advanced.push({ exposureCompensation: target });
        }

        if (Array.isArray(caps.exposureMode) && caps.exposureMode.includes('continuous')) {
            advanced.push({ exposureMode: 'continuous' });
        }

        if (Array.isArray(caps.whiteBalanceMode) && caps.whiteBalanceMode.includes('continuous')) {
            advanced.push({ whiteBalanceMode: 'continuous' });
        }

        if (!advanced.length) {
            return;
        }

        try {
            await track.applyConstraints({ advanced });
        } catch (_) {
            /* perangkat menolak constraint lanjutan */
        }
    };

    const tuneLiveVideo = async scanner => {
        const video = findVideo(scanner);
        const stream = scanner.stream || video?.srcObject;
        const track = stream?.getVideoTracks?.()[0];

        if (track) {
            await tuneTrack(track);
        }
    };

    const waitForVideoData = video => new Promise(resolve => {
        if (video.readyState >= 2 && video.videoWidth) {
            resolve();
            return;
        }

        const finish = () => {
            video.removeEventListener('loadeddata', finish);
            resolve();
        };

        video.addEventListener('loadeddata', finish, { once: true });
        setTimeout(finish, 2000);
    });

    const decodeVideoWithZxing = async (scanner, video) => {
        if (!video?.videoWidth || typeof Html5Qrcode === 'undefined') {
            return '';
        }

        try {
            const canvas = ensureCanvas(scanner);
            const ctx = canvasContext(canvas);

            if (!ctx) {
                return '';
            }

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.imageSmoothingEnabled = false;
            ctx.drawImage(video, 0, 0);

            const file = await canvasToFile(canvas);
            return await scanImageWithHtml5(file);
        } catch (_) {
            return '';
        }
    };

    const runDetectLoop = (scanner, video, detector, onSuccess) => {
        const tick = async () => {
            if (!scanner.running) {
                return;
            }

            if (scanner.detecting || video.readyState < 2) {
                scheduleTick(scanner, tick);
                return;
            }

            scanner.detecting = true;

            try {
                const pass = scanner.pass++;
                let value = detector
                    ? await detectScreenPass(detector, scanner, video, pass)
                    : '';

                if (!value && pass % 6 === 5) {
                    value = await decodeVideoWithZxing(scanner, video);
                }

                if (value && scanner.running) {
                    finishWithCode(scanner, value, onSuccess);
                    return;
                }
            } catch (_) {
                /* frame kosong, lanjut */
            }

            scanner.detecting = false;

            if (scanner.running) {
                scheduleTick(scanner, tick);
            }
        };

        scheduleTick(scanner, tick);
    };

    const attachNativeBoost = async (scanner, onSuccess) => {
        const video = findVideo(scanner);
        if (!video) {
            return;
        }

        const detector = await createDetector();
        if (!scanner.running) {
            return;
        }

        runDetectLoop(scanner, video, detector, onSuccess);
    };

    const startNative = async (scanner, onSuccess) => {
        if (typeof BarcodeDetector === 'undefined') {
            throw new Error('BarcodeDetector tidak tersedia');
        }

        const container = clearContainer(scanner.elementId);
        if (!container) {
            throw new Error('Elemen kamera tidak ditemukan');
        }

        const detector = await createDetector();
        if (!detector) {
            throw new Error('BarcodeDetector tidak tersedia');
        }

        const video = document.createElement('video');
        video.className = 'barcode-camera-video';
        video.setAttribute('playsinline', 'true');
        video.setAttribute('webkit-playsinline', 'true');
        video.setAttribute('muted', '');
        video.setAttribute('autoplay', '');
        video.muted = true;
        video.playsInline = true;
        video.autoplay = true;
        container.appendChild(video);

        const stream = await openCameraStream();

        scanner.engine = 'native';
        scanner.video = video;
        scanner.stream = stream;
        scanner.running = true;
        scanner.detecting = false;
        scanner.pass = 0;

        video.srcObject = stream;
        await video.play();
        await waitForVideoData(video);
        await tuneTrack(stream.getVideoTracks()[0]);

        runDetectLoop(scanner, video, detector, onSuccess);
    };

    const resetHtml5Instance = async scanner => {
        if (!scanner.html5) {
            return;
        }

        const instance = scanner.html5;
        scanner.html5 = null;

        try {
            await instance.stop();
        } catch (_) {
            /* ignore */
        }

        try {
            await instance.clear();
        } catch (_) {
            try {
                instance.clear();
            } catch (__) {
                /* ignore */
            }
        }
    };

    const startHtml5 = async (scanner, onSuccess, onFailure) => {
        if (typeof Html5Qrcode === 'undefined') {
            throw new Error('Html5Qrcode belum dimuat');
        }

        clearContainer(scanner.elementId);

        const handleSuccess = decodedText => {
            finishWithCode(scanner, decodedText, onSuccess);
        };

        const facing = facingPreference();
        const cameras = await listCameras();
        const deviceId = pickCameraId(cameras);
        const mildConfig = { fps: 12, qrbox, disableFlip: false };
        const attempts = [];

        if (deviceId) {
            attempts.push([deviceId, scanConfig(facing, deviceId)]);
            attempts.push([deviceId, mildConfig]);
        }

        attempts.push([{ facingMode: facing }, scanConfig(facing)]);
        attempts.push([{ facingMode: facing }, mildConfig]);
        attempts.push([{ facingMode: 'user' }, mildConfig]);
        attempts.push([{ facingMode: 'environment' }, mildConfig]);

        let lastError;

        for (let i = 0; i < attempts.length; i++) {
            const [camera, config] = attempts[i];

            await resetHtml5Instance(scanner);
            clearContainer(scanner.elementId);

            scanner.html5 = new Html5Qrcode(scanner.elementId, libraryConfig());
            scanner.engine = 'html5';
            scanner.running = true;
            scanner.pass = 0;

            try {
                await scanner.html5.start(camera, config, handleSuccess, onFailure);
                lastError = null;
                break;
            } catch (error) {
                lastError = error;
                scanner.running = false;
            }
        }

        if (lastError) {
            throw lastError;
        }

        await tuneLiveVideo(scanner);
        await attachNativeBoost(scanner, onSuccess);
    };

    const haltTracks = scanner => {
        clearTimers(scanner);

        if (scanner.stream) {
            scanner.stream.getTracks().forEach(track => {
                try {
                    track.stop();
                } catch (_) {
                    /* ignore */
                }
            });
            scanner.stream = null;
        }

        const video = findVideo(scanner);
        const liveStream = video?.srcObject;

        if (liveStream && typeof liveStream.getTracks === 'function') {
            liveStream.getTracks().forEach(track => {
                try {
                    track.stop();
                } catch (_) {
                    /* ignore */
                }
            });
        }

        if (video) {
            try {
                video.pause();
            } catch (_) {
                /* ignore */
            }

            video.srcObject = null;
        }
    };

    const finishWithCode = (scanner, decodedText, onSuccess) => {
        const value = String(decodedText || '').trim();

        if (!value || !scanner.running) {
            return;
        }

        scanner.running = false;
        haltTracks(scanner);

        setTimeout(() => {
            void stop(scanner);
        }, 0);

        onSuccess(value);
    };

    const start = async (scanner, onSuccess, onFailure = () => {}) => {
        if (!scanner || !scanner.elementId) {
            throw new Error('Scanner tidak tersedia');
        }

        if (!isSecureCameraContext()) {
            throw new Error('Kamera hanya dapat digunakan di HTTPS atau localhost.');
        }

        await stop(scanner);

        // ZXing dulu (andalan webcam laptop), native hanya jika html5 gagal buka kamera.
        try {
            await startHtml5(scanner, onSuccess, onFailure);
            return;
        } catch (html5Error) {
            await stop(scanner);

            if (typeof BarcodeDetector !== 'undefined') {
                try {
                    await startNative(scanner, onSuccess);
                    return;
                } catch (_) {
                    throw html5Error;
                }
            }

            throw html5Error;
        }
    };

    const stop = async scanner => {
        if (!scanner) {
            return;
        }

        scanner.running = false;
        clearTimers(scanner);

        if (scanner.stream) {
            scanner.stream.getTracks().forEach(track => {
                try {
                    track.stop();
                } catch (_) {
                    /* ignore */
                }
            });
            scanner.stream = null;
        }

        if (scanner.video) {
            try {
                scanner.video.pause();
            } catch (_) {
                /* ignore */
            }

            scanner.video.srcObject = null;
            scanner.video.remove();
            scanner.video = null;
        }

        await resetHtml5Instance(scanner);

        scanner.engine = null;
        scanner.detecting = false;
        scanner.pass = 0;
        scanner.workCanvas = null;
    };

    const scanImageWithHtml5 = async file => {
        if (typeof Html5Qrcode === 'undefined') {
            return '';
        }

        const host = document.createElement('div');
        host.id = `barcode-image-host-${Date.now()}`;
        host.setAttribute('aria-hidden', 'true');
        host.className = 'barcode-image-host';
        document.body.appendChild(host);

        const instance = new Html5Qrcode(host.id, libraryConfig());

        try {
            const text = await instance.scanFile(file, false);
            return String(text || '').trim();
        } catch (_) {
            try {
                if (typeof instance.scanFileV2 === 'function') {
                    const result = await instance.scanFileV2(file, false);
                    return String(result?.decodedText || '').trim();
                }
            } catch (__) {
                return '';
            }
        } finally {
            try {
                await instance.clear();
            } catch (_) {
                /* ignore */
            }

            host.remove();
        }

        return '';
    };

    const scanImageWithDetector = async (file, detector) => {
        if (!detector) {
            return '';
        }
        const scratch = create('_image');
        let image;

        try {
            if (typeof createImageBitmap === 'function') {
                try {
                    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
                    const value = await detectValue(detector, bitmap);
                    if (typeof bitmap.close === 'function') {
                        bitmap.close();
                    }
                    if (value) {
                        return value;
                    }
                } catch (_) {
                    /* lanjut ke Image */
                }
            }

            image = await loadImageFile(file);
            const width = image.naturalWidth || image.width;
            const height = image.naturalHeight || image.height;
            const bandHeight = Math.max(80, Math.round(height * 0.5));
            const bandY = Math.round((height - bandHeight) / 2);

            const passes = [
                () => detectValue(detector, image),
                () => detectDrawn(detector, scratch, image, {
                    sw: width,
                    sh: height,
                    maxWidth: Math.max(640, Math.round(width * 0.5)),
                    smooth: 'medium',
                }),
                () => detectDrawn(detector, scratch, image, {
                    sy: bandY,
                    sh: bandHeight,
                    maxWidth: 1600,
                    enhance: true,
                }),
                () => detectDrawn(detector, scratch, image, {
                    sw: width,
                    sh: height,
                    maxWidth: width < 900 ? 1600 : 1400,
                    enhance: true,
                    invert: true,
                    smooth: 'medium',
                }),
            ];

            for (let i = 0; i < passes.length; i++) {
                const value = await passes[i]();
                if (value) {
                    return value;
                }
            }

            try {
                const canvas = ensureCanvas(scratch);
                if (canvas.width && canvas.height) {
                    const processed = await canvasToFile(canvas);
                    return await scanImageWithHtml5(processed);
                }
            } catch (_) {
                return '';
            }

            return '';
        } finally {
            scratch.workCanvas = null;
        }
    };

    const scanImage = async file => {
        if (!file) {
            throw new Error('Pilih gambar kode batang terlebih dahulu.');
        }

        if (typeof BarcodeDetector !== 'undefined') {
            try {
                const detector = await createDetector();
                const value = await scanImageWithDetector(file, detector);
                if (value) {
                    return value;
                }
            } catch (_) {
                /* lanjut html5-qrcode */
            }
        }

        const fromFile = await scanImageWithHtml5(file);
        if (fromFile) {
            return fromFile;
        }

        throw new Error('Kode batang pada gambar tidak terbaca. Pastikan garis kode terlihat jelas.');
    };

    window.BarcodeCamera = {
        create,
        start,
        stop,
        scanImage,
        scanConfig,
        qrbox,
        explainError,
    };
})();
