$(document).ready(function() {
    // Menu TA Sidebar Toggle
    if (finalProjectMenu) {
        finalProjectMenu.onclick = event => (
            event.preventDefault(),
            finalProjectMenu.classList.toggle('open'),
            finalProjectSubmenu?.classList.toggle('show')
        );
    }

    // Profile Dropdown Toggle (klik area info, bukan isi menu)
    if (profileButton) {
        const profileInfo = profileButton.querySelector('.profile-info');
        const toggleProfile = event => {
            event.stopPropagation();
            dropdown?.classList.toggle('show');
        };

        (profileInfo || profileButton).onclick = toggleProfile;
        dropdown?.addEventListener('click', event => event.stopPropagation());
    }

    // Tutup dropdown profil & custom select hanya jika klik di luar
    document.addEventListener('click', event => {
        const target = event.target;

        if (!target.closest?.('#profilebtn') && !target.closest?.('#dropdown')) {
            dropdown?.classList.remove('show');
        }

        // Jangan tutup saat klik trigger, kotak cari, atau opsi di dalam custom select
        if (!target.closest?.('.custom-select')) {
            closeAllCustomSelects();
        }
    });

    // Modern Upload Box File Name Display
    document.querySelectorAll('.upload-box input[type="file"]').forEach(input => {
        input.onchange = () => {
            const uploadBox = input.closest('.upload-box');
            const fileNameEl = uploadBox?.querySelector('.file-name');
            if (!fileNameEl || !input.files?.length) return;

            const isFolder = input.hasAttribute('webkitdirectory') || input.hasAttribute('directory');
            if (isFolder) {
                const folder = input.files[0].webkitRelativePath?.split('/')[0] || input.files[0].name;
                renderUploadFileName(fileNameEl, folder, `${input.files.length} berkas`, 'Belum ada folder dipilih');
                return;
            }

            const file = input.files[0];
            renderUploadFileName(fileNameEl, file.name, formatFileSizeMb(file.size));
        };
    });

    // Siapkan pencarian pada semua custom select
    document.querySelectorAll('.custom-select').forEach(ensureCustomSelectSearch);

    // Buka/tutup custom select + fokus ke pencarian
    $(document).on('click', '.custom-select-trigger', function (event) {
        event.preventDefault();
        event.stopPropagation();

        const select = this.closest('.custom-select');
        ensureCustomSelectSearch(select);

        document.querySelectorAll('.custom-select').forEach(other => {
            if (other !== select) {
                other.classList.remove('active');
                resetCustomSelectFilter(other);
            }
        });

        const willOpen = !select.classList.contains('active');
        select.classList.toggle('active');

        if (willOpen) {
            resetCustomSelectFilter(select);
            if (isRemoteSelect(select)) {
                fetchRemoteSelectOptions(select, select.querySelector('.custom-select-search-input')?.value || '');
            }
            const searchInput = select.querySelector('.custom-select-search-input');
            requestAnimationFrame(() => searchInput?.focus());
        }
    });

    // Klik / mousedown di dalam dropdown (termasuk input cari) tidak menutup select
    $(document).on('mousedown click', '.custom-select-dropdown', event => {
        event.stopPropagation();
    });

    $(document).on('mousedown click', '.custom-select-search-input', event => {
        event.stopPropagation();
    });

    // Filter opsi saat mengetik (cocokkan setiap kata)
    $(document).on('input', '.custom-select-search-input', function () {
        const select = this.closest('.custom-select');

        if (isRemoteSelect(select)) {
            const query = this.value;
            const previous = remoteSelectTimers.get(select);
            if (previous) clearTimeout(previous);
            remoteSelectTimers.set(select, setTimeout(() => {
                fetchRemoteSelectOptions(select, query);
            }, 160));
            return;
        }

        filterCustomSelectOptions(select, this.value);
    });

    // Cegah blur/submit tak sengaja saat Enter di kotak cari
    $(document).on('keydown', '.custom-select-search-input', function (event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            this.closest('.custom-select')?.classList.remove('active');
            return;
        }

        if (event.key !== 'Enter') return;

        event.preventDefault();
        const select = this.closest('.custom-select');
        const firstVisible = select?.querySelector('.custom-option:not(.is-filtered-out)');
        firstVisible?.click();
    });

    // Delegasi klik opsi agar opsi yang di-refresh tetap aktif
    $(document).on('click', '.custom-select .custom-option', function (event) {
        event.stopPropagation();

        const option = this;
        if (option.classList.contains('is-filtered-out')) return;

        const select = option.closest('.custom-select');
        const trigger = select.querySelector('.custom-select-trigger');
        const typeIdInput = select.querySelector('input[type="hidden"][data-sync="type-id"]');
        const hiddenInput = [...select.querySelectorAll('input[type="hidden"]')].find(input => input !== typeIdInput);

        select.querySelectorAll('.custom-option').forEach(other => other.classList.remove('selected'));
        option.classList.add('selected');

        trigger.querySelector('span').textContent = option.dataset.label || option.textContent.trim();
        if (hiddenInput) {
            hiddenInput.value = option.dataset.value ?? '';
        }
        if (typeIdInput) {
            typeIdInput.value = option.dataset.id || '';
        }
        select.classList.remove('active');
        resetCustomSelectFilter(select);

        if (hiddenInput && $(hiddenInput).closest('form').length) {
            $(hiddenInput).valid();
        }
    });
});

const closeAllCustomSelects = () => {
    document.querySelectorAll('.custom-select.active').forEach(select => {
        select.classList.remove('active');
        resetCustomSelectFilter(select);
    });
};

const remoteSelectTimers = new WeakMap();
const remoteSelectXhr = new WeakMap();

const isRemoteSelect = select => Boolean(select?.getAttribute?.('data-remote-url'));

const customOptionSelector = value => {
    const escaped = (typeof CSS !== 'undefined' && CSS.escape)
        ? CSS.escape(String(value ?? ''))
        : String(value ?? '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');

    return `.custom-option[data-value="${escaped}"]`;
};

const remoteSelectOption = item => {
    const value = item.value ?? item.id;
    const label = item.label
        ?? `${item.nim || '-'} — ${item.student_name || '-'}`;

    return customOption({ id: value, value, label });
};

const fetchRemoteSelectOptions = (select, query = '', ids = []) => {
    const url = select?.getAttribute('data-remote-url');
    if (!url) return $.Deferred().resolve([]).promise();

    const previous = remoteSelectXhr.get(select);
    if (previous && previous.readyState !== 4) {
        previous.abort();
    }

    const params = { q: query, limit: 30 };
    if (ids.length) params.ids = ids;

    select.classList.add('is-loading');

    const request = $.ajax({
        url,
        data: params,
        dataType: 'json',
        timeout: 15000
    })
        .done(items => {
            const list = Array.isArray(items) ? items : [];
            setCustomSelectOptions(select, list.map(remoteSelectOption));

            const current = select.querySelector('input[type="hidden"]')?.value;
            if (!current) return;

            const option = select.querySelector(customOptionSelector(current));
            if (option) {
                option.classList.add('selected');
                const trigger = select.querySelector('.custom-select-trigger span');
                if (trigger) trigger.textContent = option.textContent.trim();
            }
        })
        .fail(async (_xhr, textStatus) => {
            if (textStatus === 'abort') return;

            if (typeof OfflineDatabase !== 'undefined') {
                try {
                    const rows = await OfflineDatabase.getIndexRows('finalprojects');
                    const list = (Array.isArray(rows) ? rows : []).slice(0, 40).map(row => ({
                        id: row[1],
                        nim: row[2],
                        student_name: row[3],
                    }));
                    if (list.length) {
                        setCustomSelectOptions(select, list.map(remoteSelectOption));
                        return;
                    }
                } catch (_) { /* lanjut ke pesan error */ }
            }

            if (typeof showError === 'function') {
                showError('Gagal memuat pilihan.', 'Tidak dapat memuat daftar tugas akhir. Periksa koneksi, lalu coba lagi.');
            }
        })
        .always(function () {
            if (remoteSelectXhr.get(select) === request) {
                select.classList.remove('is-loading');
            }
        });

    remoteSelectXhr.set(select, request);

    return request;
};

const ensureRemoteSelectOption = (select, value) => {
    if (!select || value == null || value === '') return;

    const existing = select.querySelector(customOptionSelector(value));
    if (existing) {
        existing.classList.add('selected');
        const trigger = select.querySelector('.custom-select-trigger span');
        if (trigger) trigger.textContent = existing.textContent.trim();
        return;
    }

    fetchRemoteSelectOptions(select, '', [value]);
};

const applyCustomSelectValue = (form, inputId, value, placeholder) => {
    const hiddenInput = $(form).find(`#${inputId}`);
    const selectEl = hiddenInput.closest('.custom-select').get(0);
    if (!selectEl) return;

    const $select = $(selectEl);
    $select.find('.custom-option').removeClass('selected');
    hiddenInput.val(value ?? '');

    const option = $select.find(customOptionSelector(value));
    if (option.length) {
        option.addClass('selected');
        $select.find('.custom-select-trigger span').text(option.text().trim());
        return;
    }

    $select.find('.custom-select-trigger span').text(placeholder);

    if (isRemoteSelect(selectEl) && value) {
        ensureRemoteSelectOption(selectEl, value);
    }
};

/**
 * Normalisasi teks untuk pencarian (huruf kecil, tanpa diakritik).
 */
const normalizeSearchText = value => String(value || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim();

/**
 * Cocokkan query dengan teks opsi: setiap kata harus ada di opsi.
 */
const matchesOptionSearch = (optionText, query) => {
    const haystack = normalizeSearchText(optionText);
    const words = normalizeSearchText(query).split(/\s+/).filter(Boolean);

    if (!words.length) return true;

    return words.every(word => haystack.includes(word));
};

/**
 * Sisipkan kotak cari + bungkus opsi (aman dipanggil berulang / setelah refresh).
 */
const ensureCustomSelectSearch = select => {
    if (!select) return;

    const dropdown = select.querySelector('.custom-select-dropdown');
    if (!dropdown) return;

    let optionsWrap = dropdown.querySelector('.custom-select-options');
    let searchWrap = dropdown.querySelector('.custom-select-search');
    let emptyState = dropdown.querySelector('.custom-select-empty:not([data-static-empty])');

    if (!optionsWrap) {
        optionsWrap = document.createElement('div');
        optionsWrap.className = 'custom-select-options';

        [...dropdown.children].forEach(child => {
            if (child.classList?.contains('custom-option')) {
                optionsWrap.appendChild(child);
            }
        });

        dropdown.appendChild(optionsWrap);
    } else {
        // Opsi yang baru di-inject di luar wrap (setelah refresh lama) digabung kembali
        [...dropdown.querySelectorAll(':scope > .custom-option')].forEach(option => {
            optionsWrap.appendChild(option);
        });
    }

    if (!searchWrap) {
        searchWrap = document.createElement('div');
        searchWrap.className = 'custom-select-search';
        searchWrap.append(faIcon('fa-magnifying-glass'));
        const searchInput = document.createElement('input');
        searchInput.type = 'search';
        searchInput.className = 'custom-select-search-input';
        searchInput.placeholder = 'Cari opsi...';
        searchInput.autocomplete = 'off';
        searchInput.spellcheck = false;
        searchInput.setAttribute('aria-label', 'Cari opsi');
        searchInput.setAttribute('form', '__unbound_custom_select__');
        searchWrap.append(searchInput);
        dropdown.insertBefore(searchWrap, optionsWrap);
    }

    if (!emptyState) {
        emptyState = document.createElement('div');
        emptyState.className = 'custom-select-empty';
        emptyState.hidden = true;
        emptyState.textContent = 'Tidak ada opsi yang cocok';
        dropdown.appendChild(emptyState);
    }
};

/**
 * Filter opsi custom select berdasarkan kata kunci.
 */
const filterCustomSelectOptions = (select, query = '') => {
    if (!select) return;

    ensureCustomSelectSearch(select);

    const options = select.querySelectorAll('.custom-select-options .custom-option');
    const emptyState = select.querySelector('.custom-select-empty:not([data-static-empty])');
    const staticEmpty = select.querySelector('[data-static-empty]');
    let visibleCount = 0;

    options.forEach(option => {
        const matched = matchesOptionSearch(option.textContent, query);
        option.classList.toggle('is-filtered-out', !matched);
        if (matched) visibleCount += 1;
    });

    if (staticEmpty) {
        staticEmpty.hidden = options.length > 0;
    }

    if (emptyState) {
        emptyState.hidden = visibleCount > 0 || options.length === 0;
    }
};

/**
 * Reset filter pencarian custom select.
 */
const resetCustomSelectFilter = select => {
    if (!select) return;

    const searchInput = select.querySelector('.custom-select-search-input');
    if (searchInput) searchInput.value = '';

    if (!isRemoteSelect(select)) {
        filterCustomSelectOptions(select, '');
    }
};

/**
 * Ganti isi opsi custom select sambil mempertahankan kotak pencarian.
 */
const setCustomSelectOptions = (select, options) => {
    if (!select) return;

    ensureCustomSelectSearch(select);

    const optionsWrap = select.querySelector('.custom-select-options');
    if (!optionsWrap) return;

    const nodes = Array.isArray(options) ? options.filter(Boolean) : [];
    optionsWrap.replaceChildren(...nodes);
    resetCustomSelectFilter(select);
};

/**
 * Terapkan opsi Program Studi ke custom select terkait.
 * Dipakai online (API) maupun offline (salinan IndexedDB).
 */
const applyStudyProgramOptions = studyPrograms => {
    ['#studyprogramme', '#importstudyprogramme', '#visitorprogramstudy', '#submissionstudyprogramme'].forEach(inputId => {
        const hiddenInput = document.querySelector(inputId);
        if (!hiddenInput) return;

        const select = hiddenInput.closest('.custom-select');
        const currentValue = hiddenInput.value;

        setCustomSelectOptions(select, studyPrograms.map(studyProgram => customOption({
            id: studyProgram.id,
            value: studyProgram.study_program,
            label: studyProgram.study_program,
            selected: studyProgram.study_program === currentValue
        })));

        if (!studyPrograms.some(studyProgram => studyProgram.study_program === currentValue)) {
            hiddenInput.value = '';
            select.querySelector('.custom-select-trigger span').textContent = 'Pilih Program Studi';
        }
    });

    const membershipInput = document.querySelector('#membershipstudyprogramme');
    if (membershipInput) {
        const select = membershipInput.closest('.custom-select');
        const currentValue = String(membershipInput.value || '');

        setCustomSelectOptions(select, studyPrograms.map(studyProgram => customOption({
            id: studyProgram.id,
            value: studyProgram.id,
            label: studyProgram.study_program,
            selected: String(studyProgram.id) === currentValue
        })));

        if (!studyPrograms.some(studyProgram => String(studyProgram.id) === currentValue)) {
            membershipInput.value = '';
            select.querySelector('.custom-select-trigger span').textContent = 'Pilih Program Studi';
        }
    }
};

/**
 * Muat ulang opsi Program Studi pada custom select Tesis, Impor & Pengunjung.
 * Offline: fallback ke salinan IndexedDB (index studyprograms).
 */
const refreshStudyProgramOptions = () => {
    $.getJSON('/studyprogram/options')
        .done(applyStudyProgramOptions)
        .fail(async () => {
            if (typeof OfflineDatabase === 'undefined') return;

            const rows = await OfflineDatabase.getIndexRows('studyprograms');
            applyStudyProgramOptions(rows.map(row => ({
                id: row[1],
                study_program: row[2],
                cluster: row[3]
            })));
        });
};

const degreeLevelInputs = ['#submissiondegreelevel', '#importdegreelevel'];

const degreeOptionLabel = type => {
    const level = String(type?.level ?? '').trim();
    const name = String(type?.type ?? '').trim();

    if (!level) return name;

    return name ? `${level} — ${name}` : level;
};

const applyDegreeSelectValue = (form, inputId, level, typeId, placeholder = 'Pilih Jenjang') => {
    const hiddenInput = $(form).find(`#${inputId}`);
    const selectEl = hiddenInput.closest('.custom-select').get(0);
    if (!selectEl) return;

    const $select = $(selectEl);
    const typeInput = $select.find('input[data-sync="type-id"]');
    $select.find('.custom-option').removeClass('selected');

    let option = $();
    if (typeId !== null && typeId !== undefined && String(typeId) !== '') {
        const escapedId = (typeof CSS !== 'undefined' && CSS.escape)
            ? CSS.escape(String(typeId))
            : String(typeId).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        option = $select.find(`.custom-option[data-id="${escapedId}"]`);
    }

    if (!option.length && level) {
        option = $select.find(customOptionSelector(level)).first();
    }

    if (option.length) {
        option.addClass('selected');
        $select.find('.custom-select-trigger span').text(option.attr('data-label') || option.text().trim());
        hiddenInput.val(option.attr('data-value') ?? '');
        typeInput.val(option.attr('data-id') ?? '');
        return;
    }

    $select.find('.custom-select-trigger span').text(level || placeholder);
    hiddenInput.val('');
    typeInput.val('');
};

/**
 * Terapkan opsi jenjang (baris final_project_types) ke setiap dropdown jenjang.
 */
const applyDegreeLevelOptions = types => {
    const list = (Array.isArray(types) ? types : [])
        .filter(type => String(type?.level ?? '').trim() !== '');

    degreeLevelInputs.forEach(inputId => {
        const hiddenInput = document.querySelector(inputId);
        if (!hiddenInput) return;

        const select = hiddenInput.closest('.custom-select');
        if (!select) return;

        const typeInput = select.querySelector('input[data-sync="type-id"]');
        select.querySelectorAll('[data-static-empty]').forEach(node => node.remove());
        const currentId = String(typeInput?.value || '');
        const currentLevel = String(hiddenInput.value || '');
        let matchedOnce = false;

        const options = list.map(type => {
            const level = String(type.level);
            const id = String(type.id ?? '');
            const label = degreeOptionLabel(type);
            const selected = !matchedOnce && (
                currentId !== '' ? id === currentId : level === currentLevel
            );
            if (selected) matchedOnce = true;

            return customOption({ id, value: level, label, selected, dataLabel: label });
        });

        const empty = document.createElement('div');
        empty.className = 'custom-select-empty';
        empty.dataset.staticEmpty = 'true';
        empty.textContent = 'Belum ada data jenjang';
        setCustomSelectOptions(select, options.length ? options : [empty]);

        const matched = select.querySelector('.custom-option.selected');
        const trigger = select.querySelector('.custom-select-trigger span');

        if (matched) {
            if (trigger) trigger.textContent = matched.dataset.label || matched.textContent.trim();
            hiddenInput.value = matched.dataset.value || '';
            if (typeInput) typeInput.value = matched.dataset.id || '';
            return;
        }

        hiddenInput.value = '';
        if (typeInput) typeInput.value = '';
        if (trigger) trigger.textContent = 'Pilih Jenjang';
    });
};

/**
 * Muat ulang opsi jenjang pada formulir Penyerahan dan Impor.
 * Offline: fallback ke salinan IndexedDB (index finalprojecttypes).
 */
const refreshDegreeLevelOptions = () => {
    $.getJSON('/finalprojecttype/options')
        .done(applyDegreeLevelOptions)
        .fail(async () => {
            if (typeof OfflineDatabase === 'undefined') return;

            const rows = await OfflineDatabase.getIndexRows('finalprojecttypes');
            applyDegreeLevelOptions(rows.map(row => ({
                id: row[1],
                level: row[2],
                type: row[3]
            })));
        });
};

$(function () {
    if (document.querySelector('#submissiondegreelevel, #importdegreelevel')) {
        refreshDegreeLevelOptions();
    }
});
