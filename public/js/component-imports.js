if (typeof pdfjsLib !== 'undefined') {
    const pdfWorker = document.querySelector('meta[name="pdf-worker"]')?.content;
    if (pdfWorker) {
        pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorker;
    }
}

const importFileStore = new Map();
const approvalSheetStore = new Map();

/**
 * Samakan jenjang hasil baca dengan level yang ada di dropdown jenis tugas akhir.
 */
function normalizeImportedDegreeLevel(raw) {
    const select = document.querySelector('#importdegreelevel')?.closest('.custom-select');
    const options = select ? [...select.querySelectorAll('.custom-option')] : [];
    const needle = String(raw ?? '').trim().toLowerCase();

    if (!needle || options.length === 0) {
        return { level: raw ?? '', typeId: '' };
    }

    const byLevel = options.filter(option => String(option.dataset.value || '').trim().toLowerCase() === needle);
    if (byLevel.length) {
        return {
            level: byLevel[0].dataset.value || raw,
            typeId: byLevel.length === 1 ? (byLevel[0].dataset.id || '') : ''
        };
    }

    const byName = options.filter(option => {
        const label = String(option.dataset.label || option.textContent || '').trim().toLowerCase();
        const typeName = label.split('—').pop()?.trim() || '';

        return label === needle || typeName === needle;
    });

    if (byName.length === 1) {
        return {
            level: byName[0].dataset.value || raw,
            typeId: byName[0].dataset.id || ''
        };
    }

    return { level: raw ?? '', typeId: '' };
}

/** Reader Groq (/reader/import) wajib online — tidak masuk antrean PWA. */
function isAiImportOffline() {
    if (typeof OfflineSync !== 'undefined' && typeof OfflineSync.isOffline === 'function') {
        return OfflineSync.isOffline();
    }

    if (typeof PwaRegister !== 'undefined' && typeof PwaRegister.isOffline === 'function') {
        return PwaRegister.isOffline();
    }

    return !navigator.onLine;
}

/**
 * Tampilkan confirm/alert bila reader AI offline.
 * @returns {boolean} true jika proses boleh dilanjutkan (online)
 */
function ensureAiImportOnline() {
    if (!isAiImportOffline()) return true;

    if (typeof progressModal !== 'undefined' && progressModal) {
        progressModal.classList.remove('show');
    }

    const header = 'Impor AI tidak dapat diproses.';
    const paragraph =
        'Koneksi sedang terputus. Analisis sampul dengan kecerdasan buatan (Groq) membutuhkan jaringan aktif karena data berkas dikirim sebagai perintah. Sambungkan kembali ke internet, lalu ulangi impor.';

    if (typeof showError === 'function') {
        showError(header, paragraph);
    } else if (typeof showAlert === 'function') {
        showAlert(header, paragraph);
    } else {
        window.alert(`${header}\n\n${paragraph}`);
    }

    return false;
}

function syncAiImportControls() {
    const offline = isAiImportOffline();
    const addBtn = document.getElementById('buttonaddimport');

    if (addBtn) {
        addBtn.classList.toggle('is-offline-blocked', offline);
        addBtn.setAttribute('aria-disabled', offline ? 'true' : 'false');
        addBtn.title = offline
            ? 'Impor AI membutuhkan koneksi daring'
            : '';
    }

    const banner = document.getElementById('ai-import-offline-note');
    if (banner) banner.hidden = !offline;
}

async function parsePdfFile(file) {
    if (!file) {
        throw new Error('Berkas tidak ditemukan.');
    }

    if (!file.size) {
        throw new Error('Berkas PDF kosong.');
    }

    if (typeof pdfjsLib === 'undefined') {
        throw new Error('Pembaca PDF belum siap. Muat ulang halaman.');
    }

    const pdf = await pdfjsLib.getDocument({
        data: await file.arrayBuffer(),
        isEvalSupported: false,
        disableAutoFetch: true,
        disableStream: true
    }).promise;

    try {
        if (!pdf.numPages) {
            throw new Error('PDF tidak memiliki halaman.');
        }

        const page = await pdf.getPage(1);

        try {
            const content = await page.getTextContent();
            return extractText(content?.items);
        } finally {
            page.cleanup?.();
        }
    } finally {
        pdf.destroy();
    }
}

function extractText(items) {
    if (!Array.isArray(items) || !items.length) return '';

    const lines = {};

    items.forEach(item => {
        const y = Number(item?.transform?.[5]);
        if (!Number.isFinite(y)) return;
        (lines[Math.round(y)] ??= []).push(item);
    });

    let text = '';
    let lastLine;

    Object.keys(lines).sort((a, b) => b - a).forEach(line => {
        if (lastLine && lastLine - line > 25) text += '\n';

        text += lines[line]
            .sort((a, b) => a.transform[4] - b.transform[4])
            .map(item => item.str)
            .join(' ')
            .replace(/\s+/g, ' ') + '\n';

        lastLine = line;
    });

    return text;
}

function summarizeFailedFiles(names) {
    if (!names.length) return '';

    const preview = names.slice(0, 3).join(', ');
    const extra = names.length > 3 ? ` (+${names.length - 3} lainnya)` : '';
    return `${preview}${extra}`;
}

async function importPdfFiles() {
    if (!ensureAiImportOnline()) {
        return false;
    }

    const files = $('#folderfile')[0].files;
    const total = files.length;

    if (!total) {
        showError(
            'Impor AI tidak dapat diproses.',
            'Tidak ada berkas PDF yang dipilih.'
        );
        return false;
    }

    if (total > 200 && !window.confirm(`Anda mengimpor ${total} berkas. Proses ini bisa memakan waktu lama dan memakai memori besar. Lanjutkan?`)) {
        return false;
    }

    importTable.clear().draw();
    importFileStore.clear();
    approvalSheetStore.clear();

    let rowNumber = 1;
    let processed = 0;
    let abortedOffline = false;
    const failedNames = [];

    const importDate = new Date().toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });

    if (typeof lockProgressConfirm === 'function') {
        lockProgressConfirm();
    }

    try {
        for (let i = 0; i < total; i++) {
            // Cek offline tanpa menutup progress (ringkasan di akhir loop)
            if (isAiImportOffline()) {
                abortedOffline = true;
                break;
            }

            const file = files[i];

            try {
                setProgress(
                    'Memproses Impor.',
                    'Data impor telah diproses untuk disimpan sementara sebelum kirim ke basis data.',
                    Math.round((i / total) * 100),
                    `Membaca "${file.name}"...`
                );

                const text = await parsePdfFile(file);

                if (isAiImportOffline()) {
                    abortedOffline = true;
                    break;
                }

                setProgress(
                    'Memproses Impor.',
                    'Data impor telah diproses untuk disimpan sementara sebelum kirim ke basis data.',
                    Math.round((i / total) * 100),
                    `Analisis "${file.name}" gunakan kecerdasan buatan...`
                );

                const id = rowNumber++;
                let rows;

                try {
                    rows = await $.ajax({
                        url: '/reader/import',
                        type: 'POST',
                        timeout: 120000,
                        headers: {
                            'X-CSRF-TOKEN': csrfToken
                        },
                        data: {
                            text,
                            _token: csrfToken
                        }
                    });
                } catch (ajaxError) {
                    if (isAiImportOffline() || ajaxError?.status === 0) {
                        abortedOffline = true;
                        break;
                    }

                    throw ajaxError;
                }

                importFileStore.set(id, file);

                rows.forEach(row => {
                    row.unshift(id);
                    row.splice(7, 0, importDate, file.name, null);

                    const degree = normalizeImportedDegreeLevel(row[5]);
                    row[5] = degree.level;
                    row[20] = degree.typeId;
                });

                importTable.rows.add(rows);
                processed += 1;

                setProgress(
                    'Memproses Impor.',
                    'Data impor telah diproses untuk disimpan sementara sebelum kirim ke basis data.',
                    Math.round(((i + 1) / total) * 100),
                    `Proses ${i + 1} dari ${total} berkas.`
                );
            } catch (error) {
                console.error(error);
                failedNames.push(file.name);

                setProgress(
                    'Memproses Impor.',
                    'Data impor telah diproses untuk disimpan sementara sebelum kirim ke basis data.',
                    Math.round(((i + 1) / total) * 100),
                    `Gagal menganalisis "${file.name}". Melanjutkan berkas berikutnya...`
                );
            }
        }

        importTable.draw(false);

        if (abortedOffline) {
            setProgress(
                'Impor AI dihentikan.',
                'Koneksi terputus — analisis kecerdasan buatan tidak dapat dilanjutkan.',
                100,
                `Berhasil ${processed} dari ${total} berkas sebelum terputus.`
            );
            return false;
        }

        if (failedNames.length) {
            setProgress(
                'Impor selesai dengan peringatan.',
                'Sebagian berkas gagal dianalisis. Periksa daftar di bawah lalu ulangi berkas yang gagal bila perlu.',
                100,
                `Berhasil ${processed} dari ${total}. Gagal ${failedNames.length}: ${summarizeFailedFiles(failedNames)}.`
            );
            return processed > 0;
        }

        setProgress(
            'Memproses Impor.',
            'Data impor telah diproses untuk disimpan sementara sebelum kirim ke basis data.',
            100,
            `Impor sukses ${processed} berkas.`
        );

        return true;
    } finally {
        if (typeof unlockProgressConfirm === 'function') {
            unlockProgressConfirm();
        }
    }
}

document.addEventListener('DOMContentLoaded', syncAiImportControls);
window.addEventListener('online', syncAiImportControls);
window.addEventListener('offline', syncAiImportControls);
