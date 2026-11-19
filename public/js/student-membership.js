(() => {
    const form = document.getElementById('membershippublicform');
    if (!form) return;

    const membershipDatepickerLocale = {
        days: ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'],
        daysShort: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'],
        daysMin: ['Mg', 'Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb'],
        months: [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ],
        monthsShort: [
            'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
            'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
        ],
        today: 'Hari ini',
        clear: 'Hapus',
        dateFormat: 'd MMMM yyyy',
        timeFormat: 'HH:mm',
        firstDay: 1,
    };

    function toDatabaseDate(date) {
        return date instanceof Date && !Number.isNaN(date.getTime())
            ? date.toLocaleDateString('sv-SE')
            : '';
    }

    function parseDateValue(value) {
        const raw = String(value || '').trim();
        if (!raw) return null;

        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
            const [year, month, day] = raw.split('-').map(Number);
            return new Date(year, month - 1, day);
        }

        const localized = raw.match(/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/);
        if (localized) {
            const day = Number(localized[1]);
            const monthIndex = membershipDatepickerLocale.months.findIndex(
                (month) => month.toLowerCase() === localized[2].toLowerCase()
            );
            const year = Number(localized[3]);
            if (monthIndex >= 0) {
                return new Date(year, monthIndex, day);
            }
        }

        const parsed = new Date(raw);
        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    function initDatePickers() {
        const el = document.getElementById('date_of_birth_display');
        const hidden = document.getElementById('date_of_birth');
        if (!el || el.dataset.datepickerReady === 'true') return;

        if (typeof AirDatepicker === 'undefined') {
            window.addEventListener('load', initDatePickers, { once: true });
            return;
        }

        const initial = parseDateValue(hidden?.value || el.value);
        const options = {
            autoClose: true,
            locale: membershipDatepickerLocale,
            maxDate: new Date(),
            startDate: initial || new Date(2000, 0, 1),
            onSelect({ date }) {
                const selected = Array.isArray(date) ? date[0] : date;
                if (hidden) hidden.value = toDatabaseDate(selected);
                clearFieldError(hidden);
                clearFieldError(el);
            },
        };

        if (initial) {
            options.selectedDates = [initial];
        }

        new AirDatepicker(el, options);
        el.dataset.datepickerReady = 'true';
    }

    function clearFieldError(el) {
        if (!el) return;
        el.classList.remove('input-error');
        const group = el.closest('.form-group');
        group?.querySelectorAll('.js-field-error').forEach((node) => node.remove());
        if (el.classList?.contains('custom-select-trigger')) {
            el.classList.remove('input-error');
        }
    }

    function showFieldError(el, message) {
        if (!el || !message) return;
        const target = el.closest?.('.custom-select')
            ? el.closest('.custom-select').querySelector('.custom-select-trigger')
            : el;
        clearFieldError(target);
        target.classList.add('input-error');

        const group = target.closest('.form-group');
        if (!group) return;

        const error = document.createElement('div');
        error.className = 'field-error js-field-error';
        error.setAttribute('role', 'alert');
        error.textContent = message;
        group.appendChild(error);
    }

    function matchesOptionSearch(text, query) {
        const normalizedQuery = String(query || '').trim().toLowerCase();
        if (!normalizedQuery) return true;
        return String(text || '').toLowerCase().includes(normalizedQuery);
    }

    function filterSelectOptions(select, query = '') {
        const options = select.querySelectorAll('.custom-select-options .custom-option');
        const emptyState = select.querySelector('.custom-select-empty:not([data-static-empty])');
        let visibleCount = 0;

        options.forEach((option) => {
            const haystack = [
                option.dataset.label,
                option.dataset.value,
                option.dataset.cluster,
                option.textContent,
            ].filter(Boolean).join(' ');

            const matched = matchesOptionSearch(haystack, query);
            option.classList.toggle('is-filtered-out', !matched);
            if (matched) visibleCount += 1;
        });

        if (emptyState) {
            emptyState.hidden = visibleCount > 0 || options.length === 0;
        }
    }

    function resetSelectFilter(select) {
        const searchInput = select.querySelector('.custom-select-search-input');
        if (searchInput) searchInput.value = '';
        filterSelectOptions(select, '');
    }

    function initCustomSelect(container = document) {
        container.querySelectorAll('.custom-select').forEach((select) => {
            if (select.dataset.initialized === 'true') return;
            select.dataset.initialized = 'true';

            const trigger = select.querySelector('.custom-select-trigger');
            const optionsWrap = select.querySelector('.custom-select-options') || select;
            const hiddenInput = select.querySelector('input[type="hidden"]');
            const text = trigger?.querySelector('span');
            const searchInput = select.querySelector('.custom-select-search-input');

            const openSelect = () => {
                document.querySelectorAll('.custom-select').forEach((el) => {
                    if (el !== select) {
                        el.classList.remove('active');
                        resetSelectFilter(el);
                    }
                });
                select.classList.add('active');
                if (searchInput) {
                    resetSelectFilter(select);
                    setTimeout(() => searchInput.focus(), 0);
                }
            };

            const closeSelect = () => {
                select.classList.remove('active');
                resetSelectFilter(select);
            };

            trigger?.addEventListener('click', (event) => {
                event.stopPropagation();
                if (select.classList.contains('active')) {
                    closeSelect();
                } else {
                    openSelect();
                }
            });

            optionsWrap.addEventListener('click', (event) => {
                const option = event.target.closest('.custom-option');
                if (!option || option.classList.contains('is-filtered-out')) return;

                optionsWrap.querySelectorAll('.custom-option').forEach((el) => el.classList.remove('selected'));
                option.classList.add('selected');

                const value = option.dataset.value || '';
                const label = option.dataset.label || option.textContent.trim();
                if (hiddenInput) hiddenInput.value = value;
                if (text) {
                    text.textContent = label;
                    text.classList.toggle('contain', value !== '');
                }

                clearFieldError(trigger);
                closeSelect();
            });

            searchInput?.addEventListener('input', () => {
                filterSelectOptions(select, searchInput.value);
            });
        });
    }

    initCustomSelect(form);
    initDatePickers();
    window.addEventListener('load', initDatePickers);

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.custom-select')) {
            document.querySelectorAll('.custom-select').forEach((el) => {
                el.classList.remove('active');
                resetSelectFilter(el);
            });
        }
    });

    form.addEventListener('submit', (event) => {
        const gender = document.getElementById('gender');
        const studyProgram = document.getElementById('study_program_id');
        const place = document.getElementById('place_of_birth');
        const birthDate = document.getElementById('date_of_birth');
        const birthDateDisplay = document.getElementById('date_of_birth_display');
        const origin = document.getElementById('origin_address');
        const current = document.getElementById('current_address');
        const submitBtn = document.getElementById('submitMembershipBtn');

        const rules = [
            { input: studyProgram, message: !studyProgram?.value ? 'Program studi harus dipilih!' : '' },
            { input: gender, message: !gender?.value ? 'Jenis kelamin harus dipilih!' : '' },
            { input: place, message: !place?.value.trim() ? 'Tempat lahir harus diisi!' : '' },
            { input: birthDateDisplay, message: !birthDate?.value.trim() ? 'Tanggal lahir harus diisi!' : '' },
            { input: origin, message: !origin?.value.trim() ? 'Alamat asal harus diisi!' : '' },
            { input: current, message: !current?.value.trim() ? 'Alamat sekarang harus diisi!' : '' },
        ];

        let firstInvalid = null;
        rules.forEach(({ input, message }) => {
            if (message) {
                showFieldError(input, message);
                if (!firstInvalid) firstInvalid = input;
            } else {
                const target = input?.closest?.('.custom-select')
                    ? input.closest('.custom-select').querySelector('.custom-select-trigger')
                    : input;
                clearFieldError(target);
            }
        });

        if (firstInvalid) {
            event.preventDefault();
            firstInvalid.closest('.form-group')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('is-busy');
        }
    });
})();
