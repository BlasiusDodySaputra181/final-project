(() => {
    const tableEl = document.getElementById('publicsearchtable');
    if (!tableEl || typeof jQuery === 'undefined' || !jQuery.fn.DataTable) return;

    const indexUrl = tableEl.dataset.indexUrl || '';

    const $ = jQuery;
    const $input = $('#search-q');
    const $reset = $('#search-reset');
    const $lengthSlot = $('#search-length-slot');
    let keyword = '';

    const language = {
        decimal: '',
        emptyTable: 'Tidak ada data tugas akhir',
        info: 'Tampil _START_–_END_ dari _TOTAL_ data',
        infoEmpty: 'Tidak ada data',
        infoFiltered: '(dari _MAX_ total)',
        thousands: '.',
        lengthMenu: 'Tampil _MENU_',
        loadingRecords: 'Memuat…',
        processing: processingText,
        search: '',
        zeroRecords: 'Tidak ada data yang cocok.',
        paginate: paginateLabels
    };

    let table;
    let xhr = null;
    let searchTimer = 0;

    table = $('#publicsearchtable').DataTable({
        serverSide: true,
        processing: true,
        searching: false,
        deferRender: true,
        autoWidth: false,
        responsive: false,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [],
        language,
        infoCallback: (settings, start, end, max, total, pre) => (
            typeof dataTablesInfoCallback === 'function'
                ? dataTablesInfoCallback(settings, start, end, max, total)
                : pre
        ),
        dom: '<"student-dt-length-source"l><"student-dt-frame"rt><"student-dt-bottom"ip>',
        ajax: (dtRequest, callback) => {
            if (xhr && xhr.readyState !== 4) {
                xhr.abort();
            }

            xhr = $.ajax({
                url: indexUrl,
                timeout: 30000,
                data: typeof slimDataTablesRequest === 'function'
                    ? slimDataTablesRequest(dtRequest, { q: keyword })
                    : Object.assign({}, dtRequest || {}, { q: keyword }),
                dataType: 'json',
                cache: false
            })
                .done(json => callback(json || { data: [] }))
                .fail((_xhr, textStatus) => {
                    if (textStatus === 'abort') {
                        return;
                    }

                    const offline = typeof PwaRegister !== 'undefined' && PwaRegister.isOffline?.();
                    const settings = table.settings()[0];
                    if (settings?.oLanguage) {
                        settings.oLanguage.sEmptyTable = offline
                            ? 'Pencarian membutuhkan koneksi. Sambungkan jaringan, lalu coba lagi.'
                            : (textStatus === 'timeout'
                                ? 'Koneksi lambat. Coba lagi.'
                                : 'Gagal memuat hasil. Coba beberapa saat lagi.');
                    }
                    callback({
                        draw: Number(dtRequest?.draw || 1),
                        recordsTotal: 0,
                        recordsFiltered: 0,
                        data: []
                    });
                });
        },
        columns: [
            {
                data: 0,
                className: 'col-no no-sort',
                name: 'id',
                orderable: false,
                searchable: false,
                render: (data, type) => (
                    typeof renderCountLabel === 'function'
                        ? renderCountLabel(data, type)
                        : data
                )
            },
            { data: 1, className: 'col-nim', name: 'nim' },
            { data: 2, className: 'col-name', name: 'student_name' },
            { data: 3, className: 'col-title', name: 'final_project_title' },
            { data: 4, className: 'col-study-program', name: 'program_study' },
            { data: 5, className: 'col-degree', name: 'degree_level' },
            { data: 6, className: 'col-year', name: 'final_project_year' },
            { data: 7, className: 'col-callno', name: 'call_number' }
        ],
        columnDefs: [
            {
                targets: [1, 2, 3, 4, 5, 6, 7],
                render: (data, type) => {
                    if (type === 'display' || type === 'filter') {
                        return data == null || data === '' ? '-' : data;
                    }
                    return data;
                }
            }
        ],
        initComplete: function () {
            const $length = $(this.api().table().container()).find('.dataTables_length');
            if ($lengthSlot.length && $length.length) {
                $lengthSlot.append($length);
            }
        }
    });

    table.on('processing.dt', (_event, _settings, processing) => {
        $(table.table().container()).toggleClass('is-processing', processing);
    });

    const applyKeyword = (value) => {
        keyword = String(value || '').trim();
        $reset.prop('hidden', keyword === '');
        table.ajax.reload();
    };

    $input.on('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => applyKeyword($input.val()), 160);
    });

    $('#publicsearchform').on('submit', (event) => {
        event.preventDefault();
        window.clearTimeout(searchTimer);
        applyKeyword($input.val());
    });

    $reset.on('click', () => {
        window.clearTimeout(searchTimer);
        $input.val('');
        applyKeyword('');
    });
})();
