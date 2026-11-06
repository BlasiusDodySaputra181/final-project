/* Peta perawatan — student-submission.js
 * Tanggal, slider langkah, validasi, pemilih, hibah buku, unggah berkas.
 */
(() => {
    const form = document.getElementById('submissionpublicform');
    if (!form) return;

    const submissionDatepickerLocale = {
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

    let submissionDatePicker = null;

    // --- Tanggal ---

    function toDatabaseDate(date) {
        return date instanceof Date && !Number.isNaN(date.getTime())
            ? date.toLocaleDateString('sv-SE')
            : '';
    }

    function parseDateValue(value) {
        const raw = String(value || '').trim();
        if (!raw) return new Date();

        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
            const [year, month, day] = raw.split('-').map(Number);
            return new Date(year, month - 1, day);
        }

        const localized = raw.match(/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/);
        if (localized) {
            const day = Number(localized[1]);
            const monthIndex = submissionDatepickerLocale.months.findIndex(
                (month) => month.toLowerCase() === localized[2].toLowerCase()
            );
            const year = Number(localized[3]);
            if (monthIndex >= 0) {
                return new Date(year, monthIndex, day);
            }
        }

        const parsed = new Date(raw);
        return Number.isNaN(parsed.getTime()) ? new Date() : parsed;
    }

    function syncSubmissionDateField(date) {
        const hidden = document.getElementById('submission_date');
        if (hidden) hidden.value = toDatabaseDate(date);
    }

    const totalSteps = 3;
    const initialStep = Number.parseInt(form.dataset.initialStep || '0', 10);
    let currentStep = Number.isInteger(initialStep)
        ? Math.min(Math.max(initialStep, 0), totalSteps - 1)
        : 0;
    let bookCounter = Math.max(
        1,
        document.querySelectorAll('#booksContainer .book-entry').length
    );

    // --- Langkah formulir ---

    const slides = () => document.querySelectorAll('.slide');

    function updateSlider() {
        slides().forEach((slide, index) => {
            slide.classList.toggle('active-slide', index === currentStep);
        });

        document.querySelectorAll('.step').forEach((step, index) => {
            step.classList.toggle('active', index === currentStep);
        });

        const line1 = document.getElementById('line-1');
        const line2 = document.getElementById('line-2');
        if (line1) line1.classList.toggle('active', currentStep >= 1);
        if (line2) line2.classList.toggle('active', currentStep >= 2);

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // --- Validasi langkah ---

    function clearFieldError(el) {
        if (!el) return;
        el.classList.remove('input-error');
        const group = el.closest('.form-group') || el.closest('.upload-area');
        group?.querySelectorAll('.js-field-error').forEach((node) => node.remove());
        if (el.classList?.contains('custom-select-trigger')) {
            el.classList.remove('input-error');
        }
    }

    function showFieldError(el, message) {
        if (!el) return;
        const target = el.closest?.('.custom-select')
            ? el.closest('.custom-select').querySelector('.custom-select-trigger')
            : el.closest?.('.upload-area') || el;

        target.classList.add('input-error');

        const container = el.closest('.form-group')
            || el.closest('.upload-area')
            || el.parentElement;

        if (!container) return;

        let error = container.querySelector('.js-field-error');
        if (!error) {
            error = document.createElement('div');
            error.className = 'field-error js-field-error';
            error.setAttribute('role', 'alert');
            container.appendChild(error);
        }
        error.textContent = message;
    }

    function valueOf(name) {
        const el = form.elements.namedItem(name);
        if (!el) return '';
        if (el instanceof RadioNodeList) return el.value?.trim() || '';
        return (el.value || '').trim();
    }

    function validateStep(stepIndex) {
        let valid = true;
        const withoutBooks = document.getElementById('without_books')?.checked;

        const requireText = (name, message) => {
            const el = form.elements.namedItem(name);
            clearFieldError(el?.closest?.('.custom-select')?.querySelector('.custom-select-trigger') || el);
            if (!valueOf(name)) {
                showFieldError(el, message);
                valid = false;
            }
        };

        const requireFile = (name, message) => {
            const el = form.elements.namedItem(name);
            const area = el?.closest?.('.upload-area');
            area?.classList.remove('input-error', 'is-invalid');
            area?.querySelectorAll('.js-field-error').forEach((node) => node.remove());
            if (!el?.files?.length) {
                if (area) {
                    area.classList.add('input-error', 'is-invalid');
                    showFieldError(el, message);
                }
                valid = false;
            }
        };

        if (stepIndex === 0) {
            requireText('nim', 'NIM harus diisi!');
            requireText('student_name', 'Nama mahasiswa harus diisi!');
            requireText('program_study', 'Program studi harus dipilih!');
            requireText('degree_level', 'Jenjang harus dipilih!');
            requireText('submission_date', 'Tanggal penyerahan harus diisi!');
            requireText('semester_graduated', 'Semester lulus harus diisi!');
            requireText('graduation_year', 'Tahun lulus harus diisi!');
            requireText('final_project_year', 'Tahun tugas akhir harus diisi!');
            requireText('final_project_title', 'Judul tugas akhir harus diisi!');
            requireFile('final_project_file', 'Berkas tugas akhir harus diunggah!');
            requireFile('approval_sheet', 'Lembar persetujuan harus diunggah!');
        }

        if (stepIndex === 1) {
            if (withoutBooks) {
                return valid;
            }

            const entries = document.querySelectorAll('#booksContainer .book-entry');
            if (!entries.length) {
                valid = false;
                showFieldError(
                    document.getElementById('addBookBtn'),
                    'Isi minimal satu hibah buku, atau centang Tanpa Buku.'
                );
                return valid;
            }

            entries.forEach((entry) => {
                const author = entry.querySelector('input[name*="[author]"]');
                const year = entry.querySelector('input[name*="[book_year]"]');
                const title = entry.querySelector('textarea[name*="[book_title]"]');

                [author, year, title].forEach(clearFieldError);

                if (!author?.value.trim()) {
                    showFieldError(author, 'Pengarang buku harus diisi!');
                    valid = false;
                }
                if (!year?.value.trim()) {
                    showFieldError(year, 'Tahun buku harus diisi!');
                    valid = false;
                }
                if (!title?.value.trim()) {
                    showFieldError(title, 'Judul buku harus diisi!');
                    valid = false;
                }
            });
        }

        if (stepIndex === 2) {
            requireText('phone_number', 'Nomor telepon harus diisi!');
            requireText('email', 'Email harus diisi!');

            const email = valueOf('email');
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showFieldError(form.elements.namedItem('email'), 'Format email tidak valid!');
                valid = false;
            }
        }


        return valid;
    }

    // --- Tahun, tanggal, dan pilihan ---

    function initYearPickers(root = document) {
        if (typeof AirDatepicker === 'undefined') return;

        root.querySelectorAll('.years-picker').forEach((el) => {
            if (el.airDatepicker) return;
            new AirDatepicker(el, {
                view: 'years',
                minView: 'years',
                autoClose: true,
                dateFormat: 'yyyy',
                locale: submissionDatepickerLocale,
            });
        });
    }

    function initDatePickers(root = document) {
        if (typeof AirDatepicker === 'undefined') return;

        root.querySelectorAll('.date-picker').forEach((el) => {
            if (el.airDatepicker) return;

            const hidden = document.getElementById('submission_date');
            const initial = parseDateValue(hidden?.value || el.value);

            const picker = new AirDatepicker(el, {
                autoClose: true,
                locale: submissionDatepickerLocale,
                selectedDates: [initial],
                onSelect({ date }) {
                    const selected = Array.isArray(date) ? date[0] : date;
                    syncSubmissionDateField(selected);
                    clearFieldError(hidden);
                    clearFieldError(el);
                },
            });

            if (el.id === 'submission_date_display') {
                submissionDatePicker = picker;
            }

            syncSubmissionDateField(initial);
        });
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
            const options = () => optionsWrap.querySelectorAll('.custom-option');
            const hiddenInput = select.querySelector('input[type="hidden"]:not([id="final_project_type_id"])')
                || select.querySelector('input[type="hidden"]');
            const typeIdInput = select.querySelector('#final_project_type_id');
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

            trigger?.addEventListener('click', (e) => {
                e.stopPropagation();
                if (select.classList.contains('active')) {
                    closeSelect();
                } else {
                    openSelect();
                }
            });

            searchInput?.addEventListener('click', (e) => e.stopPropagation());
            searchInput?.addEventListener('mousedown', (e) => e.stopPropagation());
            searchInput?.addEventListener('input', () => {
                filterSelectOptions(select, searchInput.value);
            });
            searchInput?.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeSelect();
                }
            });

            options().forEach((option) => {
                option.addEventListener('click', () => {
                    if (option.classList.contains('is-filtered-out') || option.classList.contains('is-hidden')) {
                        return;
                    }

                    options().forEach((o) => o.classList.remove('selected'));
                    option.classList.add('selected');

                    const label = option.dataset.label
                        || option.querySelector('.option-title')?.textContent?.trim()
                        || option.textContent.trim();

                    if (text) {
                        text.textContent = label;
                        text.classList.toggle('contain', Boolean(option.dataset.value));
                    }

                    if (hiddenInput) {
                        hiddenInput.value = option.dataset.value || '';
                        clearFieldError(trigger);
                        hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    if (typeIdInput || option.dataset.typeIdTarget) {
                        const target = typeIdInput
                            || document.getElementById(option.dataset.typeIdTarget);
                        if (target) target.value = option.dataset.id || '';
                    }

                    closeSelect();
                });
            });
        });
    }

    // --- Hibah buku ---

    function updateBookCountBadge() {
        const badge = document.getElementById('bookCountBadge');
        const withoutBooks = document.getElementById('without_books')?.checked;
        const count = document.querySelectorAll('#booksContainer .book-entry').length;

        if (!badge) return;

        if (withoutBooks) {
            badge.textContent = 'Tanpa buku';
            return;
        }

        badge.textContent = count === 1 ? '1 buku' : `${count} buku`;
    }

    function reindexBookEntries() {
        document.querySelectorAll('#booksContainer .book-entry').forEach((entry, index) => {
            entry.dataset.bookIndex = String(index);

            const title = entry.querySelector('.clone-title');
            if (title) title.textContent = `Form Buku ${index + 1}`;

            const author = entry.querySelector('input[name*="[author]"]');
            const year = entry.querySelector('input[name*="[book_year]"]');
            const bookTitle = entry.querySelector('textarea[name*="[book_title]"]');

            if (author) author.name = `books[${index}][author]`;
            if (year) year.name = `books[${index}][book_year]`;
            if (bookTitle) bookTitle.name = `books[${index}][book_title]`;

            const removeBtn = entry.querySelector('.remove-book-btn');
            if (index === 0) {
                removeBtn?.remove();
            } else if (!removeBtn) {
                const header = entry.querySelector('.clone-header');
                if (header) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-danger remove-book-btn';
                    button.append(document.createTextNode('Hapus Formulir '), faIcon('fa-minus'));
                    header.appendChild(button);
                }
            }
        });

        bookCounter = document.querySelectorAll('#booksContainer .book-entry').length;
        updateBookCountBadge();
    }

    function updateBookDisabledState() {
        const withoutBooks = document.getElementById('without_books');
        const wrapper = document.getElementById('bookFormWrapper');
        if (!withoutBooks || !wrapper) return;

        const disabled = withoutBooks.checked;
        wrapper.classList.toggle('form-disabled', disabled);
        wrapper.querySelectorAll('input, textarea, button, select').forEach((field) => {
            if (field.id === 'without_books') return;
            field.disabled = disabled;
        });

        updateBookCountBadge();
    }

    function createBookEntry() {
        const template = document.getElementById('tpl-book-entry');
        return template.content.firstElementChild.cloneNode(true);
    }

    function submissionNotice(kind, title, text) {
        const alert = document.createElement('div');
        alert.className = `submission-alert submission-alert--${kind} js-offline-alert`;
        alert.setAttribute('role', kind === 'success' ? 'status' : 'alert');

        const iconWrap = document.createElement('div');
        iconWrap.className = 'submission-alert__icon';
        iconWrap.setAttribute('aria-hidden', 'true');
        iconWrap.append(faIcon(kind === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'));

        const body = document.createElement('div');
        body.className = 'submission-alert__body';
        const heading = document.createElement('strong');
        heading.className = 'submission-alert__title';
        heading.textContent = title;
        const paragraph = document.createElement('p');
        paragraph.className = 'submission-alert__text';
        paragraph.textContent = text;
        body.append(heading, paragraph);
        alert.append(iconWrap, body);
        return alert;
    }

    function renderStudentFileName(area, fileNameEl, file) {
        if (!file) {
            fileNameEl.classList.remove('has-file');
            area.classList.remove('has-file');
            fileNameEl.textContent = 'Drag & Drop PDF';
            return;
        }

        area.classList.add('has-file');
        fileNameEl.classList.add('has-file');
        fillCheckedFileName(fileNameEl, file.name, `${(file.size / 1048576).toFixed(2)} MB`);
    }

    // --- Unggah berkas ---

    function initUploads() {
        document.querySelectorAll('.upload-area').forEach((area) => {
            const input = area.querySelector('.upload-input');
            const fileName = area.querySelector('.file-name');
            if (!input || !fileName) return;

            area.addEventListener('click', (e) => {
                if (e.target.closest('label')) return;
                input.click();
            });

            input.addEventListener('change', () => {
                if (input.files?.length) {
                    renderStudentFileName(area, fileName, input.files[0]);
                    area.classList.remove('input-error', 'is-invalid');
                    area.querySelectorAll('.js-field-error').forEach((node) => node.remove());
                }
            });

            area.addEventListener('dragover', (e) => {
                e.preventDefault();
                area.classList.add('dragover');
            });

            area.addEventListener('dragleave', () => {
                area.classList.remove('dragover');
            });

            area.addEventListener('drop', (e) => {
                e.preventDefault();
                area.classList.remove('dragover');
                const files = e.dataTransfer?.files;
                if (!files?.length) return;

                const transfer = new DataTransfer();
                transfer.items.add(files[0]);
                input.files = transfer.files;
                renderStudentFileName(area, fileName, files[0]);
                area.classList.remove('input-error', 'is-invalid');
                area.querySelectorAll('.js-field-error').forEach((node) => node.remove());
            });
        });
    }

    document.querySelectorAll('.next-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!validateStep(currentStep)) return;
            if (currentStep < totalSteps - 1) {
                currentStep += 1;
                updateSlider();
            }
        });
    });

    document.querySelectorAll('.prev-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (currentStep > 0) {
                currentStep -= 1;
                updateSlider();
            }
        });
    });

    document.getElementById('without_books')?.addEventListener('change', updateBookDisabledState);

    document.getElementById('addBookBtn')?.addEventListener('click', () => {
        const withoutBooks = document.getElementById('without_books');
        if (withoutBooks?.checked) return;

        const container = document.getElementById('booksContainer');
        if (!container) return;

        const entry = createBookEntry();
        container.appendChild(entry);
        bookCounter += 1;
        reindexBookEntries();
        initYearPickers(entry);
        updateBookDisabledState();
    });

    document.addEventListener('click', (e) => {
        const removeBtn = e.target.closest('.remove-book-btn');
        if (removeBtn) {
            const entries = document.querySelectorAll('#booksContainer .book-entry');
            if (entries.length <= 1) return;

            removeBtn.closest('.book-entry')?.remove();
            reindexBookEntries();
            updateBookDisabledState();
            return;
        }

        if (!e.target.closest('.custom-select')) {
            document.querySelectorAll('.custom-select').forEach((el) => {
                el.classList.remove('active');
                resetSelectFilter(el);
            });
        }
    });

    let isSubmitting = false;

    form.addEventListener('submit', async (e) => {
        if (isSubmitting) {
            e.preventDefault();
            return;
        }

        syncSubmissionDateField(submissionDatePicker?.selectedDates?.[0]);
        reindexBookEntries();

        for (let step = 0; step < totalSteps; step += 1) {
            if (!validateStep(step)) {
                e.preventDefault();
                currentStep = step;
                updateSlider();
                return;
            }
        }

        isSubmitting = true;
        const submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        if (window.PwaRegister?.isOffline?.() && typeof OfflineDatabase !== 'undefined') {
            e.preventDefault();

            try {
                const payload = PwaRegister.formToOutboxPayload(form);
                await PwaRegister.queueAction({
                    key: `action:public-submission:${Date.now()}`,
                    url: form.action || '/serah',
                    module: 'submission',
                    fields: payload.fields,
                    files: payload.files
                });

                const alertHost = form.closest('.portal-card') || form.parentElement;
                alertHost?.querySelector('.js-offline-alert')?.remove();

                const alert = submissionNotice(
                    'success',
                    'Tersimpan Offline',
                    'Data penyerahan disimpan di perangkat dan akan dikirim otomatis saat koneksi kembali.'
                );
                form.before(alert);
                form.reset();
                // Readonly fields ikut reset ke nilai awal halaman (NIM/nama akun).
                form.querySelectorAll('input.is-account-locked').forEach((input) => {
                    input.value = input.defaultValue;
                });
                const resetDate = parseDateValue(
                    document.getElementById('submission_date')?.defaultValue
                    || document.getElementById('submission_date_display')?.defaultValue
                );
                submissionDatePicker?.selectDate(resetDate);
                syncSubmissionDateField(resetDate);
                document.querySelectorAll('#booksContainer .book-entry').forEach((entry, index) => {
                    if (index > 0) entry.remove();
                });
                bookCounter = 1;
                reindexBookEntries();
                updateBookDisabledState();
                currentStep = 0;
                updateSlider();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (error) {
                console.error(error);
                const alertHost = form.closest('.portal-card') || form.parentElement;
                alertHost?.querySelector('.js-offline-alert')?.remove();
                const alert = submissionNotice(
                    'error',
                    'Gagal menyimpan secara luring',
                    'Penyimpanan di perangkat gagal. Izinkan penyimpanan situs atau keluar dari mode privat, lalu coba lagi.'
                );
                form.before(alert);
            } finally {
                isSubmitting = false;
                if (submitBtn) submitBtn.disabled = false;
            }
        }
    });

    initCustomSelect();
    initYearPickers();
    initDatePickers();
    initUploads();
    reindexBookEntries();
    updateBookDisabledState();
    updateSlider();
})();
