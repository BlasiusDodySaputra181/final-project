/* Peta perawatan — global-setup.js
 * Setup klien (induk): token, peran, DOM, tanggal, barcode, ukuran berkas.
 * CRUD klien (anak): CrudClient.fail dan pilihan baris tabel.
 * Bagian: variabel, grafik, DOM, helper, kesalahan CRUD, tanggal,
 * barcode, ukuran berkas, cetak, detail baris, halaman.
 */
// Global Variables & Helpers
const csrfToken = $('meta[name="csrf-token"]').attr('content');
const currentPath = location.pathname;
const userRole = document.body?.dataset?.userRole || $('meta[name="user-role"]').attr('content') || '';
const authUserId = document.body?.dataset?.userId || $('meta[name="user-id"]').attr('content') || '';
const authUserName = document.body?.dataset?.userName || $('meta[name="user-name"]').attr('content') || '';
const canMutateData = userRole === 'Petugas';
const poppers = {};
let html5QrCode;

const datepickerLocale = {
    months: 'Januari Februari Maret April Mei Juni Juli Agustus September Oktober November Desember'.split(' '),
    monthsShort: 'Jan Feb Mar Apr Mei Jun Jul Agu Sep Okt Nov Des'.split(' '),
    days: 'Minggu Senin Selasa Rabu Kamis Jumat Sabtu'.split(' '),
    daysShort: 'Min Sen Sel Rab Kam Jum Sab'.split(' '),
    daysMin: 'Mg Sn Sl Rb Km Jm Sb'.split(' '),
    dateFormat: 'd MMMM yyyy', today: 'Hari ini', clear: 'Hapus', firstDay: 1
};

// Data grafik diambil dari atribut data pada canvas dasbor (diisi dari database)
const chartSourceCanvas = document.getElementById('chartsbar');
const globalChartData = {
    labels: ['Berlaku', 'Tidak Berlaku'],
    datasets: [{
        data: [
            Number(chartSourceCanvas?.dataset.underTenYears ?? 0),
            Number(chartSourceCanvas?.dataset.overTenYears ?? 0)
        ],
        backgroundColor: ['#2563EB', '#EF4444']
    }]
};

const getDisplayFileName = path =>
    (path?.split('/').pop() || 'Belum ada file')
        .replace(/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\s*-\s*/i, '');

// DOM Elements
const byId = id => document.getElementById(id);
const query = document.querySelector.bind(document);
const queryAll = document.querySelectorAll.bind(document);

const [
    pageLoader, profileButton, sidebarToggle, finalProjectSubmenu, dropdown, finalProjectModal, folderModal, progressFill,
    progressPercent, progressLabel, progressModal, importInformationModal, importModal, sidebar, finalProjectMenu,
    main, heroHeader, heroTitle, barChartCanvas, pieChartCanvas, alertModal, errorModal, successModal, cameraScannerOverlay,
    startCameraScannerButton, stopCameraScannerButton, deviceScannerOverlay, startDeviceScannerButton, stopDeviceScannerButton,
    studyProgramModal, membershipModal, finalProjectTypeModal, visitorModal, bookGrantModal, libraryClearanceModal, barcodeModal, destructionModal, archiveModal, archiveDownloadModal, reportModal, reportDownloadModal, submissionModal, userModal
] = [
    'pageloader', 'profilebtn', 'toggleSidebar', 'submenufinalproject', 'dropdown', 'finalprojectmodal', 'foldermodal', 'progressfill',
    'progresspercent', 'progresslabel', 'progressmodal', 'informationimportmodal', 'importmodal', 'sidebar', 'menufinalproject',
    'main', 'headershero', 'titleshero', 'chartsbar', 'chartspie', 'alertsmodal', 'errorsmodal', 'successmodal', 'camerascanneroverlay',
    'startcamerascanner', 'stopcamerascanner', 'devicescanneroverlay', 'startdevicescanner', 'stopdevicescanner',
    'studyprogrammodal', 'membershipmodal', 'finalprojecttypemodal', 'visitormodal', 'bookgrantmodal', 'libraryclearancemodal', 'barcodemodal', 'destructionmodal', 'archivemodal', 'archivedownloadmodal', 'reportmodal', 'reportdownloadmodal', 'submissionmodal', 'usermodal'
].map(byId);

const cameraScannerStatus = byId('camerascannerstatus');
const deviceScannerStatus = byId('devicescannerstatus');
const deviceScannerConnection = byId('devicescannerconnection');
const deviceScannerInput = byId('devicescannerinput');

// Helper Functions
const formatImportFileSize = file => (file.size / 1048576).toFixed(2);
const formatFileSizeMb = bytes => `${(Number(bytes) / 1048576).toFixed(2)} MB`;

const resolveFileNameNodes = target => {
    if (!target) return [];
    if (target.jquery) return target.get();
    if (target.nodeType) return [target];
    if (typeof target === 'string') return [...document.querySelectorAll(target)];
    if (typeof target.length === 'number') return [...target];
    return [];
};

const renderUploadFileName = (target, name = '', sizeLabel = '', emptyText = 'Belum ada file dipilih') => {
    resolveFileNameNodes(target).forEach(el => {
        const box = el.closest('.upload-box') || el.closest('.upload-area');
        const hasFile = Boolean(name);

        el.classList.toggle('has-file', hasFile);
        box?.classList.toggle('has-file', hasFile);
        box?.style.removeProperty('border-color');

        if (!hasFile) {
            el.textContent = emptyText;
            return;
        }

        fillCheckedFileName(el, name, sizeLabel);
    });
};
const getSelectedByButton = buttonId => {
    try {
        return JSON.parse($( `#${buttonId}` ).attr('data-id') || '{"id":null}');
    } catch {
        return { id: null };
    }
};
const getSelectedFinalProject = () => getSelectedByButton('buttonfinalproject');
const getSelectedStudyProgram = () => getSelectedByButton('buttonstudyprogram');
const getSelectedMembership = () => getSelectedByButton('buttonmembership');
const getSelectedFinalProjectType = () => getSelectedByButton('buttonfinalprojecttype');
const getSelectedVisitor = () => getSelectedByButton('buttonvisitor');
const getSelectedBookGrant = () => getSelectedByButton('buttonbookgrant');
const getSelectedLibraryClearance = () => getSelectedByButton('buttonlibraryclearance');
const getSelectedBarcode = () => getSelectedByButton('buttonbarcode');
const getSelectedDestruction = () => getSelectedByButton('buttondestruction');
const getSelectedArchive = () => getSelectedByButton('buttonarchive');
const getSelectedReport = () => getSelectedByButton('buttonreport');
const getSelectedSubmission = () => getSelectedByButton('buttonsubmission');
const getSelectedUser = () => getSelectedByButton('buttonuser');
const getFinalProjectTypeContext = () => {
    const context = $('#finalprojectcontext');
    if (!context.length) {
        return { id: null, level: null, type: 'Tugas Akhir' };
    }

    return {
        id: context.data('type-id') || null,
        level: context.data('level') || null,
        type: context.data('type') || 'Tugas Akhir'
    };
};
const zipToJson = (keys, values) => JSON.stringify(
    Object.fromEntries(keys.map((key, index) => [key, values[index]]))
);

const escapeHtmlAttr = value => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
    .replace(/</g, '&lt;');

const escapeHtml = value => escapeHtmlAttr(value).replace(/>/g, '&gt;');

const isAppOffline = () =>
    (typeof OfflineSync !== 'undefined' && typeof OfflineSync.isOffline === 'function' && OfflineSync.isOffline())
    || (typeof PwaRegister !== 'undefined' && typeof PwaRegister.isOffline === 'function' && PwaRegister.isOffline())
    || (typeof navigator !== 'undefined' && navigator.onLine === false);

if (typeof $ !== 'undefined' && $.ajaxSetup) {
    $.ajaxSetup({ timeout: 60000 });
}

const firstValidationMessage = json => {
    const errors = json?.errors;
    if (errors && typeof errors === 'object') {
        for (const key of Object.keys(errors)) {
            const value = errors[key];
            if (Array.isArray(value) && value[0]) return String(value[0]);
            if (typeof value === 'string' && value) return value;
        }
    }

    if (typeof json?.message === 'string' && json.message) return json.message;
    if (typeof json === 'string' && json) return json;
    if (typeof json?.error === 'string' && json.error) return json.error;

    return null;
};

const describeXhrError = (xhr, textStatus) => {
    const status = Number(xhr?.status || 0);
    const json = xhr?.responseJSON;
    const timedOut = textStatus === 'timeout' || xhr?.statusText === 'timeout';

    if (status === 0 || timedOut) {
        if (isAppOffline()) {
            return {
                kind: 'offline',
                retryOffline: true,
                title: 'Koneksi terputus.',
                message: 'Perubahan dapat disimpan di perangkat dan dikirim saat online kembali.'
            };
        }

        if (timedOut) {
            return {
                kind: 'timeout',
                retryOffline: false,
                title: 'Koneksi lambat.',
                message: 'Server tidak merespons tepat waktu. Periksa jaringan, lalu coba lagi.'
            };
        }

        return {
            kind: 'network',
            retryOffline: true,
            title: 'Jaringan bermasalah.',
            message: 'Tidak dapat terhubung ke peladen. Data dapat antre secara luring.'
        };
    }

    if (status === 413) {
        return {
            kind: 'payload',
            title: 'Berkas terlalu besar.',
            message: firstValidationMessage(json) || 'Perkecil ukuran unggahan, lalu coba lagi.'
        };
    }

    if (status === 419) {
        return {
            kind: 'csrf',
            title: 'Sesi berakhir.',
            message: firstValidationMessage(json) || 'Muat ulang halaman, lalu masuk kembali.'
        };
    }

    if (status === 422) {
        return {
            kind: 'validation',
            title: 'Data tidak valid.',
            message: firstValidationMessage(json) || 'Periksa isian formulir, lalu coba lagi.'
        };
    }

    if (status === 429) {
        return {
            kind: 'throttle',
            title: 'Terlalu banyak percobaan.',
            message: 'Tunggu sebentar, lalu coba lagi.'
        };
    }

    if (status === 404) {
        return {
            kind: 'missing',
            title: 'Data tidak ditemukan.',
            message: firstValidationMessage(json) || 'Data mungkin sudah dihapus.'
        };
    }

    if (status >= 500) {
        return {
            kind: 'server',
            title: 'Server sedang bermasalah.',
            message: firstValidationMessage(json) || 'Coba beberapa saat lagi.'
        };
    }

    return {
        kind: 'error',
        title: 'Gagal memproses.',
        message: firstValidationMessage(json) || 'Terjadi kesalahan. Coba beberapa saat lagi.'
    };
};


// --- Kesalahan permintaan CRUD ---
const handleCrudRequestError = async (xhr, options = {}) => {
    const { queueOffline, failTitle, textStatus } = options;
    const info = describeXhrError(xhr, textStatus);

    if (info.retryOffline && typeof queueOffline === 'function') {
        try {
            await queueOffline();
        } catch (error) {
            if (typeof showError === 'function') {
                showError(
                    failTitle || 'Gagal menyimpan secara luring.',
                    error?.message || 'Penyimpanan di perangkat gagal. Izinkan penyimpanan situs, lalu coba lagi.'
                );
            }
        }
        return;
    }

    if (typeof showError === 'function') {
        showError(failTitle || info.title, info.message);
    }

    if (info.kind === 'csrf') {
        window.setTimeout(() => window.location.reload(), 1600);
    }
};

const parseDateValue = value => {
    const raw = String(value || '').trim();
    if (!raw || raw === '-') return null;

    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        const [year, month, day] = raw.split('-').map(Number);
        const date = new Date(year, month - 1, day);
        return Number.isNaN(date.getTime()) ? null : date;
    }

    const localized = raw.match(/^(\d{1,2})\s+([A-Za-z.]+)\s+(\d{4})$/);
    if (localized) {
        const day = Number(localized[1]);
        const monthName = localized[2].replace(/\.$/, '').toLowerCase();
        const names = [...datepickerLocale.months, ...datepickerLocale.monthsShort];
        const found = names.findIndex(name => name.toLowerCase() === monthName);
        if (found >= 0) {
            const date = new Date(Number(localized[3]), found % 12, day);
            return Number.isNaN(date.getTime()) ? null : date;
        }
    }

    const parsed = new Date(raw);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const selectDatepickerDate = (picker, value) => {
    if (!picker) return;
    const date = parseDateValue(value);
    if (date) picker.selectDate(date);
    else picker.clear();
};

const splitPlaceDateOfBirth = value => {
    const raw = String(value || '').trim();
    if (!raw) return { place: '', date: '' };

    const slash = raw.match(/^(.*?)\s+\/\s+(.+)$/);
    if (slash) {
        return { place: slash[1].trim(), date: slash[2].trim() };
    }

    const comma = raw.match(/^(.*?),\s*(\d{1,2}\s+[A-Za-z.]+\s+\d{4})$/);
    if (comma) {
        return { place: comma[1].trim(), date: comma[2].trim() };
    }

    return { place: raw, date: '' };
};

const composeMembershipPlaceDateOfBirth = (place, dateDisplay) => {
    const trimmedPlace = String(place || '').trim();
    const trimmedDate = String(dateDisplay || '').trim();
    if (!trimmedPlace || !trimmedDate) return '';
    return `${trimmedPlace} / ${trimmedDate}`;
};

document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.dataset.skipSubmitLock === '1') return;
    if (form.dataset.submitLock === '1') {
        event.preventDefault();
        event.stopImmediatePropagation();
        return;
    }
    form.dataset.submitLock = '1';
    window.setTimeout(() => {
        if (form.dataset.submitLock === '1') form.dataset.submitLock = '0';
    }, 8000);
}, true);

$(document).ajaxComplete(() => {
    document.querySelectorAll('form[data-submit-lock="1"]').forEach(form => {
        form.dataset.submitLock = '0';
    });
});


// --- Barcode Code 128 ---
const renderCode128Markup = (code, options = {}) => {
    if (!code) return '-';

    return markup(barcodeSvg(code, {
        height: options.height ?? 40,
        displayValue: options.displayValue !== false
    }));
};

const paintCode128Barcodes = (root = document) => {
    if (typeof JsBarcode === 'undefined') return;

    root.querySelectorAll('svg.barcode-code128[data-barcode]').forEach(svg => {
        const code = svg.getAttribute('data-barcode');
        if (!code) return;

        try {
            JsBarcode(svg, code, {
                format: 'CODE128',
                lineColor: '#0f172a',
                width: 2,
                height: Number(svg.getAttribute('data-height') || 48),
                displayValue: svg.getAttribute('data-display-value') !== '0',
                fontSize: 12,
                textMargin: 4,
                margin: 10
            });
        } catch {
            svg.replaceWith(document.createTextNode(code));
        }
    });
};

// Unduh PNG Code128 agar bisa dipindai ulang lewat html5-qrcode
const downloadCode128Barcode = code => {
    const barcode = String(code || '').trim();
    if (!barcode || typeof JsBarcode === 'undefined') return false;

    const canvas = document.createElement('canvas');

    try {
        JsBarcode(canvas, barcode, {
            format: 'CODE128',
            lineColor: '#000000',
            background: '#ffffff',
            width: 2,
            height: 80,
            displayValue: true,
            fontSize: 14,
            textMargin: 6,
            margin: 12
        });
    } catch {
        return false;
    }

    const link = document.createElement('a');
    link.href = canvas.toDataURL('image/png');
    link.download = `${barcode}.png`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    return true;
};

// Cache ukuran file agar path yang sama tidak memicu request berulang

// --- Ukuran berkas tersimpan ---
const fileSizeCache = new Map();
const fileSizeInflight = new Map();

const paintFileSize = (path, size) => {
    if (!path) return;
    const escaped = (typeof CSS !== 'undefined' && CSS.escape) ? CSS.escape(path) : String(path).replace(/"/g, '\\"');
    document.querySelectorAll(`[data-file-size-path="${escaped}"]`).forEach(el => {
        el.textContent = size;
    });
};

const requestStoredFileSize = path => {
    if (!path) return Promise.resolve('0.00');
    if (fileSizeCache.has(path)) return Promise.resolve(fileSizeCache.get(path));
    if (fileSizeInflight.has(path)) return fileSizeInflight.get(path);

    if (isAppOffline()) {
        fileSizeCache.set(path, '—');
        paintFileSize(path, '—');
        return Promise.resolve('—');
    }

    const request = $.ajax({
        url: '/showfilesize',
        type: 'POST',
        timeout: 15000,
        data: { _token: csrfToken, files: path }
    });

    request.done(json => {
        const size = json?.size_mb ?? '0.00';
        fileSizeCache.set(path, size);
        paintFileSize(path, size);
    }).fail(() => {
        fileSizeCache.set(path, '0.00');
        paintFileSize(path, '0.00');
    }).always(() => {
        fileSizeInflight.delete(path);
    });

    const wrapped = request.then(
        () => fileSizeCache.get(path) ?? '0.00',
        () => fileSizeCache.get(path) ?? '0.00'
    );
    fileSizeInflight.set(path, wrapped);
    return wrapped;
};

const getStoredFileSize = path => {
    if (!path) return '0.00';
    if (fileSizeCache.has(path)) return fileSizeCache.get(path);
    requestStoredFileSize(path);
    return '…';
};


// --- Cetak dan ekspor tabel ---
const DATATABLES_EXPORT_PAGE = 200;
const DATATABLES_EXPORT_MAX = 2000;

const resolveDataTablesAjaxUrl = (table, options = {}) => {
    if (options.url) return options.url;

    try {
        const fromApi = typeof table.ajax?.url === 'function' ? table.ajax.url() : table.ajax?.url;
        if (fromApi) return fromApi;
    } catch (_) { /* ajax function source has no URL */ }

    try {
        const ajax = table.settings?.()[0]?.ajax;
        if (typeof ajax === 'string' && ajax) return ajax;
        if (ajax && typeof ajax === 'object' && ajax.url) return ajax.url;
    } catch (_) { /* ignore */ }

    return '';
};

const fetchAllTableRows = async (table, options = {}) => {
    if (!table) return [];

    if (isAppOffline()) {
        if (options.indexKey && typeof OfflineDatabase !== 'undefined') {
            try {
                const cached = await OfflineDatabase.getIndexRows(options.indexKey);
                if (Array.isArray(cached) && cached.length) return cached;
            } catch (_) { /* fallback ke halaman aktif */ }
        }
        return table.rows().data().toArray();
    }

    const url = resolveDataTablesAjaxUrl(table, options);
    if (!url) {
        return table.rows().data().toArray();
    }

    let lastParams = {};
    try {
        lastParams = typeof table.ajax.params === 'function' ? (table.ajax.params() || {}) : {};
    } catch (_) { /* ajax function source may belum punya params */ }
    const info = table.page.info();
    const pageSize = DATATABLES_EXPORT_PAGE;
    const rows = [];
    let total = Number(info?.recordsDisplay ?? 0);
    let knownTotal = total > 0;
    let warnedCap = false;

    for (let start = 0; start < DATATABLES_EXPORT_MAX; start += pageSize) {
        if (knownTotal && start >= Math.min(total, DATATABLES_EXPORT_MAX)) break;

        const data = Object.assign({}, lastParams, {
            start,
            length: pageSize,
            draw: 1,
            export: 1
        });

        try {
            const json = await $.ajax({ url, data, dataType: 'json', cache: false, timeout: 60000 });
            const chunk = Array.isArray(json) ? json : (json?.data ?? []);
            rows.push(...chunk);

            if (!knownTotal && json && !Array.isArray(json)) {
                total = Number(json.recordsFiltered ?? json.recordsTotal ?? chunk.length);
                knownTotal = true;
            }

            if (!warnedCap && knownTotal && total > DATATABLES_EXPORT_MAX && typeof showError === 'function') {
                warnedCap = true;
                showError(
                    'Ekspor dibatasi.',
                    `Hanya ${DATATABLES_EXPORT_MAX} baris pertama yang diambil agar perangkat tidak kehabisan memori.`
                );
            }

            if (chunk.length < pageSize) break;
        } catch (_) {
            if (!rows.length && typeof showError === 'function') {
                showError('Gagal mengekspor data.', 'Koneksi lambat atau terputus. Coba lagi.');
            }
            break;
        }
    }

    return rows;
};

const openPrintPreviewWindow = () => window.open('/cetak/pratinjau', '_blank');

const closePrintPreviewWindow = tab => {
    if (!tab || tab.closed) return;
    try { tab.close(); } catch (_) { /* ignore */ }
};

const exportTableAndPrint = async ({
    button,
    table,
    options,
    emptyTitle,
    emptyMessage,
    failMessage = 'Tidak dapat memuat seluruh data. Coba lagi.',
    open
}) => {
    if (button.dataset.busy === '1') return;
    button.dataset.busy = '1';

    const tab = openPrintPreviewWindow();

    try {
        const rows = await fetchAllTableRows(table, options);

        if (rows.length === 0) {
            closePrintPreviewWindow(tab);
            return showError(emptyTitle, emptyMessage);
        }

        open(rows, tab);
    } catch (error) {
        closePrintPreviewWindow(tab);
        showError(emptyTitle, failMessage);
    } finally {
        button.dataset.busy = '0';
    }
};

const fileDownloadItem = (label, routePath, rowId, filePath) => fileDetailItem(label, {
    href: filePath ? `/${routePath}/download/${rowId}` : '#',
    className: filePath ? '' : 'detail-file-download',
    fileName: getDisplayFileName(filePath),
    filePath: filePath || ''
});


// --- Isi detail baris ---
const renderFinalProjectDetail = row => detailMarkup([
    ...[
        ['NIM', 2], ['Nama Mahasiswa', 3], ['Judul Tugas Akhir', 4], ['Program Studi', 5],
        ['Jenjang', 6], ['Tahun Tugas Akhir', 7], ['Tanggal Penyerahan', 8], ['Status', 9],
        ['Tanggal Dibuat', 12], ['Tanggal Diubah', 13]
    ].map(([label, index]) => [label, row[index] ?? '']),
    fileDownloadItem('Berkas Tugas Akhir', 'finalproject', row[1], row[10]),
    fileDownloadItem('Lembar Persetujuan', 'approvalsheet', row[1], row[11])
]);

const renderStudyProgramDetail = row => indexedDetail(row, [
    ['Program Studi', 2], ['Rumpun Ilmu', 3], ['Tanggal Dibuat', 4], ['Tanggal Diubah', 5]
]);

const renderMembershipDetail = row => detailMarkup([
    ['NIM', row[2] || '-'],
    ['Nama Mahasiswa', row[3] || '-'],
    ['Rumpun Ilmu', row[13] || '-'],
    ['Program Studi', row[4] || '-'],
    ['Jenis Kelamin', row[5] || '-'],
    ['Tempat Lahir', row[16] || '-'],
    ['Tanggal Lahir', row[6] || '-'],
    ['Alamat Asal', row[7] || '-'],
    ['Alamat Sekarang', row[8] || '-'],
    ['Hp/Telpon', row[14] || '-'],
    ['E-mail', row[15] || '-'],
    ['Tanggal Dibuat', row[9] || '-'],
    ['Tanggal Diubah', row[10] || '-']
], 'detail-grid detail-grid--membership');

const renderFinalProjectTypeDetail = row => indexedDetail(row, [
    ['Jenjang', 2], ['Jenis', 3], ['Tanggal Dibuat', 4], ['Tanggal Diubah', 5]
]);

const renderVisitorDetail = row => indexedDetail(row, [
    ['NIM', 2], ['Nama Mahasiswa', 3], ['Program Studi', 4], ['Keperluan', 5],
    ['Pengawasan', 6], ['Tanggal Dibuat', 7], ['Tanggal Diubah', 8]
]);

const renderBookGrantDetail = row => indexedDetail(row, [
    ['NIM', 2], ['Nama Mahasiswa', 3], ['Jurusan / Rumpun', 4], ['Program Studi', 5],
    ['Jenjang', 6], ['Terdaftar Keanggotaan', 7], ['Tanggal Penyerahan', 8], ['Judul Buku', 9],
    ['Tahun Buku', 10], ['Pengarang', 11], ['Tanggal Dibuat', 12], ['Tanggal Diubah', 13]
]);

const renderLibraryClearanceDetail = row => indexedDetail(row, [
    ['NIM', 2], ['Nama Mahasiswa', 3], ['Jurusan / Rumpun', 4], ['Program Studi', 5],
    ['Jenjang', 6], ['Terdaftar Keanggotaan', 7], ['Email', 8], ['Nomor Telepon', 9],
    ['Tahun Lulus', 10], ['Semester Lulus', 11], ['Tanggal Dibuat', 12], ['Tanggal Diubah', 13]
]);

const renderBarcodeDetail = row => {
    const code = String(row[13] ?? '').trim();
    const link = document.createElement('a');
    link.href = '#';
    link.className = code ? 'detail-barcode-download' : 'detail-file-download';
    if (code) link.dataset.barcode = code;
    link.textContent = code || 'Belum ada kode batang';

    const content = document.createElement('span');
    content.append(faIcon('fa-download'), document.createTextNode(' '), link);

    return detailMarkup([
        ...[
            ['NIM', 2], ['Nama Mahasiswa', 3], ['Judul Tugas Akhir', 4], ['Program Studi', 5],
            ['Jenjang', 6], ['Tahun Tugas Akhir', 7], ['Tahun Lulus', 8], ['Semester Lulus', 9],
            ['Tanggal Penyerahan', 10], ['Status', 11], ['Label Kode Batang', 12]
        ].map(([label, index]) => detailItem(label, row[index] ?? '-')),
        detailItem('Kode Batang', content),
        ...[['Tanggal Dibuat', 14], ['Tanggal Diubah', 15]].map(([label, index]) => detailItem(label, row[index] ?? '-'))
    ]);
};

const renderDestructionDetail = row => indexedDetail(row, [
    ['NIM', 2], ['Nama Mahasiswa', 3], ['Judul Tugas Akhir', 4], ['Program Studi', 5],
    ['Jenjang', 6], ['Tahun Tugas Akhir', 7], ['Tanggal Penyerahan', 8], ['Status', 9],
    ['Keterangan', 10], ['Penyetuju', 12], ['Tanggal Dibuat', 13], ['Tanggal Diubah', 14]
]);

const renderArchiveDetail = row => indexedDetail(row, [
    ['NIM', 2], ['Nama Mahasiswa', 3], ['Judul Tugas Akhir', 4], ['Program Studi', 5],
    ['Jenjang', 6], ['Tahun Tugas Akhir', 7], ['Tanggal Penyerahan', 8], ['Status', 9],
    ['Batas Waktu', 10], ['Keterangan', 11], ['Tanggal Dibuat', 12], ['Tanggal Diubah', 13]
]);

const printAlignCenter = { align: 'center', nowrap: true };
const printAlignDate = { align: 'center', nowrap: true, type: 'date' };
const visitorPrintColumns = [
    ['No.', 0, { width: '4%', align: 'center', nowrap: true }],
    ['NIM', 2, { width: '12%', align: 'center', nowrap: true }],
    ['Nama Mahasiswa', 3, { width: '18%' }],
    ['Program Studi', 4, { width: '16%' }],
    ['Keperluan', 5, { width: '20%' }],
    ['Pengawasan', 6, { width: '10%', align: 'center' }],
    ['Tgl Dibuat', 7, { width: '10%', ...printAlignDate }],
    ['Tgl Diubah', 8, { width: '10%', ...printAlignDate }]
];

const archivePrintColumns = [
    ['No.', 0, { width: '3%', align: 'center', nowrap: true }],
    ['NIM', 2, { width: '8%', align: 'center', nowrap: true }],
    ['Nama Mahasiswa', 3, { width: '11%' }],
    ['Judul Tugas Akhir', 4, { width: '15%' }],
    ['Program Studi', 5, { width: '10%' }],
    ['Jenjang', 6, { width: '6%', ...printAlignCenter }],
    ['Tahun TA', 7, { width: '5.5%', ...printAlignCenter }],
    ['Tgl Penyerahan', 8, { width: '8%', ...printAlignDate }],
    ['Status', 9, { width: '6.5%', align: 'center' }],
    ['Batas Waktu', 10, { width: '8%', align: 'center' }],
    ['Keterangan', 11, { width: '7%' }],
    ['Tgl Dibuat', 12, { width: '6%', ...printAlignDate }],
    ['Tgl Diubah', 13, { width: '6%', ...printAlignDate }]
];

const reportAlignCenter = { align: 'center' };
const reportAlignDate = { align: 'center', type: 'date', wrapDate: true };

const reportPrintColumns = [
    ['No.', 0, { width: '2.4%', align: 'center' }],
    ['NIM', 2, { width: '5.6%', align: 'center' }],
    ['Nama', 3, { width: '6.1%' }],
    ['Judul Tugas Akhir', 4, { width: '8.5%' }],
    ['Program Studi', 5, { width: '6.5%' }],
    ['Jenjang', 6, { width: '4.2%', ...reportAlignCenter }],
    ['Tahun TA', 7, { width: '3.8%', ...reportAlignCenter }],
    ['Tahun Lulus', 8, { width: '4.2%', ...reportAlignCenter }],
    ['Semester', 9, { width: '4.2%', align: 'center' }],
    ['Tanggal Penyerahan', 10, { width: '5.5%', ...reportAlignDate }],
    ['Status', 11, { width: '4.2%', align: 'center' }],
    ['Kode Batang', 12, { width: '5.6%', align: 'center' }],
    ['Hibah', 13, { width: '3.8%', align: 'center' }],
    ['Bebas Pustaka', 14, { width: '4.8%', align: 'center' }],
    ['Batas Waktu', 15, { width: '5.2%', align: 'center' }],
    ['Ket. Arsip', 16, { width: '5.2%' }],
    ['Ket. Laporan', 17, { width: '5.6%' }],
    ['Editor', 18, { width: '4.6%' }],
    ['Tanggal Dibuat', 19, { width: '5%', ...reportAlignDate }],
    ['Tanggal Diubah', 20, { width: '5%', ...reportAlignDate }]
];

const getTableRowsOrEmpty = table => {
    if (typeof table === 'undefined' || !table) {
        return [];
    }

    return table.rows().data().toArray();
};

const getArchiveTableRows = () => getTableRowsOrEmpty(typeof archiveTable === 'undefined' ? null : archiveTable);
const getReportTableRows = () => getTableRowsOrEmpty(typeof reportTable === 'undefined' ? null : reportTable);
const getVisitorTableRows = () => getTableRowsOrEmpty(typeof visitorTable === 'undefined' ? null : visitorTable);

const openDataPrintTab = payload => {
    if (typeof publishPrintPreview !== 'function') {
        if (typeof showError === 'function') {
            showError(payload?.errorTitle || 'Gagal membuka pratinjau.', 'Pratinjau cetak belum dimuat.');
        }
        return;
    }

    publishPrintPreview(payload);
};

const openArchivePrintTab = (rows, studyProgram = null, targetWindow = null) => {
    openDataPrintTab({
        title: 'Daftar Arsip Tugas Akhir',
        subtitle: studyProgram ? `Program Studi: ${studyProgram}` : 'Semua Program Studi',
        emptyLabel: 'Tidak ada data arsip.',
        errorTitle: 'Gagal membuka arsip.',
        columns: archivePrintColumns,
        rows,
        targetWindow
    });
};

const openReportPrintTab = (rows, finalProjectYear = null, targetWindow = null) => {
    openDataPrintTab({
        title: 'Daftar Laporan Tugas Akhir',
        subtitle: finalProjectYear ? `Tahun Tugas Akhir: ${finalProjectYear}` : 'Semua Tahun Tugas Akhir',
        emptyLabel: 'Tidak ada data laporan.',
        errorTitle: 'Gagal membuka laporan.',
        columns: reportPrintColumns,
        rows,
        targetWindow,
        wrapCells: true
    });
};

const openVisitorPrintTab = (rows, targetWindow = null) => {
    openDataPrintTab({
        title: 'Daftar Pengunjung',
        subtitle: 'Semua Pengunjung',
        emptyLabel: 'Tidak ada data pengunjung.',
        errorTitle: 'Gagal membuka pengunjung.',
        columns: visitorPrintColumns,
        rows,
        targetWindow
    });
};

const renderReportDetail = row => indexedDetail(row, [
    ['NIM', 2], ['Nama Mahasiswa', 3], ['Judul Tugas Akhir', 4], ['Program Studi', 5],
    ['Jenjang', 6], ['Tahun Tugas Akhir', 7], ['Tahun Lulus', 8], ['Semester Lulus', 9],
    ['Tanggal Penyerahan', 10], ['Status', 11], ['Kode Batang', 12], ['Hibah Buku', 13],
    ['Bebas Pustaka', 14], ['Batas Waktu', 15], ['Keterangan Arsip', 16], ['Keterangan Laporan', 17],
    ['Editor', 18], ['Tanggal Dibuat', 19], ['Tanggal Diubah', 20]
]);

const renderSubmissionDetail = row => detailMarkup([
    ...[
        ['NIM', 2], ['Nama Mahasiswa', 3], ['Judul Tugas Akhir', 4], ['Program Studi', 5],
        ['Jenjang', 6], ['Tahun Tugas Akhir', 7], ['Tanggal Penyerahan', 8], ['Status', 9],
        ['Tanggal Dibuat', 12], ['Tanggal Diubah', 13]
    ].map(([label, index]) => [label, row[index] ?? '']),
    fileDownloadItem('Berkas Tugas Akhir', 'finalproject', row[1], row[10]),
    fileDownloadItem('Lembar Persetujuan', 'approvalsheet', row[1], row[11])
]);

const renderUserDetail = row => {
    const fields = [
        ['NIP / NIM', row[2] ?? '-'],
        ['Nama', row[3] ?? '-'],
        ['Email', row[4] ?? '-'],
        ['Peran', row[5] ?? '-'],
        ['Tanggal Dibuat', row[6] ?? '-'],
        ['Tanggal Diubah', row[7] ?? '-']
    ];

    if (row[5] === 'Mahasiswa') {
        fields.push(['Akses Penyerahan', Number(row[8] ?? 0) !== 0 ? 'Terbuka' : 'Tertutup']);
        fields.push(['Akses Anggota', Number(row[9] ?? 0) !== 0 ? 'Terbuka' : 'Tertutup']);
    }

    return detailMarkup(fields);
};

const renderImportDetail = row => {
    const fields = [
        ['NIM', 1], ['Nama Mahasiswa', 2], ['Judul Tugas Akhir', 3], ['Program Studi', 4],
        ['Jenjang', 5], ['Tahun Tugas Akhir', 6], ['Tanggal Penyerahan', 7]
    ].map(([label, index]) => [label, row[index] ?? '']);

    const files = [
        ['Berkas Tugas Akhir', 'finalproject', 8],
        ['Lembar Persetujuan', 'approvalsheet', row[9] ? 9 : null]
    ].map(([label, routePath, index]) => {
        let file = null;
        let filePath = null;

        if (index === 8) {
            file = importFileStore.get(row[0]);
            filePath = row[8];
        } else if (index === 9) {
            file = approvalSheetStore.get(row[0]);
            filePath = row[9];
        }

        const fileName = file?.name ?? filePath?.split('/').pop() ?? 'Belum ada file';

        if (file) {
            return fileDetailItem(label, {
                href: '#',
                className: 'import-file-download',
                fileName,
                plainSize: formatImportFileSize(file),
                dataset: { row: row[0], type: routePath }
            });
        }

        if (filePath) {
            return fileDetailItem(label, {
                href: `import/${routePath}/download/${encodeURIComponent(filePath)}`,
                fileName,
                filePath
            });
        }

        return fileDetailItem(label, {
            href: '#',
            className: 'detail-file-download',
            fileName,
            plainSize: '0.00'
        });
    });

    return detailMarkup([...fields, ...files]);
};

// Cocokkan path akhir agar tetap bekerja di subdirectory Laragon
const isCurrentPage = page => {
    const path = (currentPath || '').replace(/\/+$/, '') || '/';
    return path === page || path.endsWith(page);
};

// Page Setup & Hero Content
$(document).ready(function() {
    const typeContext = getFinalProjectTypeContext();

    if (typeContext.id) {
        $('#finalprojectformtitle').text(`Formulir ${typeContext.type}`);
        $('#finalprojectformsubtitle').text(
            `Lengkapi data akademik dan unggah dokumen ${String(typeContext.type).toLowerCase()}.`
        );
    }

    const clearActiveNav = () => query('.active')?.classList.remove('active');
    const activateNavByPath = (selector, pagePath) => {
        clearActiveNav();
        queryAll(selector).forEach(link => {
            const linkPath = (link.pathname || '').replace(/\/+$/, '');
            if (linkPath === pagePath || linkPath.endsWith(pagePath)) {
                link.classList.add('active');
            }
        });
    };

    if (isCurrentPage('/dasbor')) {
        activateNavByPath('.menu>a', '/dasbor');
        showHero('Dasbor Tugas Akhir', 'Aplikasi pengelolaan arsip digital FMIPA Universitas Negeri Jakarta. Monitoring data skripsi, tesis, disertasi, hibah buku, dan layanan bebas pustaka dalam satu dasbor.');
    } else if (currentPath.includes('/tugas-akhir/') || isCurrentPage('/tesis')) {
        clearActiveNav();
        finalProjectMenu?.classList.add('open', 'active');
        finalProjectSubmenu?.classList.add('show');
        queryAll('#submenufinalproject a').forEach(link => {
            const linkPath = link.pathname?.replace(/\/+$/, '') || '';
            if (linkPath && (currentPath === linkPath || currentPath.endsWith(linkPath))) {
                link.classList.add('active');
            }
        });
        showHero(
            typeContext.type,
            `Pendataan ${String(typeContext.type).toLowerCase()} digital mahasiswa FMIPA Universitas Negeri Jakarta.`
        );
    } else if (isCurrentPage('/penyerahan')) {
        activateNavByPath('.module-nav-container>a', '/penyerahan');
        showHero('Penyerahan', 'Pendataan penyerahan tugas akhir digital mahasiswa FMIPA Universitas Negeri Jakarta.');
    } else if (isCurrentPage('/pengunjung')) {
        activateNavByPath('.module-nav-container>a', '/pengunjung');
        showHero('Pengunjung', 'Pendataan pengunjung perpustakaan FMIPA Universitas Negeri Jakarta.');
    } else if (isCurrentPage('/pemusnahan')) {
        activateNavByPath('.module-nav-container>a', '/pemusnahan');
        showHero('Pemusnahan', 'Pendataan pemusnahan tugas akhir yang tidak berlaku di FMIPA Universitas Negeri Jakarta.');
    } else if (isCurrentPage('/bebaspustaka')) {
        activateNavByPath('.module-nav-container>a', '/bebaspustaka');
        showHero('Bebas Pustaka', 'Pendataan bebas pustaka mahasiswa FMIPA Universitas Negeri Jakarta.');
    } else if (isCurrentPage('/hibahbuku')) {
        activateNavByPath('.module-nav-container>a', '/hibahbuku');
        showHero('Hibah Buku', 'Pendataan hibah buku tugas akhir mahasiswa FMIPA Universitas Negeri Jakarta.');
    } else if (isCurrentPage('/kodebatang')) {
        activateNavByPath('.menu>a', '/kodebatang');
        showHero('Kode Batang', 'Pendataan kode batang tugas akhir mahasiswa FMIPA Universitas Negeri Jakarta.');
    } else if (isCurrentPage('/arsip')) {
        activateNavByPath('.menu>a', '/arsip');
        showHero('Arsip', 'Pendataan arsip tugas akhir mahasiswa FMIPA Universitas Negeri Jakarta.');
    } else if (isCurrentPage('/laporan')) {
        activateNavByPath('.menu>a', '/laporan');
        showHero('Laporan', 'Pendataan laporan tugas akhir mahasiswa FMIPA Universitas Negeri Jakarta.');
    } else if (isCurrentPage('/impor')) {
        activateNavByPath('.module-nav-container>a', '/impor');
        showHero('Impor Tugas Akhir', 'Pendataan impor tugas akhir digital mahasiswa FMIPA Universitas Negeri Jakarta.');
    } else if (isCurrentPage('/prodi')) {
        activateNavByPath('.module-nav-container>a', '/prodi');
        showHero('Program Studi', 'Pendataan program studi FMIPA Universitas Negeri Jakarta.');
    } else if (isCurrentPage('/keanggotaan')) {
        activateNavByPath('.module-nav-container>a', '/keanggotaan');
        showHero('Keanggotaan', 'Pendataan keanggotaan perpustakaan mahasiswa FMIPA Universitas Negeri Jakarta.');
    } else if (isCurrentPage('/jenista')) {
        activateNavByPath('.module-nav-container>a', '/jenista');
        showHero('Jenis Tugas Akhir', 'Pendataan jenis tugas akhir FMIPA Universitas Negeri Jakarta.');
    } else if (isCurrentPage('/pengguna')) {
        activateNavByPath('.module-nav-container>a', '/pengguna');
        showHero('Pengguna', 'Pendataan pengguna sistem perpustakaan FMIPA Universitas Negeri Jakarta.');
    } else if (isCurrentPage('/profil')) {
        clearActiveNav();
        const profileHeroTitle = (userRole === 'Petugas' || userRole === 'Koordinator')
            ? 'Profil Pengguna'
            : 'Pengaturan Profil';
        showHero(profileHeroTitle, 'Perbarui data akun Anda pada sistem arsip tugas akhir FMIPA Universitas Negeri Jakarta.');
    } else if (isCurrentPage('/pengaturan')) {
        clearActiveNav();
        document.getElementById('menusetting')?.classList.add('active');
        showHero('Pengaturan', 'Tentukan folder penyimpanan berkas tugas akhir dan lembar persetujuan. Setelah lokasi dipilih, berkas tersusun di ARSIP TUGAS AKHIR menurut jenis, tahun, rumpun ilmu, dan program studi. Hanya folder yang berisi berkas yang disimpan. Atur juga akses pengunjung, penyerahan, dan kode batang untuk seluruh mahasiswa.');
    }
});

const showHero = (header, paragraph) => {
    if (!heroHeader || !heroTitle) return;
    heroHeader.textContent = header;
    heroTitle.textContent = paragraph;
};

function refreshOpenedImportDetails() {
    importTable.rows().every(function () {

        const tableRow = $(this.node());

        if (this.child.isShown()) {
            this.child(renderImportDetail(this.data())).show();
            tableRow.addClass('shown');
            tableRow.find('td.dt-control i')
                .removeClass('fa-chevron-right')
                .addClass('fa-chevron-down');
        } else {
            tableRow.removeClass('shown');
            tableRow.find('td.dt-control i')
                .removeClass('fa-chevron-down')
                .addClass('fa-chevron-right');
        }
    });
}

function setProgress(header, paragraph, percent, text, extras) {
    showProgress(header, paragraph);

    const safePercent = Math.max(0, Math.min(100, Number(percent) || 0));
    const rounded = Math.round(safePercent);

    if (progressFill) {
        progressFill.style.width = rounded + '%';
        progressFill.classList.toggle('is-active', rounded < 100);
        progressFill.classList.toggle('is-complete', rounded >= 100);
    }

    if (progressPercent) {
        progressPercent.textContent = rounded + '%';
    }

    if (progressLabel) {
        progressLabel.textContent = text;
    }

    const bar = document.getElementById('progressbar') || progressFill?.parentElement;
    bar?.setAttribute('aria-valuenow', String(rounded));

    const details = typeof extras === 'string'
        ? { status: extras }
        : (extras && typeof extras === 'object' ? extras : null);

    const statusEl = document.getElementById('progressstatus');
    if (statusEl && typeof details?.status === 'string') {
        statusEl.textContent = details.status;
    }

    const metaEl = document.getElementById('progressmeta');
    if (metaEl) {
        if (details?.meta) {
            metaEl.hidden = false;
            metaEl.textContent = details.meta;
        } else if (details && Object.prototype.hasOwnProperty.call(details, 'meta')) {
            metaEl.hidden = true;
            metaEl.textContent = '';
        }
    }

    const fileEl = document.getElementById('progressfile');
    if (fileEl) {
        if (details?.file) {
            fileEl.hidden = false;
            fileEl.textContent = details.file;
            fileEl.title = details.file;
        } else if (details && Object.prototype.hasOwnProperty.call(details, 'file')) {
            fileEl.hidden = true;
            fileEl.textContent = '';
            fileEl.removeAttribute('title');
        }
    }
}

let editedImportRow = null;

class SetupClient {
    token() {
        return csrfToken;
    }

    role() {
        return userRole;
    }

    canMutate() {
        return canMutateData;
    }

    offline() {
        return isAppOffline();
    }

    escape(value) {
        return escapeHtml(value);
    }
}

class CrudClient extends SetupClient {
    fail(...args) {
        return handleCrudRequestError(...args);
    }

    selected(buttonId) {
        return getSelectedByButton(buttonId);
    }
}

const crudClient = new CrudClient();
