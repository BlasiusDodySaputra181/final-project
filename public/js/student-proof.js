(() => {
    initProofTable();
    initProofLetterDownloads();

    function initProofTable() {
        const tableEl = document.getElementById('publicprooftable');
        if (!tableEl || typeof jQuery === 'undefined' || !jQuery.fn.DataTable) return;

        const $ = jQuery;
        const indexUrl = tableEl.dataset.indexUrl || '';
        const detailPrefix = tableEl.dataset.detailPrefix || '/bukti/';

        const language = {
            decimal: '',
            emptyTable: 'Tidak ada data tersedia',
            info: 'Tampil _START_–_END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(dari _MAX_ total)',
            thousands: '.',
            lengthMenu: 'Tampil _MENU_',
            loadingRecords: 'Memuat…',
            processing: processingText,
            search: '',
            searchPlaceholder: 'Cari di tabel…',
            zeroRecords: 'Tidak ada data yang cocok',
            paginate: paginateLabels
        };

        let table;
        let xhr = null;

        table = $('#publicprooftable').DataTable({
            serverSide: true,
            processing: true,
            searching: true,
            deferRender: true,
            autoWidth: false,
            responsive: false,
            pageLength: 25,
            searchDelay: 160,
            lengthMenu: [10, 25, 50, 100],
            order: [[7, 'desc']],
            language,
            infoCallback: (settings, start, end, max, total, pre) => (
                typeof dataTablesInfoCallback === 'function'
                    ? dataTablesInfoCallback(settings, start, end, max, total)
                    : pre
            ),
            dom: '<"student-dt-top"<"student-dt-top-left"l><"student-dt-top-right"f>><"student-dt-frame"rt><"student-dt-bottom"ip>',
            ajax: (dtRequest, callback) => {
                if (xhr && xhr.readyState !== 4) {
                    xhr.abort();
                }

                xhr = $.ajax({
                    url: indexUrl,
                    timeout: 30000,
                    data: typeof slimDataTablesRequest === 'function'
                        ? slimDataTablesRequest(dtRequest)
                        : (dtRequest || {}),
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
                                ? 'Data bukti belum tersedia secara luring. Sambungkan jaringan, lalu buka halaman ini sekali.'
                                : (textStatus === 'timeout'
                                    ? 'Koneksi lambat. Coba lagi.'
                                    : 'Gagal memuat data bukti. Coba beberapa saat lagi.');
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
                { data: 3, className: 'col-title proof-title-cell', name: 'final_project_title' },
                { data: 4, className: 'col-study-program', name: 'program_study' },
                { data: 5, className: 'col-degree', name: 'degree_level' },
                { data: 6, className: 'col-year', name: 'final_project_year' },
                { data: 7, className: 'col-date', name: 'submission_date' },
                {
                    data: 8,
                    orderable: false,
                    searchable: false,
                    className: 'no-sort col-action',
                    render: (id) => {
                        const link = document.createElement('a');
                        link.href = `${detailPrefix}${encodeURIComponent(id)}`;
                        link.className = 'proof-detail-btn';
                        const label = document.createElement('span');
                        label.textContent = 'Lihat';
                        link.append(faIcon('fa-eye'), label);
                        return markup(link);
                    }
                }
            ]
        });

        table.on('processing.dt', (_event, _settings, processing) => {
            $(table.table().container()).toggleClass('is-processing', processing);
        });
    }

    function initProofLetterDownloads() {
        const modal = document.getElementById('prooflettermodal');
        const titleEl = document.getElementById('prooflettertitle');
        const textEl = document.getElementById('prooflettertext');
        const membershipError = 'Keanggotaan belum terdaftar. Surat hanya dapat diunduh untuk tugas akhir yang sudah terdaftar keanggotaan.';

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

        document.addEventListener('click', (event) => {
            if (event.target === modal || event.target.closest('[data-proof-letter-close]')) {
                event.preventDefault();
                closeError();
                return;
            }

            const link = event.target.closest('[data-proof-letter]');
            if (!link) return;

            if (!navigator.onLine) {
                event.preventDefault();
                showError('Gagal unduh surat.', 'Unduh surat membutuhkan koneksi daring.');
                return;
            }

            const membership = String(link.getAttribute('data-membership') ?? '').trim();
            if (membership === 'Terdaftar') return;

            event.preventDefault();
            showError('Gagal unduh surat.', membershipError);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal?.classList.contains('is-open')) {
                closeError();
            }
        });
    }
})();
