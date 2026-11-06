/* Peta perawatan — component-table.js
 * Lebar kolom, lalu satu blok DataTable per modul.
 * Cari: #finalprojecttable, #studyprogramtable, #membershiptable,
 * #finalprojecttypetable, #visitortable, #bookgranttable,
 * #libraryclearancetable, #barcodetable, #destructiontable,
 * #archivetable, #reporttable, #submissiontable, #usertable, #importtable.
 */
let finalProjectTable;
let importTable;
let studyProgramTable;
let finalProjectTypeTable;
let visitorTable;
let bookGrantTable;
let libraryClearanceTable;
let barcodeTable;
let destructionTable;
let archiveTable;
let reportTable;
let submissionTable;
let userTable;
let membershipTable;

/** Kelas lebar kolom sesuai konteks data (judul lebar, NIM terbatas, dst.). */
const columnRoleClass = column => {
    const className = String(column.className || '');
    const name = String(column.name || '');
    const title = typeof column.title === 'string' ? column.title : '';
    const titleText = title.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();

    if (className.includes('dt-control')) return 'dt-col-control';
    if (/checkall|approveall|superviseall/i.test(title)) return 'dt-col-check';
    if (titleText === 'Pengaturan' || className.includes('col-action')) return 'dt-col-actions';
    if (titleText === 'No.' || titleText === 'No') return 'dt-col-no';
    if (name === 'nim' || name === 'identity_number' || titleText === 'NIM' || titleText === 'NIP / NIM') {
        return 'dt-col-nim';
    }
    if (name === 'final_project_title' || name === 'book_title' || /judul/i.test(titleText)) {
        return 'dt-col-title';
    }
    if (name === 'student_name' || name === 'name' || titleText === 'Nama Mahasiswa' || titleText === 'Nama') {
        return 'dt-col-name';
    }
    if (name === 'program_study' || name === 'study_program' || titleText === 'Program Studi') {
        return 'dt-col-study-program';
    }
    if (name === 'cluster' || titleText === 'Rumpun Ilmu') {
        return 'dt-col-cluster';
    }
    if (name === 'type' || titleText === 'Jenis') {
        return 'dt-col-kind';
    }
    if (name === 'status' || name === 'role' || titleText === 'Status' || titleText === 'Peran' || titleText === 'Persetujuan') {
        return 'dt-col-status';
    }
    if (name === 'email' || titleText === 'Email') return 'dt-col-email';
    if (name === 'phone_number' || titleText === 'Nomor Telepon') return 'dt-col-phone';
    if (name === 'barcode' || titleText === 'Kode Batang') return 'dt-col-barcode';
    if (name === 'call_number' || /panggil/i.test(titleText)) return 'dt-col-code';
    if (
        name === 'final_project_year'
        || name === 'book_year'
        || name === 'graduation_year'
        || /^tahun/i.test(titleText)
    ) {
        return 'dt-col-year';
    }
    if (name === 'level') return 'dt-col-level';
    if (name === 'degree_level' || titleText === 'Jenjang') return 'dt-col-degree';
    if (name === 'submission_date' || /tanggal/i.test(titleText)) return 'dt-col-date';

    return 'dt-col-text';
};

const withColumnRole = column => {
    if (!column || typeof column !== 'object' || column.visible === false) return column;

    const next = Object.assign({}, column);
    delete next.width;
    next.className = [next.className, columnRoleClass(next)].filter(Boolean).join(' ');

    const className = String(next.className || '');
    const title = typeof next.title === 'string' ? next.title : '';
    // DataTables 1.13 mencocokkan columnDefs.targets (class) ke TH HTML yang sudah ada.
    // Tabel JS kosong belum punya th.no-sort saat itu, jadi kolom checkbox tetap orderable
    // dan klik "Hapus Semua" memicu ajax reload — centang baris ikut terhapus.
    if (className.includes('no-sort') || /checkall|approveall|superviseall/i.test(title)) {
        next.orderable = false;
        if (next.searchable === undefined) {
            next.searchable = false;
        }
    }

    const titleText = title.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    if (
        (titleText === 'No.' || titleText === 'No')
        && typeof next.render !== 'function'
        && typeof renderCountLabel === 'function'
    ) {
        next.render = renderCountLabel;
    }

    return next;
};

/** Kolom mutasi (ubah/hapus/centang) hanya untuk Petugas */
const finalizeColumns = columns => {
    const fitted = columns.map(withColumnRole);

    if (typeof canMutateData === 'undefined' || canMutateData) {
        return fitted;
    }

    return fitted.filter(column => {
        const title = column.title;
        if (typeof title === 'string' && title.includes('checkall')) return false;
        if (title === 'Pengaturan' && !column.retainForViewer) return false;
        return true;
    });
};

/**
 * Kolom tersembunyi agar kotak cari DataTable juga mencocokkan field
 * yang hanya tampil di baris detail (child row), bukan hanya kolom utama.
 */
const detailSearchColumn = (...indices) => ({
    data: null,
    visible: false,
    orderable: false,
    searchable: true,
    defaultContent: '',
    render: (_data, type, row) => {
        const haystack = indices
            .map(i => {
                const value = row?.[i];
                if (value == null || value === '') return '';
                return String(value).replace(/<[^>]*>/g, ' ').trim();
            })
            .filter(Boolean)
            .join(' ');

        if (type === 'display') return '';
        return haystack;
    }
});

/**
 * Ajax DataTables dengan skema PWA:
 * online  -> server-side (halaman/filter di database) + gabung salinan ke IndexedDB
 * offline -> paginasi/filter lokal dari IndexedDB
 */
function extraAjaxParams(params) {
    return typeof params === 'function' ? params() : (params || {});
}

function paginateOfflineRows(dtRequest, rows, extras = {}) {
    const draw = Number(dtRequest?.draw || 1);
    const start = Math.max(0, Number(dtRequest?.start || 0));
    let length = Number(dtRequest?.length || 25);
    if (!Number.isFinite(length) || length < 1 || length > 200) {
        length = 25;
    }

    let filtered = Array.isArray(rows) ? rows.slice() : [];
    const scan = String(extras.scan || '').trim();
    if (scan) {
        filtered = filtered.filter(row => String(row?.[13] ?? '').trim() === scan);
    }

    const keyword = String(dtRequest?.search?.value || '').trim().toLowerCase();
    if (keyword) {
        filtered = filtered.filter(row => Array.isArray(row) && row.some(cell => (
            String(cell ?? '').toLowerCase().includes(keyword)
        )));
    }

    return {
        draw,
        recordsTotal: Array.isArray(rows) ? rows.length : 0,
        recordsFiltered: filtered.length,
        data: filtered.slice(start, start + length)
    };
}

function offlineAwareAjax(moduleName, url, params = {}) {
    let xhr = null;

    return (dtRequest, callback) => {
        if (xhr && xhr.readyState !== 4) {
            xhr.abort();
        }

        const extras = extraAjaxParams(params);
        const indexKey = typeof OfflineSync !== 'undefined'
            ? OfflineSync.resolveIndexKey(moduleName, extras)
            : moduleName;
        const payload = typeof slimDataTablesRequest === 'function'
            ? slimDataTablesRequest(dtRequest, extras)
            : Object.assign({}, extras, dtRequest || {});

        const fallbackOffline = async () => {
            let rows = [];
            try {
                if (typeof OfflineSync !== 'undefined') {
                    rows = await OfflineSync.getOfflineIndexRows(moduleName, extras);
                }
            } catch (_) {
                rows = [];
            }
            callback(paginateOfflineRows(dtRequest, rows, extras));
        };

        xhr = $.ajax({
            url,
            data: payload,
            dataType: 'json',
            cache: false,
            timeout: 30000
        })
            .done(json => {
                if (json?.serverError && typeof showError === 'function') {
                    showError('Gagal memuat data.', json.message || 'Coba beberapa saat lagi.');
                }

                if (Array.isArray(json)) {
                    callback({ data: json });
                } else {
                    callback(json || { data: [] });
                }

                const rows = Array.isArray(json) ? json : (json?.data ?? []);
                const merge = () => {
                    if (typeof OfflineDatabase === 'undefined' || !OfflineDatabase.mergeIndexRows) return;
                    Promise.resolve(OfflineDatabase.mergeIndexRows(indexKey, rows, {
                        recordsTotal: Array.isArray(json) ? rows.length : json?.recordsTotal,
                        recordsFiltered: Array.isArray(json) ? rows.length : json?.recordsFiltered,
                        start: Number(dtRequest?.start ?? 0),
                        keyword: String(dtRequest?.search?.value ?? '').trim(),
                    })).catch(() => { /* kuota IndexedDB penuh: tabel online tetap tampil */ });
                };

                if (typeof requestIdleCallback === 'function') {
                    requestIdleCallback(merge, { timeout: 1500 });
                } else {
                    setTimeout(merge, 0);
                }
            })
            .fail((failedXhr, textStatus) => {
                if (textStatus === 'abort') {
                    return;
                }

                const offline = (typeof isAppOffline === 'function' && isAppOffline())
                    || failedXhr.status === 0
                    || textStatus === 'timeout';

                if (!offline && typeof showError === 'function') {
                    const info = typeof describeXhrError === 'function'
                        ? describeXhrError(failedXhr, textStatus)
                        : { message: 'Gagal memuat data. Coba beberapa saat lagi.' };
                    showError('Gagal memuat data.', info.message);
                }

                fallbackOffline();
            });
    };
}

function decorateStaffTableSearch(wrapper) {
    const $filter = $(wrapper).find('.dataTables_filter');
    if (!$filter.length || $filter.hasClass('dt-search-ready')) return;

    const $input = $filter.find('input[type="search"]').first();
    if (!$input.length) return;

    $filter.addClass('dt-search-ready');
    const $host = $input.parent();
    $host.addClass('dt-search-field');

    if (!$host.find('.dt-search-icon').length) {
        const icon = document.createElement('i');
        icon.className = 'fas fa-magnifying-glass dt-search-icon';
        icon.setAttribute('aria-hidden', 'true');
        $input[0].parentNode.insertBefore(icon, $input[0]);
    }

    if (!$input.attr('aria-label')) {
        $input.attr('aria-label', $input.attr('placeholder') || 'Cari');
    }
}

$(document).on('init.dt', function(_event, settings) {
    if (!settings?.nTableWrapper) return;
    decorateStaffTableSearch(settings.nTableWrapper);
});

$(document).ready(function() {
    const sharedLanguage = {
        decimal: '',
        emptyTable: 'Tidak ada data tersedia',
        info: 'Tampil _START_ - _END_ dari _TOTAL_ data',
        infoEmpty: 'Tidak ada data',
        infoFiltered: 'Tersaring dari _MAX_ total entri',
        thousands: ',',
        lengthMenu: '_MENU_',
        loadingRecords: 'Tunggu...',
        processing: 'Memuat…',
        search: '',
        zeroRecords: 'Tidak ada data yang cocok',
        paginate: paginateLabels
    };

    const sharedOptions = {
        paging: true,
        serverSide: true,
        autoWidth: false,
        processing: true,
        deferRender: true,
        searchDelay: 160,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [],
        dom: '<"dt-toolbar"<"dt-toolbar-left"l><"dt-toolbar-right"f>><"dt-table-frame"rt><"dt-footer"ip>',
        columnDefs: [{ targets: 'no-sort', orderable: false, searchable: false }],
        infoCallback: (settings, start, end, max, total, pre) => (
            typeof dataTablesInfoCallback === 'function'
                ? dataTablesInfoCallback(settings, start, end, max, total)
                : pre
        )
    };

    const finalProjectTypeContext = typeof getFinalProjectTypeContext === 'function'
        ? getFinalProjectTypeContext()
        : { id: null, level: null, type: 'Tugas Akhir' };

    // Hanya init DataTable jika elemen tabel ada di halaman saat ini

    // --- Tabel Tugas Akhir ---
    if ($('#finalprojecttable').length) {
        finalProjectTable = $('#finalprojecttable').DataTable({
            ...sharedOptions,
            language: {
                ...sharedLanguage,
                searchPlaceholder: `Cari ${finalProjectTypeContext.type}…`
            },
            // Online: ambil dari server (difilter Jenis TA) dan simpan salinan ke IndexedDB.
            // Offline: tampilan substitusi dari IndexedDB (termasuk perubahan antrean).
            ajax: offlineAwareAjax(
                'finalproject',
                '/finalproject/index',
                finalProjectTypeContext.id ? { type_id: finalProjectTypeContext.id } : {}
            ),
            columns: finalizeColumns([
                { title: 'Detail', data: null, width: '5%', className: 'dt-control no-sort', render: () => detailToggleMarkup() },
                { title: 'No.', data: 0, name: 'id', width: '5%' },
                { title: 'NIM', data: 2, name: 'nim', width: '12%' },
                { title: 'Nama Mahasiswa', data: 3, name: 'student_name', width: '22%' },
                { title: 'Program Studi', data: 5, name: 'program_study', width: '18%' },
                { title: 'Status', data: null, name: 'status', width: '10%', render: (_, __, row) => badgeMarkup(row[9] == 'Berlaku' ? 'badge-success' : 'badge-danger', row[9]) },
                { title: 'Pengaturan', data: null, width: '12%', className: 'no-sort', render: (_, __, row) => editDeleteMarkup('buttoneditfinalproject', 'buttondeletefinalproject', zipToJson(['id','nim','student_name','final_project_title','program_study','final_project_year','submission_date','final_project_file','approval_sheet'], [row[1],row[2],row[3],row[4],row[5],row[7],row[8],row[10],row[11]]), row[1]) },
                { title: headerCheckMarkup('checkall', 'Hapus Semua'), data: null, width: '10%', className: 'no-sort', render: (_, __, row) => rowCheckMarkup('Pilih Hapus', row[1]) },
                detailSearchColumn(4, 6, 7, 8, 9, 10, 11, 12, 13)
            ])
        });

        $('#finalprojecttable thead .checkbox-modern').on('click', function (event) {
            event.stopPropagation();
        });

        $('#finalprojecttable').on('change', '.row-check', function() {
            $(this).closest('tr').toggleClass('selected', this.checked);
            const boxes = $('#finalprojecttable tbody .row-check');
            $('#finalprojecttable #checkall').prop('checked', boxes.length > 0 && boxes.filter(':not(:checked)').length === 0);
        });

        $('#finalprojecttable tbody').on('click', 'td.dt-control', function() {
            const tableRow = $(this).closest('tr'), row = finalProjectTable.row(tableRow), shown = !row.child.isShown();
            shown ? row.child(renderFinalProjectDetail(row.data())).show() : row.child.hide();
            tableRow.toggleClass('shown', shown);
            $(this).find('i').toggleClass('fa-chevron-right', !shown).toggleClass('fa-chevron-down', shown);
        });

        $('#finalprojecttable').on('change', '#checkall', function() {
            const checked = this.checked;
            $('#finalprojecttable tbody .row-check').prop('checked', checked)
                .closest('tr').toggleClass('selected', checked);
        });
    }


    // --- Tabel Program Studi ---
    if ($('#studyprogramtable').length) {
        studyProgramTable = $('#studyprogramtable').DataTable({
            ...sharedOptions,
            language: {
                ...sharedLanguage,
                searchPlaceholder: 'Cari Program Studi…'
            },
            ajax: offlineAwareAjax('studyprogram', '/studyprogram/index'),
            columns: finalizeColumns([
                { title: 'Detail', data: null, width: '5%', className: 'dt-control no-sort', render: () => detailToggleMarkup() },
                { title: 'No.', data: 0, name: 'id', width: '5%' },
                { title: 'Program Studi', data: 2, name: 'study_program', width: '35%' },
                { title: 'Rumpun Ilmu', data: 3, name: 'cluster', width: '35%' },
                { title: 'Pengaturan', data: null, width: '10%', className: 'no-sort', render: (_, __, row) => editDeleteMarkup('buttoneditstudyprogram', 'buttondeletestudyprogram', zipToJson(['id','study_program','cluster'], [row[1],row[2],row[3]]), row[1]) },
                { title: headerCheckMarkup('checkall', 'Hapus Semua'), data: null, width: '10%', className: 'no-sort', render: (_, __, row) => rowCheckMarkup('Pilih Hapus', row[1]) },
                detailSearchColumn(4, 5)
            ])
        });

        $('#studyprogramtable').on('change', '.row-check', function() {
            $(this).closest('tr').toggleClass('selected', this.checked);
            $('#studyprogramtable #checkall').prop('checked', $('#studyprogramtable .row-check:not(:checked)').length === 0);
        });

        $('#studyprogramtable tbody').on('click', 'td.dt-control', function() {
            const tableRow = $(this).closest('tr'), row = studyProgramTable.row(tableRow), shown = !row.child.isShown();
            shown ? row.child(renderStudyProgramDetail(row.data())).show() : row.child.hide();
            tableRow.toggleClass('shown', shown);
            $(this).find('i').toggleClass('fa-chevron-right', !shown).toggleClass('fa-chevron-down', shown);
        });

        $('#studyprogramtable').on('change', '#checkall', function() {
            $('#studyprogramtable .row-check').prop('checked', this.checked)
                .closest('tbody').find('tr')
                .toggleClass('selected', this.checked);
        });
    }


    // --- Tabel Keanggotaan ---
    if ($('#membershiptable').length) {
        membershipTable = $('#membershiptable').DataTable({
            ...sharedOptions,
            language: {
                ...sharedLanguage,
                searchPlaceholder: 'Cari Keanggotaan…'
            },
            ajax: offlineAwareAjax('membership', '/membership/index'),
            columns: finalizeColumns([
                { title: 'Detail', data: null, width: '5%', className: 'dt-control no-sort', render: () => detailToggleMarkup() },
                { title: 'No.', data: 0, name: 'id', width: '5%' },
                { title: 'NIM', data: 2, name: 'identity_number', width: '12%' },
                { title: 'Nama', data: 3, name: 'name', width: '20%' },
                { title: 'Program Studi', data: 4, name: 'study_program', width: '20%' },
                { title: 'Jenis Kelamin', data: 5, name: 'gender', width: '12%' },
                { title: 'Tempat Lahir', data: 16, name: 'place_of_birth', width: '12%', render: data => data || '-' },
                { title: 'Tanggal Lahir', data: 6, name: 'date_of_birth', width: '12%' },
                { title: 'Pengaturan', data: null, width: '12%', className: 'no-sort', render: (_, __, row) => editDeleteMarkup(
                    'buttoneditmembership',
                    'buttondeletemembership',
                    zipToJson(['id','user_id','study_program_id','gender','place_of_birth','date_of_birth','origin_address','current_address'], [row[1],row[11],row[12],row[5],row[16],row[17],row[7],row[8]]),
                    row[1],
                    {
                        group: true,
                        leading: [
                            iconButton({
                                id: 'buttondownloadmembershipletter',
                                type: 'button',
                                className: 'icon-primary button-tooltip-target',
                                tooltip: 'Unduh Surat',
                                icon: 'fa-file-arrow-down',
                                dataId: row[1]
                            })
                        ]
                    }
                ) },
                { title: headerCheckMarkup('checkall', 'Hapus Semua'), data: null, width: '10%', className: 'no-sort', render: (_, __, row) => rowCheckMarkup('Pilih Hapus', row[1]) },
                detailSearchColumn(7, 8, 9, 10, 13, 14, 15)
            ])
        });

        $('#membershiptable').on('change', '.row-check', function() {
            $(this).closest('tr').toggleClass('selected', this.checked);
            $('#membershiptable #checkall').prop('checked', $('#membershiptable .row-check:not(:checked)').length === 0);
        });

        $('#membershiptable tbody').on('click', 'td.dt-control', function() {
            const tableRow = $(this).closest('tr'), row = membershipTable.row(tableRow), shown = !row.child.isShown();
            shown ? row.child(renderMembershipDetail(row.data())).show() : row.child.hide();
            tableRow.toggleClass('shown', shown);
            $(this).find('i').toggleClass('fa-chevron-right', !shown).toggleClass('fa-chevron-down', shown);
        });

        $('#membershiptable').on('change', '#checkall', function() {
            $('#membershiptable .row-check').prop('checked', this.checked)
                .closest('tbody').find('tr')
                .toggleClass('selected', this.checked);
        });
    }


    // --- Tabel Jenis Tugas Akhir ---
    if ($('#finalprojecttypetable').length) {
        finalProjectTypeTable = $('#finalprojecttypetable').DataTable({
            ...sharedOptions,
            language: {
                ...sharedLanguage,
                searchPlaceholder: 'Cari Jenis Tugas Akhir…'
            },
            ajax: offlineAwareAjax('finalprojecttype', '/finalprojecttype/index'),
            columns: finalizeColumns([
                { title: 'Detail', data: null, width: '5%', className: 'dt-control no-sort', render: () => detailToggleMarkup() },
                { title: 'No.', data: 0, name: 'id', width: '5%' },
                { title: 'Jenjang', data: 2, name: 'level', width: '20%' },
                { title: 'Jenis', data: 3, name: 'type', width: '50%' },
                { title: 'Pengaturan', data: null, width: '10%', className: 'no-sort', render: (_, __, row) => editDeleteMarkup('buttoneditfinalprojecttype', 'buttondeletefinalprojecttype', zipToJson(['id','level','type'], [row[1],row[2],row[3]]), row[1]) },
                { title: headerCheckMarkup('checkall', 'Hapus Semua'), data: null, width: '10%', className: 'no-sort', render: (_, __, row) => rowCheckMarkup('Pilih Hapus', row[1]) },
                detailSearchColumn(4, 5)
            ])
        });

        $('#finalprojecttypetable').on('change', '.row-check', function() {
            $(this).closest('tr').toggleClass('selected', this.checked);
            $('#finalprojecttypetable #checkall').prop('checked', $('#finalprojecttypetable .row-check:not(:checked)').length === 0);
        });

        $('#finalprojecttypetable tbody').on('click', 'td.dt-control', function() {
            const tableRow = $(this).closest('tr'), row = finalProjectTypeTable.row(tableRow), shown = !row.child.isShown();
            shown ? row.child(renderFinalProjectTypeDetail(row.data())).show() : row.child.hide();
            tableRow.toggleClass('shown', shown);
            $(this).find('i').toggleClass('fa-chevron-right', !shown).toggleClass('fa-chevron-down', shown);
        });

        $('#finalprojecttypetable').on('change', '#checkall', function() {
            $('#finalprojecttypetable .row-check').prop('checked', this.checked)
                .closest('tbody').find('tr')
                .toggleClass('selected', this.checked);
        });
    }


    // --- Tabel Pengunjung ---
    if ($('#visitortable').length) {
        const visitorColumns = [
            { title: 'Detail', data: null, width: '5%', className: 'dt-control no-sort', render: () => detailToggleMarkup() },
            { title: 'No.', data: 0, name: 'id', width: '5%' },
            { title: 'NIM', data: 2, name: 'nim', width: '15%' },
            { title: 'Nama Mahasiswa', data: 3, name: 'student_name', width: '20%' },
            { title: 'Program Studi', data: 4, name: 'program_study', width: '15%' },
            { title: 'Keperluan', data: 5, name: 'needs', width: '15%' },
            canMutateData
                ? {
                    title: headerCheckMarkup('superviseallvisitor', 'Awasi Semua', 'checkbox-approve'),
                    data: null,
                    width: '10%',
                    className: 'no-sort',
                    retainForViewer: true,
                    render: (_, __, row) => {
                        const supervised = row[9] != null && row[9] !== '' && Number(row[9]) > 0;
                        return rowCheckMarkup(
                            supervised ? 'Batalkan Pengawasan' : 'Tetapkan Pengawasan',
                            row[1],
                            { inputClass: 'row-supervise', checked: supervised, extraClass: 'checkbox-approve' }
                        );
                    }
                }
                : { title: 'Pengawasan', data: 6, width: '15%' },
        ];

        if (canMutateData) {
            visitorColumns.push(
                { title: 'Pengawas', data: 6, width: '12%' },
                {
                    title: 'Pengaturan',
                    data: null,
                    width: '10%',
                    className: 'no-sort',
                    render: (_, __, row) => editDeleteMarkup('buttoneditvisitor', 'buttondeletevisitor', zipToJson(['id','nim','student_name','program_study','needs'], [row[1],row[2],row[3],row[4],row[5]]), row[1])
                },
                {
                    title: headerCheckMarkup('checkall', 'Hapus Semua'),
                    data: null,
                    width: '5%',
                    className: 'no-sort',
                    render: (_, __, row) => rowCheckMarkup('Pilih Hapus', row[1])
                }
            );
        }

        visitorColumns.push(detailSearchColumn(6, 7, 8));

        visitorTable = $('#visitortable').DataTable({
            ...sharedOptions,
            language: {
                ...sharedLanguage,
                searchPlaceholder: 'Cari Pengunjung…'
            },
            ajax: offlineAwareAjax('visitor', '/visitor/index'),
            columns: finalizeColumns(visitorColumns)
        });

        if (canMutateData) {
            $('#visitortable').on('change', '.row-check', function() {
                $(this).closest('tr').toggleClass('selected', this.checked);
                $('#visitortable #checkall').prop('checked', $('#visitortable .row-check:not(:checked)').length === 0);
            });

            $('#visitortable').on('change', '#checkall', function() {
                $('#visitortable .row-check').prop('checked', this.checked)
                    .closest('tbody').find('tr')
                    .toggleClass('selected', this.checked);
            });

            const syncSuperviseHeader = () => {
                const boxes = $('#visitortable .row-supervise');
                $('#superviseallvisitor').prop(
                    'checked',
                    boxes.length > 0 && boxes.filter(':not(:checked)').length === 0
                );
            };

            const submitSupervision = (ids, supervised, checkbox) => {
                const serverIds = ids
                    .map(Number)
                    .filter(id => Number.isFinite(id) && id > 0);

                if (!serverIds.length) {
                    if (checkbox) checkbox.checked = !supervised;
                    syncSuperviseHeader();
                    if (typeof showError === 'function') {
                        showError(
                            'Tidak dapat mengatur pengawasan.',
                            'Data lokal yang luring belum tersinkronkan ke peladen.'
                        );
                    }
                    return;
                }

                const queueOffline = async () => {
                    await OfflineSync.queueVisitorSupervision(csrfToken, serverIds, supervised);
                    if (typeof showSuccess === 'function') {
                        showSuccess(
                            'Tersimpan secara luring.',
                            'Status pengawasan disimpan di perangkat dan akan disinkronkan saat koneksi kembali.'
                        );
                    }
                };

                if (OfflineSync.isOffline()) return void queueOffline();

                $.ajax({
                    url: '/visitor/supervise',
                    type: 'POST',
                    data: { _token: csrfToken, ids: serverIds, supervised: supervised ? 1 : 0 },
                    success: message => {
                        visitorTable.ajax.reload(() => syncSuperviseHeader(), false);
                        if (typeof showSuccess === 'function') {
                            showSuccess(
                                supervised ? 'Pengawasan ditetapkan.' : 'Pengawasan dibatalkan.',
                                typeof message === 'string' ? message : 'Status pengawasan diperbarui.'
                            );
                        }
                    },
                    error: xhr => {
                        if (xhr.status === 0) return void queueOffline();
                        if (checkbox) checkbox.checked = !supervised;
                        syncSuperviseHeader();
                        if (typeof showError === 'function') {
                            showError(
                                'Gagal memperbarui pengawasan.',
                                xhr.responseJSON ?? 'Terjadi kesalahan pada peladen.'
                            );
                        }
                    }
                });
            };

            $('#visitortable').on('change', '.row-supervise', function () {
                submitSupervision([Number(this.value)], this.checked, this);
                syncSuperviseHeader();
            });

            $('#visitortable').on('change', '#superviseallvisitor', function () {
                const supervised = this.checked;
                const boxes = $('#visitortable .row-supervise');
                const ids = boxes.map((_, el) => Number(el.value)).get();

                if (!ids.length) {
                    this.checked = false;
                    return;
                }

                boxes.prop('checked', supervised);
                submitSupervision(ids, supervised, null);
            });

            visitorTable.on('draw', syncSuperviseHeader);
        }

        $('#visitortable tbody').on('click', 'td.dt-control', function() {
            const tableRow = $(this).closest('tr'), row = visitorTable.row(tableRow), shown = !row.child.isShown();
            shown ? row.child(renderVisitorDetail(row.data())).show() : row.child.hide();
            tableRow.toggleClass('shown', shown);
            $(this).find('i').toggleClass('fa-chevron-right', !shown).toggleClass('fa-chevron-down', shown);
        });
    }


    // --- Tabel Hibah Buku ---
    if ($('#bookgranttable').length) {
        bookGrantTable = $('#bookgranttable').DataTable({
            ...sharedOptions,
            language: {
                ...sharedLanguage,
                searchPlaceholder: 'Cari Hibah Buku…'
            },
            ajax: offlineAwareAjax('bookgrant', '/bookgrant/index'),
            columns: finalizeColumns([
                { title: 'Detail', data: null, width: '4%', className: 'dt-control no-sort', render: () => detailToggleMarkup() },
                { title: 'No.', data: 0, name: 'id', width: '5%' },
                { title: 'NIM', data: 2, name: 'nim', width: '12%' },
                { title: 'Nama Mahasiswa', data: 3, name: 'student_name', width: '18%' },
                { title: 'Judul Buku', data: 9, name: 'book_title', width: '20%' },
                { title: 'Tahun Buku', data: 10, name: 'book_year', width: '10%' },
                { title: 'Pengarang', data: 11, name: 'author', width: '15%' },
                { title: 'Pengaturan', data: null, width: '12%', className: 'no-sort', render: (_, __, row) => editDeleteMarkup(
                    'buttoneditbookgrant',
                    'buttondeletebookgrant',
                    zipToJson(['id','final_project_id','book_title','book_year','author','membership_registered'], [row[1],row[14],row[9],row[10],row[11],row[7]]),
                    row[1],
                    {
                        group: true,
                        leading: [
                            iconButton({
                                id: 'buttondownloadbookgrantletter',
                                type: 'button',
                                className: 'icon-primary button-tooltip-target',
                                tooltip: 'Unduh Surat',
                                icon: 'fa-file-arrow-down',
                                dataId: row[1],
                                dataset: { membership: row[7] ?? '' }
                            })
                        ]
                    }
                ) },
                { title: headerCheckMarkup('checkall', 'Hapus Semua'), data: null, width: '6%', className: 'no-sort', render: (_, __, row) => rowCheckMarkup('Pilih Hapus', row[1]) },
                detailSearchColumn(4, 5, 6, 7, 8, 12, 13)
            ])
        });

        $('#bookgranttable').on('change', '.row-check', function() {
            $(this).closest('tr').toggleClass('selected', this.checked);
            $('#bookgranttable #checkall').prop('checked', $('#bookgranttable .row-check:not(:checked)').length === 0);
        });

        $('#bookgranttable tbody').on('click', 'td.dt-control', function() {
            const tableRow = $(this).closest('tr'), row = bookGrantTable.row(tableRow), shown = !row.child.isShown();
            shown ? row.child(renderBookGrantDetail(row.data())).show() : row.child.hide();
            tableRow.toggleClass('shown', shown);
            $(this).find('i').toggleClass('fa-chevron-right', !shown).toggleClass('fa-chevron-down', shown);
        });

        $('#bookgranttable').on('change', '#checkall', function() {
            $('#bookgranttable .row-check').prop('checked', this.checked)
                .closest('tbody').find('tr')
                .toggleClass('selected', this.checked);
        });
    }


    // --- Tabel Bebas Pustaka ---
    if ($('#libraryclearancetable').length) {
        libraryClearanceTable = $('#libraryclearancetable').DataTable({
            ...sharedOptions,
            language: {
                ...sharedLanguage,
                searchPlaceholder: 'Cari Bebas Pustaka…'
            },
            ajax: offlineAwareAjax('libraryclearance', '/libraryclearance/index'),
            columns: finalizeColumns([
                { title: 'Detail', data: null, width: '4%', className: 'dt-control no-sort', render: () => detailToggleMarkup() },
                { title: 'No.', data: 0, name: 'id', width: '5%' },
                { title: 'NIM', data: 2, name: 'nim', width: '12%' },
                { title: 'Nama Mahasiswa', data: 3, name: 'student_name', width: '18%' },
                { title: 'Email', data: 8, name: 'email', width: '16%' },
                { title: 'Nomor Telepon', data: 9, name: 'phone_number', width: '12%' },
                { title: 'Tahun Lulus', data: 10, name: 'graduation_year', width: '10%' },
                { title: 'Semester Lulus', data: 11, name: 'semester_graduated', width: '10%' },
                { title: 'Pengaturan', data: null, width: '12%', className: 'no-sort', render: (_, __, row) => editDeleteMarkup(
                    'buttoneditlibraryclearance',
                    'buttondeletelibraryclearance',
                    zipToJson(['id','final_project_id','email','phone_number','graduation_year','semester_graduated','membership_registered'], [row[1],row[14],row[8],row[9],row[10],row[11],row[7]]),
                    row[1],
                    {
                        group: true,
                        leading: [
                            iconButton({
                                id: 'buttondownloadlibraryclearanceletter',
                                type: 'button',
                                className: 'icon-primary button-tooltip-target',
                                tooltip: 'Unduh Surat',
                                icon: 'fa-file-arrow-down',
                                dataId: row[1],
                                dataset: { membership: row[7] ?? '' }
                            })
                        ]
                    }
                ) },
                { title: headerCheckMarkup('checkall', 'Hapus Semua'), data: null, width: '5%', className: 'no-sort', render: (_, __, row) => rowCheckMarkup('Pilih Hapus', row[1]) },
                detailSearchColumn(4, 5, 6, 7, 12, 13)
            ])
        });

        $('#libraryclearancetable').on('change', '.row-check', function() {
            $(this).closest('tr').toggleClass('selected', this.checked);
            $('#libraryclearancetable #checkall').prop('checked', $('#libraryclearancetable .row-check:not(:checked)').length === 0);
        });

        $('#libraryclearancetable tbody').on('click', 'td.dt-control', function() {
            const tableRow = $(this).closest('tr'), row = libraryClearanceTable.row(tableRow), shown = !row.child.isShown();
            shown ? row.child(renderLibraryClearanceDetail(row.data())).show() : row.child.hide();
            tableRow.toggleClass('shown', shown);
            $(this).find('i').toggleClass('fa-chevron-right', !shown).toggleClass('fa-chevron-down', shown);
        });

        $('#libraryclearancetable').on('change', '#checkall', function() {
            $('#libraryclearancetable .row-check').prop('checked', this.checked)
                .closest('tbody').find('tr')
                .toggleClass('selected', this.checked);
        });
    }


    // --- Tabel Kode Batang ---
    if ($('#barcodetable').length) {
        let activeBarcodeScanCode = null;

        barcodeTable = $('#barcodetable').DataTable({
            ...sharedOptions,
            language: {
                ...sharedLanguage,
                searchPlaceholder: 'Cari Kode Batang…'
            },
            ajax: offlineAwareAjax('barcode', '/barcode/index', () => (
                activeBarcodeScanCode ? { scan: activeBarcodeScanCode } : {}
            )),
            columns: finalizeColumns([
                { title: 'Detail', data: null, width: '4%', className: 'dt-control no-sort', render: () => detailToggleMarkup() },
                { title: 'No.', data: 0, name: 'id', width: '5%' },
                { title: 'NIM', data: 2, name: 'nim', width: '12%' },
                { title: 'Nama Mahasiswa', data: 3, name: 'student_name', width: '18%' },
                { title: 'Kode Batang', data: 13, name: 'barcode', width: '28%', orderable: true, searchable: true, render: (data, type) => {
                    const code = String(data || '').trim();

                    // filter/sort memakai field barcode mentah, bukan label / markup SVG
                    if (type !== 'display') {
                        return code;
                    }

                    if (!code) return '-';
                    const cell = document.createElement('div');
                    cell.className = 'barcode-cell';
                    cell.append(barcodeSvg(code, { height: 42 }));
                    return markup(cell);
                } },
                { title: 'Pengaturan', data: null, width: '10%', className: 'no-sort', render: (_, __, row) => {
                    return editDeleteMarkup('buttoneditbarcode', 'buttondeletebarcode', zipToJson(['id','final_project_id','label','barcode'], [row[1],row[16],row[12],row[13]]), row[1]);
                } },
                { title: headerCheckMarkup('checkall', 'Hapus Semua'), data: null, width: '5%', className: 'no-sort', render: (_, __, row) => rowCheckMarkup('Pilih Hapus', row[1]) },
                detailSearchColumn(4, 5, 6, 7, 8, 9, 10, 11, 12, 14, 15)
            ]),
            drawCallback() {
                paintCode128Barcodes(this.api().table().container());
            }
        });

        const barcodeScanResult = document.getElementById('barcodescanresult');
        const barcodeScanResultText = document.getElementById('barcodescanresulttext');

        const clearBarcodeScanFilter = () => {
            activeBarcodeScanCode = null;

            barcodeTable.search('').ajax.reload(null, true);

            if (barcodeScanResult) {
                barcodeScanResult.hidden = true;
            }

            if (barcodeScanResultText) {
                barcodeScanResultText.replaceChildren();
            }
        };

        const applyBarcodeScanFilter = code => {
            const scannedCode = String(code || '').trim();

            if (!scannedCode) return;

            activeBarcodeScanCode = scannedCode;

            barcodeTable.search('').ajax.reload(() => {
                const matchedCount = barcodeTable.page.info().recordsDisplay;

                if (barcodeScanResult) {
                    barcodeScanResult.hidden = false;
                    barcodeScanResult.classList.toggle('is-empty', matchedCount === 0);
                    barcodeScanResult.classList.toggle('is-found', matchedCount > 0);
                }

                if (barcodeScanResultText) {
                    const message = document.createDocumentFragment();
                    message.append(document.createTextNode('Hasil pindai '));
                    message.append(strongText(scannedCode));
                    if (matchedCount) {
                        message.append(document.createTextNode(' — ditemukan '));
                        message.append(strongText(String(matchedCount)));
                        message.append(document.createTextNode(' data.'));
                    } else {
                        message.append(document.createTextNode(' — tidak ada data yang cocok.'));
                    }
                    barcodeScanResultText.replaceChildren();
                    appendStatus(
                        barcodeScanResultText,
                        matchedCount ? 'fa-check-circle' : 'fa-circle-exclamation',
                        message
                    );
                }

                if (matchedCount === 0) {
                    showError(
                        'Kode batang tidak ditemukan.',
                        `Kode "${scannedCode}" tidak cocok dengan data kode batang.`
                    );
                } else {
                    showSuccess(
                        'Kode batang ditemukan.',
                        `Menampilkan ${matchedCount} data yang cocok dengan kode "${scannedCode}".`
                    );
                }
            }, true);
        };

        document.addEventListener('barcode-scanned', event => {
            applyBarcodeScanFilter(event.detail?.code);
        });

        $('body').on('click', '#buttonresetbarcodescan', event => {
            event.preventDefault();
            clearBarcodeScanFilter();
        });

        $('#barcodetable').on('change', '.row-check', function() {
            $(this).closest('tr').toggleClass('selected', this.checked);
            $('#barcodetable #checkall').prop('checked', $('#barcodetable .row-check:not(:checked)').length === 0);
        });

        $('#barcodetable tbody').on('click', 'td.dt-control', function() {
            const tableRow = $(this).closest('tr'), row = barcodeTable.row(tableRow), shown = !row.child.isShown();
            if (shown) {
                row.child(renderBarcodeDetail(row.data())).show();
                paintCode128Barcodes(tableRow.next()[0]);
            } else {
                row.child.hide();
            }
            tableRow.toggleClass('shown', shown);
            $(this).find('i').toggleClass('fa-chevron-right', !shown).toggleClass('fa-chevron-down', shown);
        });

        $('#barcodetable').on('change', '#checkall', function() {
            $('#barcodetable .row-check').prop('checked', this.checked)
                .closest('tbody').find('tr')
                .toggleClass('selected', this.checked);
        });
    }


    // --- Tabel Pemusnahan ---
    if ($('#destructiontable').length) {
        const isCoordinator = userRole === 'Koordinator';

        const destructionColumns = [
            { title: 'Detail', data: null, width: '4%', className: 'dt-control no-sort', render: () => detailToggleMarkup() },
            { title: 'No.', data: 0, name: 'id', width: '5%' },
            { title: 'NIM', data: 2, name: 'nim', width: '12%' },
            { title: 'Nama Mahasiswa', data: 3, name: 'student_name', width: '16%' },
            { title: 'Judul Tugas Akhir', data: 4, name: 'final_project_title', width: '22%' },
            isCoordinator
                ? {
                    title: headerCheckMarkup('approvealldestruction', 'Setujui Semua', 'checkbox-approve'),
                    data: null,
                    width: '10%',
                    className: 'no-sort',
                    retainForViewer: true,
                    render: (_, __, row) => rowCheckMarkup(
                        row[11] === 'Disetujui' ? 'Batalkan Persetujuan' : 'Setujui',
                        row[1],
                        { inputClass: 'row-approve', checked: row[11] === 'Disetujui', extraClass: 'checkbox-approve' }
                    )
                }
                : {
                    title: 'Persetujuan',
                    data: null,
                    name: 'agreement',
                    width: '12%',
                    render: (_, __, row) => badgeMarkup(row[11] == 'Disetujui' ? 'badge-success' : 'badge-danger', row[11])
                },
            { title: 'Penyetuju', data: 12, width: '12%' },
        ];

        if (canMutateData) {
            destructionColumns.push(
                {
                    title: 'Pengaturan',
                    data: null,
                    width: '10%',
                    className: 'no-sort',
                    render: (_, __, row) => editDeleteMarkup('buttoneditdestruction', 'buttondeletedestruction', zipToJson(['id','final_project_id','description','agreement'], [row[1],row[15],row[10],row[11]]), row[1])
                },
                {
                    title: headerCheckMarkup('checkall', 'Hapus Semua'),
                    data: null,
                    width: '5%',
                    className: 'no-sort',
                    render: (_, __, row) => rowCheckMarkup('Pilih Hapus', row[1])
                }
            );
        }

        destructionColumns.push(detailSearchColumn(5, 6, 7, 8, 9, 10, 11, 13, 14));

        destructionTable = $('#destructiontable').DataTable({
            ...sharedOptions,
            language: {
                ...sharedLanguage,
                searchPlaceholder: 'Cari Pemusnahan…'
            },
            ajax: offlineAwareAjax('destruction', '/destruction/index'),
            columns: finalizeColumns(destructionColumns)
        });

        if (canMutateData) {
            $('#destructiontable').on('change', '.row-check', function() {
                $(this).closest('tr').toggleClass('selected', this.checked);
                $('#destructiontable #checkall').prop('checked', $('#destructiontable .row-check:not(:checked)').length === 0);
            });

            $('#destructiontable').on('change', '#checkall', function() {
                $('#destructiontable .row-check').prop('checked', this.checked)
                    .closest('tbody').find('tr')
                    .toggleClass('selected', this.checked);
            });
        }

        if (isCoordinator) {
            const syncApproveHeader = () => {
                const boxes = $('#destructiontable .row-approve');
                $('#approvealldestruction').prop(
                    'checked',
                    boxes.length > 0 && boxes.filter(':not(:checked)').length === 0
                );
            };

            const submitApproval = (ids, approved, checkbox) => {
                const serverIds = ids
                    .map(Number)
                    .filter(id => Number.isFinite(id) && id > 0);

                if (!serverIds.length) {
                    if (checkbox) checkbox.checked = !approved;
                    syncApproveHeader();
                    if (typeof showError === 'function') {
                        showError(
                            'Tidak dapat menyetujui.',
                            'Data lokal yang luring belum tersinkronkan ke peladen.'
                        );
                    }
                    return;
                }

                const queueOffline = async () => {
                    await OfflineSync.queueDestructionApproval(csrfToken, serverIds, approved);
                    if (typeof showSuccess === 'function') {
                        showSuccess(
                            'Tersimpan secara luring.',
                            'Status persetujuan disimpan di perangkat dan akan disinkronkan saat koneksi kembali.'
                        );
                    }
                };

                if (OfflineSync.isOffline()) return void queueOffline();

                $.ajax({
                    url: '/destruction/approve',
                    type: 'POST',
                    data: { _token: csrfToken, ids: serverIds, approved: approved ? 1 : 0 },
                    success: message => {
                        destructionTable.ajax.reload(() => syncApproveHeader(), false);
                        if (typeof showSuccess === 'function') {
                            showSuccess(
                                approved ? 'Disetujui.' : 'Persetujuan dibatalkan.',
                                typeof message === 'string' ? message : 'Status persetujuan diperbarui.'
                            );
                        }
                    },
                    error: xhr => {
                        if (xhr.status === 0) return void queueOffline();
                        if (checkbox) checkbox.checked = !approved;
                        syncApproveHeader();
                        if (typeof showError === 'function') {
                            showError(
                                'Gagal memperbarui persetujuan.',
                                xhr.responseJSON ?? 'Terjadi kesalahan pada peladen.'
                            );
                        }
                    }
                });
            };

            $('#destructiontable').on('change', '.row-approve', function () {
                submitApproval([Number(this.value)], this.checked, this);
                syncApproveHeader();
            });

            $('#destructiontable').on('change', '#approvealldestruction', function () {
                const approved = this.checked;
                const boxes = $('#destructiontable .row-approve');
                const ids = boxes.map((_, el) => Number(el.value)).get();

                if (!ids.length) {
                    this.checked = false;
                    return;
                }

                boxes.prop('checked', approved);
                submitApproval(ids, approved, null);
            });

            destructionTable.on('draw', syncApproveHeader);
        }

        $('#destructiontable tbody').on('click', 'td.dt-control', function() {
            const tableRow = $(this).closest('tr'), row = destructionTable.row(tableRow), shown = !row.child.isShown();
            shown ? row.child(renderDestructionDetail(row.data())).show() : row.child.hide();
            tableRow.toggleClass('shown', shown);
            $(this).find('i').toggleClass('fa-chevron-right', !shown).toggleClass('fa-chevron-down', shown);
        });
    }


    // --- Tabel Arsip ---
    if ($('#archivetable').length) {
        archiveTable = $('#archivetable').DataTable({
            ...sharedOptions,
            language: {
                ...sharedLanguage,
                searchPlaceholder: 'Cari Arsip…'
            },
            ajax: offlineAwareAjax('archive', '/archive/index'),
            columns: finalizeColumns([
                { title: 'Detail', data: null, width: '4%', className: 'dt-control no-sort', render: () => detailToggleMarkup() },
                { title: 'No.', data: 0, name: 'id', width: '5%' },
                { title: 'NIM', data: 2, name: 'nim', width: '12%' },
                { title: 'Nama Mahasiswa', data: 3, name: 'student_name', width: '18%' },
                { title: 'Judul Tugas Akhir', data: 4, name: 'final_project_title', width: '28%' },
                { title: 'Keterangan', data: 11, name: 'description', width: '18%' },
                { title: 'Pengaturan', data: null, width: '10%', className: 'no-sort', render: (_, __, row) => {
                    return editDeleteMarkup('buttoneditarchive', 'buttondeletearchive', zipToJson(['id','final_project_id','description'], [row[1],row[14],row[11]]), row[1]);
                } },
                { title: headerCheckMarkup('checkall', 'Hapus Semua'), data: null, width: '5%', className: 'no-sort', render: (_, __, row) => rowCheckMarkup('Pilih Hapus', row[1]) },
                detailSearchColumn(5, 6, 7, 8, 9, 10, 12, 13)
            ])
        });

        $('#archivetable').on('change', '.row-check', function() {
            $(this).closest('tr').toggleClass('selected', this.checked);
            $('#archivetable #checkall').prop('checked', $('#archivetable .row-check:not(:checked)').length === 0);
        });

        $('#archivetable tbody').on('click', 'td.dt-control', function() {
            const tableRow = $(this).closest('tr'), row = archiveTable.row(tableRow), shown = !row.child.isShown();
            shown ? row.child(renderArchiveDetail(row.data())).show() : row.child.hide();
            tableRow.toggleClass('shown', shown);
            $(this).find('i').toggleClass('fa-chevron-right', !shown).toggleClass('fa-chevron-down', shown);
        });

        $('#archivetable').on('change', '#checkall', function() {
            $('#archivetable .row-check').prop('checked', this.checked)
                .closest('tbody').find('tr')
                .toggleClass('selected', this.checked);
        });
    }


    // --- Tabel Laporan ---
    if ($('#reporttable').length) {
        reportTable = $('#reporttable').DataTable({
            ...sharedOptions,
            language: {
                ...sharedLanguage,
                searchPlaceholder: 'Cari Laporan…'
            },
            ajax: offlineAwareAjax('report', '/report/index'),
            columns: finalizeColumns([
                { title: 'Detail', data: null, width: '4%', className: 'dt-control no-sort', render: () => detailToggleMarkup() },
                { title: 'No.', data: 0, name: 'id', width: '5%' },
                { title: 'NIM', data: 2, name: 'nim', width: '12%' },
                { title: 'Nama Mahasiswa', data: 3, name: 'student_name', width: '18%' },
                { title: 'Judul Tugas Akhir', data: 4, name: 'final_project_title', width: '28%' },
                { title: 'Keterangan', data: 17, name: 'description', width: '18%' },
                { title: 'Pengaturan', data: null, width: '10%', className: 'no-sort', render: (_, __, row) => {
                    return editDeleteMarkup('buttoneditreport', 'buttondeletereport', zipToJson(['id','final_project_id','description'], [row[1],row[21],row[17]]), row[1]);
                } },
                { title: headerCheckMarkup('checkall', 'Hapus Semua'), data: null, width: '5%', className: 'no-sort', render: (_, __, row) => rowCheckMarkup('Pilih Hapus', row[1]) },
                detailSearchColumn(5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 18, 19, 20)
            ])
        });

        $('#reporttable').on('change', '.row-check', function() {
            $(this).closest('tr').toggleClass('selected', this.checked);
            $('#reporttable #checkall').prop('checked', $('#reporttable .row-check:not(:checked)').length === 0);
        });

        $('#reporttable tbody').on('click', 'td.dt-control', function() {
            const tableRow = $(this).closest('tr'), row = reportTable.row(tableRow), shown = !row.child.isShown();
            shown ? row.child(renderReportDetail(row.data())).show() : row.child.hide();
            tableRow.toggleClass('shown', shown);
            $(this).find('i').toggleClass('fa-chevron-right', !shown).toggleClass('fa-chevron-down', shown);
        });

        $('#reporttable').on('change', '#checkall', function() {
            $('#reporttable .row-check').prop('checked', this.checked)
                .closest('tbody').find('tr')
                .toggleClass('selected', this.checked);
        });
    }


    // --- Tabel Penyerahan ---
    if ($('#submissiontable').length) {
        submissionTable = $('#submissiontable').DataTable({
            ...sharedOptions,
            language: {
                ...sharedLanguage,
                searchPlaceholder: 'Cari Penyerahan…'
            },
            ajax: offlineAwareAjax('submission', '/submission/index'),
            columns: finalizeColumns([
                { title: 'Detail', data: null, width: '5%', className: 'dt-control no-sort', render: () => detailToggleMarkup() },
                { title: 'No.', data: 0, name: 'id', width: '5%' },
                { title: 'NIM', data: 2, name: 'nim', width: '12%' },
                { title: 'Nama Mahasiswa', data: 3, name: 'student_name', width: '22%' },
                { title: 'Program Studi', data: 5, name: 'program_study', width: '18%' },
                { title: 'Status', data: null, name: 'status', width: '10%', render: (_, __, row) => badgeMarkup(row[9] == 'Berlaku' ? 'badge-success' : 'badge-danger', row[9]) },
                { title: 'Pengaturan', data: null, width: '12%', className: 'no-sort', render: (_, __, row) => editDeleteMarkup('buttoneditsubmission', 'buttondeletesubmission', zipToJson(['id','nim','student_name','final_project_title','program_study','degree_level','final_project_year','submission_date','final_project_file','approval_sheet','final_project_type_id'], [row[1],row[2],row[3],row[4],row[5],row[6],row[7],row[8],row[10],row[11],row[14]]), row[1]) },
                { title: headerCheckMarkup('checkall', 'Hapus Semua'), data: null, width: '10%', className: 'no-sort', render: (_, __, row) => rowCheckMarkup('Pilih Hapus', row[1]) },
                detailSearchColumn(4, 6, 7, 8, 9, 10, 11, 12, 13)
            ])
        });

        $('#submissiontable').on('change', '.row-check', function() {
            $(this).closest('tr').toggleClass('selected', this.checked);
            $('#submissiontable #checkall').prop('checked', $('#submissiontable .row-check:not(:checked)').length === 0);
        });

        $('#submissiontable tbody').on('click', 'td.dt-control', function() {
            const tableRow = $(this).closest('tr'), row = submissionTable.row(tableRow), shown = !row.child.isShown();
            shown ? row.child(renderSubmissionDetail(row.data())).show() : row.child.hide();
            tableRow.toggleClass('shown', shown);
            $(this).find('i').toggleClass('fa-chevron-right', !shown).toggleClass('fa-chevron-down', shown);
        });

        $('#submissiontable').on('change', '#checkall', function() {
            $('#submissiontable .row-check').prop('checked', this.checked)
                .closest('tbody').find('tr')
                .toggleClass('selected', this.checked);
        });
    }


    // --- Tabel Pengguna ---
    if ($('#usertable').length) {
        userTable = $('#usertable').DataTable({
            ...sharedOptions,
            language: {
                ...sharedLanguage,
                searchPlaceholder: 'Cari Pengguna…'
            },
            ajax: offlineAwareAjax('user', '/user/index'),
            columns: finalizeColumns([
                { title: 'Detail', data: null, width: '5%', className: 'dt-control no-sort', render: () => detailToggleMarkup() },
                { title: 'No.', data: 0, name: 'id', width: '5%' },
                { title: 'NIP / NIM', data: 2, name: 'identity_number', width: '18%' },
                { title: 'Nama', data: 3, name: 'name', width: '22%' },
                { title: 'Email', data: 4, name: 'email', width: '22%' },
                { title: 'Peran', data: null, name: 'role', width: '12%', render: (_, __, row) => {
                    const role = row[5];
                    const badgeClass = role === 'Koordinator'
                        ? 'badge-success'
                        : (role === 'Mahasiswa' ? 'badge-info' : 'badge-danger');
                    return badgeMarkup(badgeClass, role);
                } },
                { title: 'Pengaturan', data: null, width: '16%', className: 'no-sort', render: (_, __, row) => {
                    const isStudent = row[5] === 'Mahasiswa';
                    const open = Number(row[8] ?? 0) !== 0;
                    const membershipOpen = Number(row[9] ?? 0) !== 0;
                    const buttons = [
                        iconButton({
                            id: 'buttonedituser',
                            className: 'icon-edit button-tooltip-target',
                            tooltip: 'Ubah',
                            icon: 'fa-pen',
                            dataId: zipToJson(['id','identity_number','name','email','role'], [row[1],row[2],row[3],row[4],row[5]])
                        }),
                        iconButton({
                            id: 'buttondeleteuser',
                            className: 'icon-delete button-tooltip-target',
                            tooltip: 'Hapus',
                            icon: 'fa-trash-can',
                            dataId: row[1]
                        })
                    ];

                    if (isStudent) {
                        buttons.push(accessSwitch({
                            tooltip: open ? 'Tutup Penyerahan' : 'Buka Penyerahan',
                            inputClass: 'row-submission-access',
                            value: row[1],
                            checked: open,
                            ariaLabel: 'Akses penyerahan'
                        }));
                        buttons.push(accessSwitch({
                            tooltip: membershipOpen ? 'Tutup Keanggotaan' : 'Buka Keanggotaan',
                            inputClass: 'row-membership-access',
                            value: row[1],
                            checked: membershipOpen,
                            ariaLabel: 'Akses keanggotaan',
                            extraClass: 'switch-access-row--membership'
                        }));
                    }

                    return buttonsMarkup(buttons, { group: true });
                } },
                { title: headerCheckMarkup('checkall', 'Hapus Semua'), data: null, width: '6%', className: 'no-sort', render: (_, __, row) => rowCheckMarkup('Pilih Hapus', row[1]) },
                detailSearchColumn(5, 6, 7)
            ])
        });

        $('#usertable').on('change', '.row-check', function() {
            $(this).closest('tr').toggleClass('selected', this.checked);
            $('#usertable #checkall').prop('checked', $('#usertable .row-check:not(:checked)').length === 0);
        });

        $('#usertable tbody').on('click', 'td.dt-control', function() {
            const tableRow = $(this).closest('tr'), row = userTable.row(tableRow), shown = !row.child.isShown();
            shown ? row.child(renderUserDetail(row.data())).show() : row.child.hide();
            tableRow.toggleClass('shown', shown);
            $(this).find('i').toggleClass('fa-chevron-right', !shown).toggleClass('fa-chevron-down', shown);
        });

        $('#usertable').on('change', '#checkall', function() {
            $('#usertable .row-check').prop('checked', this.checked)
                .closest('tbody').find('tr')
                .toggleClass('selected', this.checked);
        });

        if (canMutateData) {
            const submitUserSubmissionAccess = (userId, open, checkbox) => {
                const id = Number(userId);
                if (!Number.isFinite(id) || id <= 0) {
                    if (checkbox) checkbox.checked = !open;
                    return;
                }

                const revert = () => {
                    if (checkbox) checkbox.checked = !open;
                };

                const queueOffline = async () => {
                    await OfflineSync.queueUserSubmissionAccess(csrfToken, id, open);
                    if (typeof showSuccess === 'function') {
                        showSuccess(
                            'Tersimpan secara luring.',
                            'Perubahan akses penyerahan akan disinkronkan saat koneksi kembali.'
                        );
                    }
                };

                if (typeof OfflineSync !== 'undefined' && OfflineSync.isOffline()) {
                    return void queueOffline();
                }

                $.ajax({
                    url: '/user/submission-access',
                    type: 'POST',
                    data: { _token: csrfToken, id, open: open ? 1 : 0 },
                    success: response => {
                        const isOpen = !!response.open;
                        const tableRow = checkbox ? $(checkbox).closest('tr') : $();
                        if (userTable && tableRow.length) {
                            const dtRow = userTable.row(tableRow);
                            const data = dtRow.data();
                            if (Array.isArray(data)) {
                                data[8] = isOpen ? 1 : 0;
                                dtRow.invalidate().draw(false);
                            }
                        }
                        if (typeof showSuccess === 'function') {
                            showSuccess(
                                isOpen ? 'Akses penyerahan dibuka.' : 'Akses penyerahan ditutup.',
                                response.message || ''
                            );
                        }
                    },
                    error: xhr => {
                        if (xhr.status === 0) return void queueOffline();
                        revert();
                        if (typeof showError === 'function') {
                            showError(
                                'Gagal mengubah akses penyerahan.',
                                xhr.responseJSON?.message || xhr.responseJSON || 'Terjadi kesalahan pada peladen.'
                            );
                        }
                    }
                });
            };

            $('#usertable').on('change', '.row-submission-access', function (event) {
                event.stopPropagation();
                submitUserSubmissionAccess(this.value, this.checked, this);
            });

            const submitUserMembershipAccess = (userId, open, checkbox) => {
                const id = Number(userId);
                if (!Number.isFinite(id) || id <= 0) {
                    if (checkbox) checkbox.checked = !open;
                    return;
                }

                const revert = () => {
                    if (checkbox) checkbox.checked = !open;
                };

                const queueOffline = async () => {
                    await OfflineSync.queueUserMembershipAccess(csrfToken, id, open);
                    if (typeof showSuccess === 'function') {
                        showSuccess(
                            'Tersimpan secara luring.',
                            'Perubahan akses anggota akan disinkronkan saat koneksi kembali.'
                        );
                    }
                };

                if (typeof OfflineSync !== 'undefined' && OfflineSync.isOffline()) {
                    return void queueOffline();
                }

                $.ajax({
                    url: '/user/membership-access',
                    type: 'POST',
                    data: { _token: csrfToken, id, open: open ? 1 : 0 },
                    success: response => {
                        const isOpen = !!response.open;
                        const tableRow = checkbox ? $(checkbox).closest('tr') : $();
                        if (userTable && tableRow.length) {
                            const dtRow = userTable.row(tableRow);
                            const data = dtRow.data();
                            if (Array.isArray(data)) {
                                data[9] = isOpen ? 1 : 0;
                                dtRow.invalidate().draw(false);
                            }
                        }
                        if (typeof showSuccess === 'function') {
                            showSuccess(
                                isOpen ? 'Akses keanggotaan dibuka.' : 'Akses keanggotaan ditutup.',
                                response.message || ''
                            );
                        }
                    },
                    error: xhr => {
                        if (xhr.status === 0) return void queueOffline();
                        revert();
                        if (typeof showError === 'function') {
                            showError(
                                'Gagal mengubah akses anggota.',
                                xhr.responseJSON?.message || xhr.responseJSON || 'Terjadi kesalahan pada peladen.'
                            );
                        }
                    }
                });
            };

            $('#usertable').on('change', '.row-membership-access', function (event) {
                event.stopPropagation();
                submitUserMembershipAccess(this.value, this.checked, this);
            });
        }
    }


    // --- Tabel Impor ---
    if ($('#importtable').length) {
        importTable = $('#importtable').DataTable({
            ...sharedOptions,
            serverSide: false,
            language: {
                ...sharedLanguage,
                searchPlaceholder: 'Cari Impor…'
            },
            columns: finalizeColumns([
                { title: 'Detail', data: null, width: '5%', className: 'dt-control no-sort', render: () => detailToggleMarkup() },
                { title: 'No.', data: 0, name: 'id', width: '5%' },
                { title: 'NIM', data: 1, width: '15%' },
                { title: 'Nama Mahasiswa', data: 2, width: '30%' },
                { title: 'Program Studi', data: 4, width: '20%' },
                { title: 'Pengaturan', data: null, width: '25%', className: 'no-sort', retainForViewer: true, render: (_, __, row) => {
                    const info = iconButton({
                        id: 'buttoninfoimport',
                        className: 'icon-primary button-tooltip-target',
                        tooltip: 'Info',
                        icon: 'fa-info',
                        dataId: zipToJson(['model','temperature','processing_time_ms','prompt_tokens','completion_tokens','total_tokens','finish_reason','extracted_fields','total_fields','completion_percentage'], [row[10],row[11],row[12],row[13],row[14],row[15],row[16],row[17],row[18],row[19]])
                    });
                    if (!canMutateData) return markup(info);
                    return buttonsMarkup([
                        info,
                        iconButton({
                            id: 'buttoneditimport',
                            className: 'icon-edit button-tooltip-target',
                            tooltip: 'Ubah',
                            icon: 'fa-pen',
                            dataId: zipToJson(['id','nim','student_name','final_project_title','program_study','degree_level','final_project_year','submission_date','final_project_file','approval_sheet','final_project_type_id'], [row[0],row[1],row[2],row[3],row[4],row[5],row[6],row[7],row[8],row[9],row[20]])
                        }),
                        iconButton({
                            id: 'buttondeleteimport',
                            className: 'icon-delete button-tooltip-target',
                            tooltip: 'Hapus',
                            icon: 'fa-trash-can',
                            dataId: row[0]
                        })
                    ]);
                }},
                { title: headerCheckMarkup('checkall', 'Hapus Semua'), data: null, width: '10%', className: 'no-sort', render: (_, __, row) => rowCheckMarkup('Pilih Hapus', row[0]) },
                detailSearchColumn(3, 5, 6, 7, 8, 9)
            ])
        });

        $('#importtable').on('change', '.row-check', function() {
            $(this).closest('tr').toggleClass('selected', this.checked);
            $('#importtable #checkall').prop('checked', $('#importtable .row-check:not(:checked)').length === 0);
        });

        $('#importtable tbody').on('click', 'td.dt-control', function() {
            const tableRow = $(this).closest('tr'), row = importTable.row(tableRow), shown = !row.child.isShown();
            shown ? row.child(renderImportDetail(row.data())).show() : row.child.hide();
            tableRow.toggleClass('shown', shown);
            $(this).find('i').toggleClass('fa-chevron-right', !shown).toggleClass('fa-chevron-down', shown);
        });

        $('#importtable').on('change', '#checkall', function() {
            $('#importtable .row-check').prop('checked', this.checked)
                .closest('tbody').find('tr')
                .toggleClass('selected', this.checked);
        });
    }

    $('.dataTables_wrapper').each(function() {
        decorateStaffTableSearch(this);
    });
});
