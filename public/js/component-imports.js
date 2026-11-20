if (typeof pdfjsLib !== 'undefined') {
    const pdfWorker = document.querySelector('meta[name="pdf-worker"]')?.content;
    if (pdfWorker) {
        pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorker;
    }
}

const importFileStore = new Map();
const approvalSheetStore = new Map();
const IMPORT_READERS = 2;

/**
 * Comparable jenis-TA text: trimmed, one space between words, and lowercase.
 * Matching uses this text only, so S1 and s1, or Skripsi and skripsi, are the same string.
 */
function comparableDegreeText(value) {
    return String(value ?? '').trim().replace(/\s+/g, ' ').toLowerCase();
}

/**
 * Align the parsed level with a level that exists in the final-project type dropdown.
 */
function normalizeImportedDegreeLevel(raw) {
    const select = document.querySelector('#importdegreelevel')?.closest('.custom-select');
    const options = select ? [...select.querySelectorAll('.custom-option')] : [];
    const needle = comparableDegreeText(raw);

    if (!needle || options.length === 0) {
        return { level: raw ?? '', typeId: '' };
    }

    const byLevel = options.filter(option => comparableDegreeText(option.dataset.value) === needle);
    if (byLevel.length) {
        return {
            level: byLevel[0].dataset.value || raw,
            typeId: byLevel.length === 1 ? (byLevel[0].dataset.id || '') : ''
        };
    }

    const byName = options.filter(option => {
        const label = comparableDegreeText(option.dataset.label || option.textContent || '');
        const typeName = comparableDegreeText(label.split('—').pop() || '');

        return (label !== '' && label === needle) || (typeName !== '' && typeName === needle);
    });

    if (byName.length === 1) {
        return {
            level: byName[0].dataset.value || raw,
            typeId: byName[0].dataset.id || ''
        };
    }

    return { level: raw ?? '', typeId: '' };
}

/**
 * Block saving an import when Jenis Tugas Akhir is empty or no row matches it.
 * A unique name such as skripsi is rewritten to its level (S1) before the check.
 * @returns {{title: string, message: string}|null}
 */
function importSaveDegreeLevelBlock(rows) {
    const example = 'misalnya S1 = skripsi, S2 = tesis, S3 = disertasi, dan jenis lainnya';
    const select = document.querySelector('#importdegreelevel')?.closest('.custom-select');
    const options = select ? [...select.querySelectorAll('.custom-option')] : [];

    if (options.length === 0) {
        return {
            title: 'Gagal menyimpan data Impor.',
            message: `Tidak ada data jenis tugas akhir, sehingga impor tidak disimpan. Isi Jenis Tugas Akhir terlebih dahulu, ${example}.`
        };
    }

    const known = new Set(
        options
            .map(option => comparableDegreeText(option.dataset.value))
            .filter(Boolean)
    );
    const updates = [];
    const missing = [];

    rows.forEach((row, index) => {
        const degree = normalizeImportedDegreeLevel(row[5]);
        const level = String(degree.level ?? '').trim();

        if (!comparableDegreeText(level) || !known.has(comparableDegreeText(level))) {
            missing.push(level || `baris ${index + 1}`);
            return;
        }

        updates.push({ row, level: degree.level, typeId: degree.typeId });
    });

    if (missing.length) {
        const unique = [...new Set(missing)];
        const shown = unique.slice(0, 8).map(value => (
            value.length > 40 ? `${value.slice(0, 40)}…` : value
        ));
        const more = unique.length > 8 ? ', dan lainnya' : '';

        return {
            title: 'Gagal menyimpan data Impor.',
            message: `Jenjang pada data impor tidak sesuai dengan jenis tugas akhir yang tersedia, sehingga data tidak disimpan. Yang tidak cocok: ${shown.join(', ')}${more}. Sesuaikan data Jenis Tugas Akhir, ${example}.`
        };
    }

    updates.forEach(({ row, level, typeId }) => {
        row[5] = level;
        if (typeId) row[20] = typeId;
    });

    return null;
}

/**
 * Study-program options currently offered on the import form.
 * @returns {HTMLElement[]}
 */
function importStudyProgramOptions() {
    const select = document.querySelector('#importstudyprogramme')?.closest('.custom-select');

    return select ? [...select.querySelectorAll('.custom-option')] : [];
}

/**
 * Rewrite a parsed program name to the stored study-program text when the
 * string matches, ignoring case and extra spaces.
 */
function normalizeImportedStudyProgram(raw) {
    const needle = comparableDegreeText(raw);
    const match = importStudyProgramOptions().find(option => (
        comparableDegreeText(option.dataset.value) === needle
    ));

    if (!needle || !match) {
        return raw ?? '';
    }

    return match.dataset.value || raw;
}

/**
 * Block saving an import when Program Studi is empty or no name matches it.
 * Matching uses the study-program text only, so Ilmu Komputer and ilmu komputer are the same string.
 * @returns {{title: string, message: string}|null}
 */
function importSaveStudyProgramBlock(rows) {
    const example = 'misalnya Ilmu Komputer, Matematika, Fisika, Kimia, dan program studi lainnya';
    const options = importStudyProgramOptions();
    const known = new Map();

    options.forEach(option => {
        const text = comparableDegreeText(option.dataset.value);

        if (text && !known.has(text)) {
            known.set(text, option.dataset.value);
        }
    });

    if (known.size === 0) {
        return {
            title: 'Gagal menyimpan data Impor.',
            message: `Tidak ada data program studi, sehingga impor tidak disimpan. Isi Program Studi terlebih dahulu, ${example}.`
        };
    }

    const updates = [];
    const missing = [];

    rows.forEach((row, index) => {
        const raw = row[4];
        const needle = comparableDegreeText(raw);

        if (!needle || !known.has(needle)) {
            const label = String(raw ?? '').trim();
            missing.push(label || `baris ${index + 1}`);
            return;
        }

        updates.push({ row, name: known.get(needle) });
    });

    if (missing.length) {
        const unique = [...new Set(missing)];
        const shown = unique.slice(0, 8).map(value => (
            value.length > 40 ? `${value.slice(0, 40)}…` : value
        ));
        const more = unique.length > 8 ? ', dan lainnya' : '';

        return {
            title: 'Gagal menyimpan data Impor.',
            message: `Program studi pada data impor tidak sesuai dengan program studi yang tersedia, sehingga data tidak disimpan. Yang tidak cocok: ${shown.join(', ')}${more}. Sesuaikan data Program Studi, ${example}.`
        };
    }

    updates.forEach(({ row, name }) => {
        row[4] = name;
    });

    return null;
}

/** The Groq reader (/reader/import) requires a network — it does not enter the PWA queue. */
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
 * Block import when the database has no saved AI key and model yet.
 * A fresh install, before Pengaturan is saved, counts as empty.
 * @returns {boolean} true when a key and model are already stored
 */
function ensureAiKeyAndModelStored() {
    const page = document.getElementById('importpage');
    if (!page || page.dataset.aiStored !== '0') return true;

    if (typeof progressModal !== 'undefined' && progressModal) {
        progressModal.classList.remove('show');
    }

    const header = 'Impor tugas akhir tidak dapat diproses.';
    const paragraph = page.dataset.aiMissingMessage
        || 'Isi kunci dan model AI terlebih dahulu di Pengaturan, atau pilih basis data terkait proyek dari aplikasi final-project ini di Peladen Lokal.';

    if (typeof showError === 'function') {
        showError(header, paragraph);
    } else if (typeof showAlert === 'function') {
        showAlert(header, paragraph);
    } else {
        window.alert(`${header}\n\n${paragraph}`);
    }

    return false;
}

/**
 * Show a confirm or alert when the AI reader is offline.
 * @returns {boolean} true when the process may continue (online)
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

        const readPage = async pageNumber => {
            const page = await pdf.getPage(pageNumber);

            try {
                const content = await page.getTextContent();
                return extractText(content?.items);
            } finally {
                page.cleanup?.();
            }
        };

        let text = await readPage(1);

        // A decorative first page often has almost no text. The title page is next.
        if (compactLength(text) < 80 && pdf.numPages > 1) {
            const titlePage = await readPage(2);
            if (titlePage) text = `${text}\n\n${titlePage}`.trim();
        }

        if (compactLength(text) < 8) {
            throw new Error('Sampul format tugas akhir tidak berisi teks yang dapat dibaca. Pastikan berkas PDF punya teks, bukan hanya gambar hasil pindaian.');
        }

        return text.trim();
    } finally {
        pdf.destroy();
    }
}

function compactLength(text) {
    return String(text || '').replace(/\s+/g, ' ').trim().length;
}

function extractText(items) {
    if (!Array.isArray(items) || !items.length) return '';

    const positioned = items.map(item => {
        const x = Number(item?.transform?.[4]);
        const y = Number(item?.transform?.[5]);
        const height = Math.abs(Number(item?.height) || Number(item?.transform?.[3]) || 0) || 10;

        return {
            str: String(item?.str ?? ''),
            x: Number.isFinite(x) ? x : 0,
            y: Number.isFinite(y) ? y : 0,
            width: Math.max(0, Number(item?.width) || 0),
            height
        };
    }).filter(item => item.str !== '');

    positioned.sort((a, b) => b.y - a.y || a.x - b.x);

    const lines = [];

    positioned.forEach(item => {
        const last = lines[lines.length - 1];
        const tolerance = Math.max(2, ((last?.height || item.height) * 0.45));

        if (!last || Math.abs(last.y - item.y) > tolerance) {
            lines.push({ y: item.y, height: item.height, items: [item] });
            return;
        }

        last.items.push(item);
        last.height = Math.max(last.height, item.height);
    });

    return lines.map(line => {
        const sorted = line.items.sort((a, b) => a.x - b.x);
        let text = '';
        let previous = null;

        sorted.forEach(item => {
            if (previous) {
                const previousWidth = previous.width || Math.max(previous.str.length, 1) * previous.height * 0.45;
                const gap = item.x - (previous.x + previousWidth);
                const spaceThreshold = Math.max(1.5, previous.height * 0.2);

                if (gap > spaceThreshold && !text.endsWith(' ') && !item.str.startsWith(' ')) {
                    text += ' ';
                }
            }

            text += item.str;
            previous = item;
        });

        return text.replace(/[ \t]+/g, ' ').trim();
    }).filter(Boolean).join('\n');
}

function readerFailureMessage(error) {
    const data = error?.responseJSON;
    let message = '';

    if (data && typeof data.message === 'string') {
        message = data.message.trim();
    } else if (data?.errors && typeof data.errors === 'object') {
        const first = Object.values(data.errors).flat().find(item => typeof item === 'string');
        if (first) message = first.trim();
    } else if (typeof error?.message === 'string' && !/ajax|network/i.test(error.message)) {
        message = error.message.trim();
    }

    return message.length > 180 ? `${message.slice(0, 177)}...` : message;
}

function summarizeFailedFiles(names) {
    if (!names.length) return '';

    const preview = names.slice(0, 3).join(', ');
    const extra = names.length > 3 ? ` (+${names.length - 3} lainnya)` : '';
    return `${preview}${extra}`;
}

function coverRateLimited(error) {
    return /membatasi permintaan|rate limit/i.test(readerFailureMessage(error));
}

function requestCoverRows(text) {
    const send = () => $.ajax({
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

    return send().catch(error => {
        if (!coverRateLimited(error) || isAiImportOffline()) {
            throw error;
        }

        return new Promise((resolve, reject) => {
            window.setTimeout(() => {
                if (isAiImportOffline()) {
                    reject(error);
                    return;
                }

                send().then(resolve, reject);
            }, 1500);
        });
    });
}

async function importPdfFiles() {
    if (!ensureAiKeyAndModelStored()) {
        return false;
    }

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

    let processed = 0;
    let abortedOffline = false;
    const failedNames = [];
    let failureReason = '';

    const importDate = new Date().toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });

    if (typeof lockProgressConfirm === 'function') {
        lockProgressConfirm();
    }

    const outcomes = new Array(total);
    let cursor = 0;
    let finished = 0;

    const readOne = async (index) => {
        const file = files[index];

        setProgress(
            'Memproses impor',
            'Data impor diproses dan disimpan sementara sebelum dikirim ke basis data.',
            Math.round((finished / total) * 100),
            `Membaca "${file.name}"...`
        );

        const text = await parsePdfFile(file);

        if (isAiImportOffline()) {
            abortedOffline = true;
            return;
        }

        setProgress(
            'Memproses impor',
            'Data impor diproses dan disimpan sementara sebelum dikirim ke basis data.',
            Math.round((finished / total) * 100),
            `Menganalisis "${file.name}" dengan kecerdasan buatan...`
        );

        outcomes[index] = { ok: true, file, rows: await requestCoverRows(text) };
    };

    const worker = async () => {
        while (!abortedOffline) {
            const index = cursor++;

            if (index >= total) {
                return;
            }

            if (isAiImportOffline()) {
                abortedOffline = true;
                return;
            }

            const file = files[index];

            try {
                await readOne(index);
            } catch (error) {
                console.error(error);

                if (isAiImportOffline() || error?.status === 0) {
                    abortedOffline = true;
                    return;
                }

                outcomes[index] = { ok: false, file, error };
            } finally {
                finished += 1;
                const item = outcomes[index];
                const reason = item?.ok === false ? readerFailureMessage(item.error) : '';

                setProgress(
                    'Memproses impor',
                    'Data impor diproses dan disimpan sementara sebelum dikirim ke basis data.',
                    Math.round((finished / total) * 100),
                    item?.ok === false
                        ? (reason
                            ? `Gagal menganalisis "${file.name}". ${reason}`
                            : `Gagal menganalisis "${file.name}". Melanjutkan berkas berikutnya...`)
                        : `Proses ${finished} dari ${total} berkas.`
                );
            }
        }
    };

    try {
        await Promise.all(Array.from(
            { length: Math.min(IMPORT_READERS, total) },
            () => worker()
        ));

        const tableRows = [];
        let rowNumber = 1;

        for (let i = 0; i < total; i++) {
            const item = outcomes[i];

            if (!item) {
                continue;
            }

            if (!item.ok) {
                failedNames.push(item.file.name);
                failureReason = failureReason || readerFailureMessage(item.error);
                continue;
            }

            if (!Array.isArray(item.rows)) {
                failedNames.push(item.file.name);
                failureReason = failureReason || 'Respons analisis tidak valid.';
                continue;
            }

            const id = rowNumber++;
            importFileStore.set(id, item.file);

            item.rows.forEach(row => {
                row.unshift(id);
                row.splice(7, 0, importDate, item.file.name, null);
                row[4] = normalizeImportedStudyProgram(row[4]);

                const degree = normalizeImportedDegreeLevel(row[5]);
                row[5] = degree.level;
                row[20] = degree.typeId;
                tableRows.push(row);
            });

            processed += 1;
        }

        if (tableRows.length) {
            importTable.rows.add(tableRows);
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
                `Berhasil ${processed} dari ${total}. Gagal ${failedNames.length}: ${summarizeFailedFiles(failedNames)}.${failureReason ? ` ${failureReason}` : ''}`
            );
            return processed > 0;
        }

        setProgress(
            'Memproses impor',
            'Data impor diproses dan disimpan sementara sebelum dikirim ke basis data.',
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
