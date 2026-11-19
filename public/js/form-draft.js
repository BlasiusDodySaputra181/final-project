/**
 * Drafts of form fields.
 * Close, cancel, and a click outside the modal do not clear the fields.
 * Page forms also persist across navigation until they are saved successfully.
 */
const FORM_DRAFT_STORAGE = 'fp-form-drafts';
const FORM_DRAFT_SKIP = new Set(['loginform', 'publicsearchform']);
const FORM_DRAFT_PAGES = [
    'visitorpublicform',
    'membershippublicform',
    'submissionpublicform',
    'profilepublicform',
    'profileform',
    'registerform',
    'settingform'
];
const FORM_DRAFT_PAGE_URLS = {
    profileform: '/storeprofile',
    settingform: '/storesetting',
    profilepublicform: '/akun',
    visitorpublicform: '/kunjungan',
    submissionpublicform: '/serah',
    membershippublicform: '/anggota'
};

const formDraftMemory = new Map();
const formDraftTimers = new WeakMap();
let formDraftSubmitted = null;

const formDraftOwner = () => document.body?.dataset?.userId
    || document.querySelector('meta[name="user-id"]')?.getAttribute('content')
    || 'guest';

const formDraftCssEscape = value => {
    const text = String(value ?? '');
    if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
        return CSS.escape(text);
    }

    return text.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
};

const formDraftRecordId = form => {
    const button = form?.querySelector('button[data-id]');
    if (!button) return 'page';

    try {
        const id = JSON.parse(button.getAttribute('data-id') || '{}')?.id;
        return id == null || id === '' ? 'new' : String(id);
    } catch {
        return 'new';
    }
};

const formDraftKey = form => `${formDraftOwner()}:${form.id}:${formDraftRecordId(form)}`;

const isKeptForm = form => Boolean(
    form?.id
    && !FORM_DRAFT_SKIP.has(form.id)
    && (form.dataset.pageDraft === '1' || form.closest('.modal-overlay'))
);

const readFormDraftStore = () => {
    try {
        const parsed = JSON.parse(sessionStorage.getItem(FORM_DRAFT_STORAGE) || '{}');
        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch {
        return {};
    }
};

const writeFormDraftStore = store => {
    try {
        sessionStorage.setItem(FORM_DRAFT_STORAGE, JSON.stringify(store));
    } catch {
        /* quota full: the draft stays in this tab's memory */
    }
};

const formDraftPicker = inputId => {
    switch (inputId) {
        case 'finalyearproject':
            return typeof finalProjectYearDatepicker === 'undefined' ? null : finalProjectYearDatepicker;
        case 'submissiondate':
            return typeof submissionDatepicker === 'undefined' ? null : submissionDatepicker;
        case 'importfinalyearproject':
            return typeof importFinalProjectYearDatepicker === 'undefined' ? null : importFinalProjectYearDatepicker;
        case 'importsubmissiondate':
            return typeof importSubmissionDatepicker === 'undefined' ? null : importSubmissionDatepicker;
        case 'bookgrantyear':
            return typeof bookGrantYearDatepicker === 'undefined' ? null : bookGrantYearDatepicker;
        case 'libraryclearancegraduationyear':
            return typeof libraryClearanceGraduationYearDatepicker === 'undefined' ? null : libraryClearanceGraduationYearDatepicker;
        case 'submissionfinalyearproject':
            return typeof submissionYearDatepicker === 'undefined' ? null : submissionYearDatepicker;
        case 'submissionsubmissiondate':
            return typeof submissionSubmissionDatepicker === 'undefined' ? null : submissionSubmissionDatepicker;
        case 'membershipbirthdate':
            return typeof membershipBirthDatepicker === 'undefined' ? null : membershipBirthDatepicker;
        default:
            return null;
    }
};

const syncFormDraftPicker = input => {
    if (!input?.id) return;

    const picker = formDraftPicker(input.id);
    if (!picker) return;

    if (typeof selectDatepickerDate === 'function') {
        selectDatepickerDate(picker, input.value);
        return;
    }

    if (!input.value) {
        picker.clear?.({ silent: true });
        return;
    }

    const date = new Date(input.value);
    if (!Number.isNaN(date.getTime())) picker.selectDate(date);
};

const captureFormDraft = form => {
    if (!isKeptForm(form) || form.dataset.draftSuppress === '1') return;

    const fields = [];
    const secrets = [];
    const files = {};

    form.querySelectorAll('input, textarea, select').forEach(field => {
        if (field.closest('template')) return;

        const type = String(field.type || '').toLowerCase();
        if (type === 'submit' || type === 'button' || type === 'reset' || type === 'image') return;
        if (field.classList.contains('custom-select-search-input')) return;
        if (field.classList.contains('is-account-locked')) return;
        if (field.name === '_token' || field.name === '_method') return;

        if (type === 'file') {
            const key = field.id || field.name;
            if (key) files[key] = [...(field.files || [])];
            return;
        }

        if (type === 'password') {
            secrets.push({
                id: field.id || '',
                name: field.name || '',
                value: field.value
            });
            return;
        }

        if (!field.name && !field.id) return;

        if (type === 'checkbox' || type === 'radio') {
            fields.push({
                id: field.id || '',
                name: field.name || '',
                value: field.value,
                checked: field.checked,
                type
            });
            return;
        }

        fields.push({
            id: field.id || '',
            name: field.name || '',
            value: field.value
        });
    });

    const selects = [...form.querySelectorAll('.custom-select')].map(select => {
        const inputs = [...select.querySelectorAll('input[type="hidden"]')];
        return {
            inputIds: inputs.map(input => input.id),
            values: inputs.map(input => input.value),
            label: select.querySelector('.custom-select-trigger span')?.textContent?.trim() || ''
        };
    }).filter(select => select.inputIds.length);

    const fileLabels = [...form.querySelectorAll('.file-name')].map(node => node.textContent.trim());

    const passwordLabel = form.querySelector('#userpasswordlabel');
    const passwordInput = form.querySelector('#userpassword');
    const ui = {};
    if (passwordLabel) {
        ui.passwordLabel = passwordLabel.textContent;
        ui.passwordRequired = passwordLabel.classList.contains('required-label');
    }
    if (passwordInput) {
        ui.passwordPlaceholder = passwordInput.getAttribute('placeholder') || '';
    }

    let step = null;
    if (form.id === 'submissionpublicform') {
        const slides = [...form.querySelectorAll('.slide')];
        const index = slides.findIndex(slide => slide.classList.contains('active-slide'));
        step = index >= 0 ? index : 0;
    }

    const key = formDraftKey(form);
    const persisted = { fields, selects, fileLabels, ui, step };
    formDraftMemory.set(key, { secrets, files });

    const store = readFormDraftStore();
    store[key] = persisted;
    writeFormDraftStore(store);
};

const scheduleFormDraft = form => {
    if (!isKeptForm(form) || form.dataset.draftSuppress === '1') return;

    const previous = formDraftTimers.get(form);
    if (previous) clearTimeout(previous);

    formDraftTimers.set(form, setTimeout(() => {
        formDraftTimers.delete(form);
        captureFormDraft(form);
    }, 180));
};

const forgetFormDraft = form => {
    if (!form?.id) return;

    const timer = formDraftTimers.get(form);
    if (timer) clearTimeout(timer);
    formDraftTimers.delete(form);

    const key = formDraftKey(form);
    formDraftMemory.delete(key);

    const store = readFormDraftStore();
    if (Object.prototype.hasOwnProperty.call(store, key)) {
        delete store[key];
        writeFormDraftStore(store);
    }
};

const formDraftNodes = (form, field) => {
    if (field.name) {
        const named = [...form.querySelectorAll(`[name="${formDraftCssEscape(field.name)}"]`)]
            .filter(node => !node.closest('template'));
        if (named.length) return named;
    }

    if (!field.id) return [];

    const byId = form.querySelector(`#${formDraftCssEscape(field.id)}`);
    return byId && !byId.closest('template') ? [byId] : [];
};

const ensureSubmissionBookRows = (form, fields) => {
    if (form.id !== 'submissionpublicform') return;

    const indexes = fields
        .map(field => /^books\[(\d+)\]/.exec(field.name || ''))
        .filter(Boolean)
        .map(match => Number(match[1]));

    if (!indexes.length) return;

    const needed = Math.max(...indexes) + 1;
    const container = form.querySelector('#booksContainer');
    const template = document.getElementById('tpl-book-entry');
    if (!container || !template?.content?.firstElementChild) return;

    while (container.querySelectorAll('.book-entry').length < needed) {
        container.appendChild(template.content.firstElementChild.cloneNode(true));
    }

    container.querySelectorAll('.book-entry').forEach((entry, index) => {
        entry.dataset.bookIndex = String(index);
        const title = entry.querySelector('.clone-title');
        if (title) title.textContent = `Formulir Buku ${index + 1}`;

        const author = entry.querySelector('input[name*="[author]"]');
        const year = entry.querySelector('input[name*="[book_year]"]');
        const bookTitle = entry.querySelector('textarea[name*="[book_title]"]');
        if (author) author.name = `books[${index}][author]`;
        if (year) year.name = `books[${index}][book_year]`;
        if (bookTitle) bookTitle.name = `books[${index}][book_title]`;
    });
};

const applyFormDraftSelects = (form, selects) => {
    (selects || []).forEach(state => {
        const values = (state.values || []).map(value => String(value ?? ''));
        const wanted = new Set(values.filter(value => value !== ''));
        let select = null;

        state.inputIds.forEach((id, index) => {
            if (!id) return;

            const input = form.querySelector(`#${formDraftCssEscape(id)}`);
            if (!input) return;

            input.value = values[index] ?? '';
            select = select || input.closest('.custom-select');
        });

        if (!select) return;

        select.querySelectorAll('.custom-option').forEach(option => {
            const value = String(option.dataset.value ?? '');
            const id = String(option.dataset.id ?? '');
            option.classList.toggle('selected', wanted.has(value) || wanted.has(id));
        });

        const label = select.querySelector('.custom-select-trigger span');
        if (label) {
            if (state.label) label.textContent = state.label;
            label.classList.toggle('contain', wanted.size > 0);
        }

        const remoteValue = values.find(value => value !== '');
        if (remoteValue && typeof isRemoteSelect === 'function' && isRemoteSelect(select) && typeof ensureRemoteSelectOption === 'function') {
            ensureRemoteSelectOption(select, remoteValue);
        }
    });
};

const applyFormDraftFiles = (form, files, fileLabels) => {
    form.querySelectorAll('input[type="file"]').forEach(input => {
        const key = input.id || input.name;
        const list = key ? files?.[key] : null;
        delete input.dataset.draftFileRestored;

        if (!list?.length) {
            if (input.files?.length) input.value = '';
            return;
        }

        const same = input.files?.length === list.length
            && [...input.files].every((file, index) => file === list[index]);

        if (same) {
            input.dataset.draftFileRestored = '1';
            return;
        }

        try {
            const transfer = new DataTransfer();
            list.forEach(file => transfer.items.add(file));
            input.files = transfer.files;
            input.dataset.draftFileRestored = '1';
            input.dispatchEvent(new Event('change', { bubbles: true }));
        } catch {
            /* a file can be restored only in the same tab */
        }
    });

    const labels = form.querySelectorAll('.file-name');
    (fileLabels || []).forEach((text, index) => {
        const label = labels[index];
        if (!label || text === undefined) return;

        const input = label.closest('.upload-box, .upload-area')?.querySelector('input[type="file"]');
        if (input?.dataset.draftFileRestored === '1') return;
        label.textContent = text;
    });
};

const restoreFormDraft = form => {
    if (!isKeptForm(form)) return false;

    const key = formDraftKey(form);
    const stored = readFormDraftStore()[key];
    const memory = formDraftMemory.get(key);
    if (!stored && !memory) return false;

    const draft = stored || { fields: [], selects: [], fileLabels: [], ui: {}, step: null };

    try {
        ensureSubmissionBookRows(form, draft.fields || []);

        if (form.id === 'submissionpublicform' && Number.isInteger(draft.step)) {
            form.dataset.initialStep = String(draft.step);
        }

        (draft.fields || []).forEach(field => {
            formDraftNodes(form, field).forEach(node => {
                if (node.classList.contains('is-account-locked')) return;
                if (field.type === 'checkbox' || field.type === 'radio') {
                    if (node.value === field.value) node.checked = Boolean(field.checked);
                    return;
                }

                if (node.type === 'file' || node.type === 'password') return;
                node.value = field.value ?? '';
            });
        });

        (memory?.secrets || []).forEach(field => {
            formDraftNodes(form, field).forEach(node => {
                if (node.type === 'password') node.value = field.value ?? '';
            });
        });

        applyFormDraftSelects(form, draft.selects);
        form.querySelectorAll('input').forEach(syncFormDraftPicker);
        applyFormDraftFiles(form, memory?.files, draft.fileLabels);

        if (draft.ui?.passwordLabel) {
            const label = form.querySelector('#userpasswordlabel');
            if (label) {
                label.textContent = draft.ui.passwordLabel;
                label.classList.toggle('required-label', Boolean(draft.ui.passwordRequired));
            }
        }

        if (draft.ui && Object.prototype.hasOwnProperty.call(draft.ui, 'passwordPlaceholder')) {
            form.querySelector('#userpassword')?.setAttribute('placeholder', draft.ui.passwordPlaceholder || '');
        }
    } catch (error) {
        console.error(error);
        return false;
    }

    return true;
};

const keepBlankDraft = form => {
    const button = form?.querySelector('button[data-id]');
    if (button) button.setAttribute('data-id', JSON.stringify({ id: null }));
    return restoreFormDraft(form);
};

const keepRecordDraft = (form, recordId) => {
    const button = form?.querySelector('button[data-id]');
    if (button) button.setAttribute('data-id', JSON.stringify({ id: recordId ?? null }));
    return restoreFormDraft(form);
};

const rememberOpenFormDrafts = () => {
    document.querySelectorAll('.modal-overlay.show form[id]').forEach(form => {
        delete form.dataset.keepDraftUntilSave;
        captureFormDraft(form);
    });
};

const suppressFormDraft = form => {
    const timer = formDraftTimers.get(form);
    if (timer) clearTimeout(timer);
    formDraftTimers.delete(form);
    form.dataset.draftSuppress = '1';
    forgetFormDraft(form);
    setTimeout(() => delete form.dataset.draftSuppress, 400);
};

const requestPath = url => {
    try {
        return new URL(String(url || ''), location.origin).pathname;
    } catch {
        return '';
    }
};

const isModalStoreRequest = url => /^\/store[a-z0-9]*/i.test(requestPath(url));

const forgetPageDraftForUrl = url => {
    const path = requestPath(url);
    if (!path) return;

    document.querySelectorAll('form[data-page-draft="1"]').forEach(form => {
        let actionPath = FORM_DRAFT_PAGE_URLS[form.id] || '';
        if (!actionPath && form.getAttribute('action')) {
            actionPath = requestPath(form.getAttribute('action'));
        }

        if (actionPath && (path === actionPath || path.endsWith(actionPath))) {
            forgetFormDraft(form);
        }
    });
};

const bindFormDraftAjax = () => {
    if (!window.jQuery) return;

    window.jQuery(document).ajaxSuccess((_event, _xhr, settings) => {
        if (String(settings?.type || 'GET').toUpperCase() !== 'POST') return;

        const url = settings?.url || '';
        if (formDraftSubmitted?.dataset.keepDraftUntilSave === '1' && isModalStoreRequest(url)) {
            forgetFormDraft(formDraftSubmitted);
            delete formDraftSubmitted.dataset.keepDraftUntilSave;
            formDraftSubmitted = null;
        }

        forgetPageDraftForUrl(url);
    });

    window.jQuery(document).ajaxError((_event, _xhr, settings) => {
        if (!formDraftSubmitted || !isModalStoreRequest(settings?.url || '')) return;

        delete formDraftSubmitted.dataset.keepDraftUntilSave;
        formDraftSubmitted = null;
    });
};

const markPageDraftForms = () => {
    FORM_DRAFT_PAGES.forEach(id => {
        const form = document.getElementById(id);
        if (form) form.dataset.pageDraft = '1';
    });
};

const restoreStandaloneDrafts = () => {
    document.querySelectorAll('form[data-page-draft="1"]').forEach(form => {
        restoreFormDraft(form);
    });
};

markPageDraftForms();
restoreStandaloneDrafts();
bindFormDraftAjax();

document.addEventListener('input', event => {
    if (!event.isTrusted) return;
    scheduleFormDraft(event.target?.closest?.('form'));
}, true);

document.addEventListener('change', event => {
    if (!event.isTrusted) return;
    scheduleFormDraft(event.target?.closest?.('form'));
}, true);

document.addEventListener('click', event => {
    if (!event.isTrusted) return;
    if (!event.target?.closest?.('.air-datepicker-cell, .custom-option, .next-btn, .prev-btn, .step, #addBookBtn, .remove-book-btn')) return;

    const form = event.target.closest('form')
        || event.target.closest('.modal-overlay')?.querySelector('form[id]')
        || document.querySelector('.modal-overlay.show form[id]')
        || document.querySelector('form[data-page-draft="1"]');

    setTimeout(() => captureFormDraft(form), 0);
}, true);

document.addEventListener('reset', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !isKeptForm(form)) return;
    suppressFormDraft(form);
}, true);

document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;

    if (form.closest('.modal-overlay') && isKeptForm(form)) {
        formDraftSubmitted = form;
        form.dataset.keepDraftUntilSave = '1';
        return;
    }

    if (form.dataset.pageDraft !== '1') return;

    setTimeout(() => {
        if (!event.defaultPrevented) forgetFormDraft(form);
    }, 0);
}, true);
