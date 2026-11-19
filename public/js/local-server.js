/**
 * Local server install button.
 * Status is updated on this computer; the user does not type the command.
 */
(() => {
    document.querySelectorAll('.capacity-fill[data-width]').forEach((bar) => {
        const width = Number(bar.dataset.width);
        if (!Number.isFinite(width)) return;
        bar.style.setProperty('--bar', `${Math.max(0, Math.min(100, width))}%`);
    });

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let pendingLocalConfirm = null;
    let confirmLabelToken = 0;

    const confirmSubmitButton = () => document.querySelector('#alertsmodal .btn-submit-confirm');

    const setConfirmLabel = label => {
        confirmLabelToken += 1;
        const button = confirmSubmitButton();
        if (!button) return;
        const icon = button.querySelector('.trash-anim');
        if (icon) icon.style.display = 'none';
        let text = button.querySelector('.confirm-label');
        if (!text) {
            [...button.childNodes].forEach(node => {
                if (node.nodeType === Node.TEXT_NODE) node.textContent = '';
            });
            text = document.createElement('span');
            text.className = 'confirm-label';
            button.append(text);
        }
        text.textContent = label;
    };

    const resetConfirmLabel = () => {
        const button = confirmSubmitButton();
        if (!button) return;
        const icon = button.querySelector('.trash-anim');
        if (icon) icon.style.removeProperty('display');
        button.querySelector('.confirm-label')?.remove();
        const hasLabel = [...button.childNodes].some(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '');
        if (!hasLabel) button.append(document.createTextNode('Hapus'));
    };

    const resetConfirmLabelSoon = () => {
        const token = ++confirmLabelToken;
        window.setTimeout(() => {
            if (token === confirmLabelToken) resetConfirmLabel();
        }, 280);
    };

    const clearLocalConfirm = () => {
        pendingLocalConfirm = null;
        if (typeof alertModal !== 'undefined' && alertModal?.dataset.source === 'localserver') {
            delete alertModal.dataset.source;
        }
        resetConfirmLabelSoon();
    };

    const askLocalConfirm = (title, message, label, action) => {
        if (typeof showAlert !== 'function' || typeof alertModal === 'undefined' || !alertModal) {
            if (window.confirm(`${title}\n\n${message}`)) action();
            return;
        }
        pendingLocalConfirm = action;
        alertModal.dataset.source = 'localserver';
        setConfirmLabel(label);
        showAlert(title, message);
    };

    document.addEventListener('click', event => {
        const installWindow = event.target.closest?.('[data-pwa-install]');
        if (installWindow && document.getElementById('localserver')?.contains(installWindow)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            askLocalConfirm(
                'Apakah Anda yakin memasang jendela aplikasi?',
                'Aplikasi dipasang sebagai jendela sendiri di perangkat ini. Bilah alamat peramban tidak ditampilkan.',
                'Pasang',
                () => window.PwaRegister?.promptInstall()
            );
            return;
        }

        if (typeof alertModal === 'undefined' || alertModal?.dataset.source !== 'localserver') return;

        const submit = event.target.closest?.('#alertsmodal .btn-submit-confirm');
        if (submit) {
            event.preventDefault();
            event.stopPropagation();
            const action = pendingLocalConfirm;
            if (typeof closeAlert === 'function') closeAlert();
            clearLocalConfirm();
            action?.();
            return;
        }

        const dismiss = event.target.closest?.('#alertsmodal .confirm-close, #alertsmodal .btn-cancel') || event.target === alertModal;
        if (dismiss) clearLocalConfirm();
    }, true);

    const exchange = document.getElementById('localdatabaseexchange');

    if (exchange) {
        const fileInput = document.getElementById('localdatabasefile');
        const importButton = document.getElementById('localdatabaseimport');
        const exportLink = document.getElementById('localdatabaseexport');

        const uploadDatabase = async file => {
            const body = new FormData();
            body.set('database', file);
            if (importButton) importButton.disabled = true;

            try {
                const response = await fetch(exchange.dataset.importUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body,
                });
                const data = await response.json().catch(() => ({}));
                const text = data.message || (response.ok ? 'Basis data diimpor.' : 'Impor gagal.');
                if (!response.ok) {
                    if (importButton) importButton.disabled = false;
                    if (typeof showError === 'function') showError('Impor gagal.', text);
                    return;
                }
                if (typeof showSuccess === 'function') showSuccess('Basis data diimpor.', text);
                window.setTimeout(() => window.location.reload(), 700);
            } catch {
                if (importButton) importButton.disabled = false;
                if (typeof showError === 'function') showError('Impor gagal.', 'Komputer ini tidak menjawab.');
            }
        };

        importButton?.addEventListener('click', () => fileInput?.click());
        fileInput?.addEventListener('change', () => {
            const file = fileInput.files && fileInput.files[0];
            fileInput.value = '';
            if (!file || !exchange.dataset.importUrl) return;
            askLocalConfirm(
                'Apakah Anda yakin mengimpor basis data?',
                'Impor mengganti basis data aplikasi ini. Berkas lama disimpan sebagai cadangan di folder basis data. Folder berkas ARSIP TUGAS AKHIR tidak ikut terganti.',
                'Impor',
                () => uploadDatabase(file)
            );
        });

        exportLink?.addEventListener('click', event => {
            event.preventDefault();
            const href = exportLink.href;
            askLocalConfirm(
                'Apakah Anda yakin mengekspor basis data?',
                'Berkas basis data yang sedang dipakai akan diunduh. Berkas ini memuat akun, tugas akhir, pengaturan, dan kunci tautan. Simpan di tempat yang aman.',
                'Ekspor',
                () => { window.location.href = href; }
            );
        });
    }

    const root = document.getElementById('localserver');
    if (!root) return;
    const messageBox = document.getElementById('localservermessage');
    const messageText = document.getElementById('localservermessagetext');
    const phaseNode = document.getElementById('localserverphase');
    const buttons = [...root.querySelectorAll('[data-local-action]')];
    const working = new Set(['starting', 'php', 'tunnel-binary', 'database', 'shortcuts', 'server', 'sharing', 'removing']);
    let pollTimer = 0;
    let pollStarted = 0;

    const setMessage = text => {
        if (!messageBox || !messageText) return;
        const value = (text || '').trim();
        messageText.textContent = value;
        messageBox.hidden = value === '';
    };

    const setBusy = busy => {
        buttons.forEach(button => {
            button.disabled = busy;
        });
    };

    const fillCode = (id, value, asList = false) => {
        const node = document.getElementById(id);
        if (!node) return;
        const empty = node.dataset.empty || '';
        const list = asList ? (Array.isArray(value) ? value : []) : [];
        const text = asList ? (list.length ? list.join('\n') : empty) : (value || empty);
        node.textContent = text;
    };

    const applyStatus = status => {
        if (!status || typeof status !== 'object') return;
        if (phaseNode && status.phase_label) {
            phaseNode.textContent = status.phase_label;
        }
        setMessage(status.message || '');
        fillCode('localserverlocal', status.local_url || '');
        fillCode('localserverpublic', status.public_url || '');
        const file = document.getElementById('localserverfile');
        if (file && status.database_file) {
            file.textContent = status.database_file;
        }
        const busy = working.has(status.phase);
        setBusy(busy);
        if (busy && !pollTimer) {
            startPoll();
        }
        if (!busy) {
            stopPoll();
        }
    };

    async function refreshStatus() {
        if (pollStarted && Date.now() - pollStarted > 15 * 60 * 1000) {
            stopPoll();
            setBusy(false);
            setMessage('Pemasangan masih berjalan di komputer ini. Muat ulang halaman untuk melihat status terbaru.');
            return;
        }

        try {
            const response = await fetch(root.dataset.statusUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) return;
            applyStatus(await response.json());
        } catch {
            /* Keep the last status. */
        }
    }

    function startPoll() {
        if (pollTimer) return;
        pollStarted = Date.now();
        pollTimer = window.setInterval(refreshStatus, 2000);
    }

    function stopPoll() {
        if (!pollTimer) return;
        window.clearInterval(pollTimer);
        pollTimer = 0;
        pollStarted = 0;
    }

    const selectedMode = name => root.querySelector(`input[name="${name}"]:checked`)?.value || 'keep';

    const syncPlacement = () => {
        const databaseMode = selectedMode('database_mode');
        const archiveMode = selectedMode('archive_mode');
        const databaseInput = document.getElementById('databasefilepath');
        const databaseButton = document.getElementById('pickdatabasefile');
        const archiveInput = document.getElementById('archivefilepath');
        const archiveButton = document.getElementById('pickarchivefolder');
        const copy = document.getElementById('copydatabase');
        const databaseOn = databaseMode === 'existing';
        const archiveOn = archiveMode === 'new' || archiveMode === 'existing';
        if (databaseInput) databaseInput.disabled = !databaseOn;
        if (databaseButton) databaseButton.disabled = !databaseOn;
        if (archiveInput) archiveInput.disabled = !archiveOn;
        if (archiveButton) archiveButton.disabled = !archiveOn;
        if (copy) {
            copy.disabled = databaseMode === 'existing';
            if (databaseMode === 'existing') copy.checked = false;
        }
    };

    const installNotice = () => {
        const lines = [
            'PHP, basis data, dan tautan internet diunduh ke folder aplikasi, lalu pintasan dibuat. Biarkan halaman ini terbuka sampai status berubah.'
        ];
        const databaseMode = selectedMode('database_mode');
        if (databaseMode === 'new' && root.dataset.databaseReady === '1') {
            lines.push('Basis data yang ada disimpan sebagai cadangan, lalu berkas baru dibuat.');
        }
        if (databaseMode === 'existing') {
            lines.push('Berkas basis data yang dipilih disalin ke folder aplikasi ini.');
        }
        return lines.join(' ');
    };

    async function runAction(url) {
        setBusy(true);
        const body = new FormData();
        const copy = document.getElementById('copydatabase');
        const adopt = document.getElementById('adoptdatabase');
        const databaseMode = selectedMode('database_mode');
        if (copy) body.set('copy_database', copy.checked ? '1' : '0');
        if (adopt) body.set('adopt_database', adopt.checked ? '1' : '0');
        body.set('database_mode', databaseMode);
        body.set('archive_mode', selectedMode('archive_mode'));
        body.set('database_file', document.getElementById('databasefilepath')?.value.trim() || '');
        body.set('archive_path', document.getElementById('archivefilepath')?.value.trim() || '');

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body,
            });
            const data = await response.json().catch(() => ({}));
            if (data.status) {
                applyStatus(data.status);
            }
            if (data.message) {
                setMessage(data.message);
            }
            if (!response.ok && !data.status) {
                setBusy(false);
            } else if (working.has(data.status?.phase)) {
                startPoll();
            }
        } catch {
            setBusy(false);
            setMessage('Komputer ini tidak menjawab. Muat ulang halaman, atau jalankan Pasang-ke-Perangkat.cmd.');
        }
    }

    const actionConfirm = {
        localserverinstall: () => ({
            title: 'Apakah Anda yakin memasang semua komponen?',
            message: installNotice(),
            label: 'Pasang',
        }),
        localserverstart: () => ({
            title: 'Apakah Anda yakin menjalankan peladen?',
            message: 'Peladen di komputer ini akan dinyalakan. Komputer harus tetap menyala agar aplikasi dapat dibuka.',
            label: 'Jalankan',
        }),
        localservershare: () => ({
            title: 'Apakah Anda yakin membagikan tautan?',
            message: 'Tautan internet untuk klien dibuat saat komputer daring. Klien yang menerima tautan dapat masuk selama peladen menyala.',
            label: 'Bagikan',
        }),
        localserverunshare: () => ({
            title: 'Apakah Anda yakin menghentikan tautan?',
            message: 'Tautan untuk klien diputus. Akses dari internet berhenti. Peladen di komputer ini tetap menyala.',
            label: 'Hentikan',
        }),
        localserverstop: () => ({
            title: 'Apakah Anda yakin menghentikan peladen?',
            message: 'Peladen di komputer ini dimatikan. Aplikasi tidak dapat dibuka sampai peladen dijalankan lagi.',
            label: 'Hentikan',
        }),
        localserveruninstall: () => ({
            title: 'Apakah Anda yakin mencopot pemasangan?',
            message: 'ARSIP-TA dihapus secara permanen dari komputer ini. Isi berkas aplikasi ditimpa, lalu folder aplikasi, basis data di dalamnya, registri, catatan, pintasan, dan sisa berkas dihapus. Folder arsip di luar folder aplikasi tidak dihapus. Tindakan ini tidak dapat dibatalkan.',
            label: 'Copot',
        }),
    };

    root.addEventListener('click', event => {
        const databaseLink = event.target.closest('#localserverdatabase');
        if (databaseLink) {
            event.preventDefault();
            const href = databaseLink.href;
            askLocalConfirm(
                'Apakah Anda yakin membuka panel basis data?',
                'Panel menampilkan isi tabel di komputer ini. Kata sandi disembunyikan. Klien dari tautan internet tidak dapat membuka halaman ini.',
                'Buka',
                () => { window.location.href = href; }
            );
            return;
        }

        const action = event.target.closest('[data-local-action]');
        if (action) {
            event.preventDefault();
            if (action.disabled) return;
            const copy = actionConfirm[action.id]?.();
            const url = action.dataset.localAction;
            if (!copy) {
                runAction(url);
                return;
            }
            askLocalConfirm(copy.title, copy.message, copy.label, () => runAction(url));
            return;
        }

        const copy = event.target.closest('[data-copy-target]');
        if (!copy) return;
        const node = document.querySelector(copy.dataset.copyTarget || '');
        const empty = node?.dataset.empty || '';
        const text = node?.textContent?.trim() || '';
        if (!text || text === empty) return;
        navigator.clipboard?.writeText(text).then(() => {
            setMessage('Tautan disalin.');
        }).catch(() => {
            setMessage('Salin tautan secara manual.');
        });
    });

    if (working.has(root.dataset.phase || '')) {
        setBusy(true);
        startPoll();
    }

    syncPlacement();
    root.addEventListener('change', event => {
        if (event.target.matches('input[name="database_mode"], input[name="archive_mode"]')) {
            syncPlacement();
        }
    });

    const picker = document.getElementById('localpickermodal');
    const pickerPath = document.getElementById('localpickerpath');
    const pickerList = document.getElementById('localpickerlist');
    const pickerStatus = document.getElementById('localpickerstatus');
    const pickerShortcuts = document.getElementById('localpickershortcuts');
    const pickerTitle = document.getElementById('localpickertitle');
    const pickerHint = document.getElementById('localpickerhint');
    const pickerUse = document.getElementById('localpickeruse');
    const pickerUp = document.getElementById('localpickerup');
    let pickerMode = 'folder';
    let pickerParent = null;
    let pickedFile = '';
    let targetInput = null;

    const setPickerStatus = (text, isError = false) => {
        if (!pickerStatus) return;
        pickerStatus.textContent = text;
        pickerStatus.classList.toggle('is-error', Boolean(isError));
    };

    const shortcutIcon = kind => {
        if (kind === 'database') return 'fa-database';
        if (kind === 'computer') return 'fa-computer';
        if (kind === 'default') return 'fa-box-archive';
        if (kind === 'removable') return 'fa-usb';
        if (kind === 'network') return 'fa-server';
        if (kind === 'drive') return 'fa-hard-drive';
        return 'fa-folder';
    };

    const renderShortcuts = shortcuts => {
        if (!pickerShortcuts) return;
        pickerShortcuts.replaceChildren();
        (shortcuts || []).forEach(item => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'storage-picker-shortcut';
            button.dataset.path = item.path || '';
            const label = document.createElement('span');
            label.textContent = item.label || '';
            button.append(typeof faIcon === 'function' ? faIcon(shortcutIcon(item.kind)) : document.createTextNode(''), label);
            pickerShortcuts.append(button);
        });
    };

    const renderEntries = entries => {
        if (!pickerList) return;
        pickerList.replaceChildren();
        if (!entries || !entries.length) {
            const item = document.createElement('li');
            item.className = 'storage-picker-empty';
            item.textContent = pickerMode === 'file' ? 'Tidak ada folder atau berkas basis data di lokasi ini.' : 'Tidak ada subfolder di lokasi ini.';
            pickerList.append(item);
            return;
        }
        entries.forEach(item => {
            const entry = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'storage-picker-item' + (item.path === pickedFile ? ' is-active' : '');
            button.dataset.path = item.path || '';
            button.dataset.kind = item.kind || 'folder';
            const label = document.createElement('span');
            label.textContent = item.name || '';
            button.append(typeof faIcon === 'function' ? faIcon(shortcutIcon(item.kind || 'folder')) : document.createTextNode(''), label);
            entry.append(button);
            pickerList.append(entry);
        });
    };

    const loadPicker = async path => {
        setPickerStatus('Membuka folder…');
        const url = new URL(root.dataset.browseUrl, window.location.origin);
        if (path) url.searchParams.set('path', path);
        if (pickerMode === 'file') url.searchParams.set('files', '1');
        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                setPickerStatus(data.message || 'Folder tidak dapat dibuka.', true);
                return;
            }
            const isComputer = Boolean(data.computer);
            pickerParent = data.parent || null;
            pickedFile = '';
            if (pickerPath) pickerPath.value = isComputer ? '' : (data.path || '');
            if (pickerUp) pickerUp.disabled = !pickerParent;
            renderShortcuts(data.shortcuts || []);
            renderEntries(data.entries || []);
            setPickerStatus(isComputer
                ? 'Pilih drive, lalu buka foldernya.'
                : (data.path || ''));
        } catch {
            setPickerStatus('Folder tidak dapat dibuka.', true);
        }
    };

    const openPicker = (mode, input) => {
        if (!picker) return;
        pickerMode = mode;
        targetInput = input;
        pickedFile = '';
        if (pickerTitle) pickerTitle.textContent = mode === 'file' ? 'Pilih berkas basis data' : 'Pilih folder berkas';
        if (pickerHint) {
            pickerHint.textContent = mode === 'file'
                ? 'Pilih berkas SQLite yang sudah ada, atau tempel lokasinya, lalu klik Gunakan.'
                : 'Pilih folder ARSIP TUGAS AKHIR yang sudah ada, atau folder induk untuk folder baru.';
        }
        if (pickerUse) pickerUse.textContent = mode === 'file' ? 'Gunakan berkas ini' : 'Gunakan folder ini';
        picker.classList.add('show');
        document.body.style.overflow = 'hidden';
        loadPicker('__computer__');
    };

    const closePicker = () => {
        picker?.classList.remove('show');
        document.body.style.overflow = '';
    };

    document.getElementById('pickdatabasefile')?.addEventListener('click', () => {
        openPicker('file', document.getElementById('databasefilepath'));
    });
    document.getElementById('pickarchivefolder')?.addEventListener('click', () => {
        openPicker('folder', document.getElementById('archivefilepath'));
    });
    document.getElementById('localpickerclose')?.addEventListener('click', closePicker);
    document.getElementById('localpickercancel')?.addEventListener('click', closePicker);
    document.getElementById('localpickercomputer')?.addEventListener('click', () => loadPicker('__computer__'));
    pickerUp?.addEventListener('click', () => {
        if (pickerParent) loadPicker(pickerParent);
    });
    document.getElementById('localpickergo')?.addEventListener('click', () => {
        const typed = pickerPath?.value.trim() || '';
        if (pickerMode === 'file' && /\.(sqlite3?|db)$/i.test(typed)) {
            pickedFile = typed;
            setPickerStatus(typed);
            return;
        }
        if (typed) loadPicker(typed);
    });
    pickerUse?.addEventListener('click', () => {
        const typed = pickerPath?.value.trim() || '';
        if (pickerMode === 'file') {
            const chosen = pickedFile || (/\.(sqlite3?|db)$/i.test(typed) ? typed : '');
            if (!chosen) {
                setPickerStatus('Pilih berkas basis data.', true);
                return;
            }
            if (targetInput) targetInput.value = chosen;
            closePicker();
            return;
        }
        if (!typed || typed === 'Komputer') {
            setPickerStatus('Pilih folder.', true);
            return;
        }
        if (targetInput) targetInput.value = typed;
        closePicker();
    });
    pickerList?.addEventListener('click', event => {
        const button = event.target.closest('[data-path]');
        if (!button) return;
        if (button.dataset.kind === 'database') {
            pickedFile = button.dataset.path || '';
            pickerList.querySelectorAll('.storage-picker-item').forEach(item => {
                item.classList.toggle('is-active', item === button);
            });
            setPickerStatus(pickedFile);
            return;
        }
        loadPicker(button.dataset.path || '');
    });
    pickerShortcuts?.addEventListener('click', event => {
        const button = event.target.closest('[data-path]');
        if (!button) return;
        loadPicker(button.dataset.path || '__computer__');
    });
})();
