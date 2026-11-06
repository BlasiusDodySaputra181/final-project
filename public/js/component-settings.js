/**
 * Pengaturan global lokasi penyimpanan berkas tugas akhir
 * serta akses mahasiswa pengunjung, serah, dan barcode.
 */
(function () {
    const form = document.getElementById('settingform');
    if (!form || typeof $ === 'undefined') return;

    const canEdit = form.dataset.canEdit === '1';
    const pathInput = document.getElementById('settingstoragepath');
    const resolvedEl = document.getElementById('settingresolvedpath');
    const pickButton = document.getElementById('buttonpickstorage');
    const resetButton = document.getElementById('buttonresetstorage');
    const pickerModal = document.getElementById('storagepickermodal');
    const pickerPath = document.getElementById('storagepickerpath');
    const pickerList = document.getElementById('storagepickerlist');
    const pickerStatus = document.getElementById('storagepickerstatus');
    const pickerShortcuts = document.getElementById('storagepickershortcuts');
    const pickerUp = document.getElementById('storagepickerup');
    const pickerComputer = document.getElementById('storagepickercomputer');
    const pickerUse = document.getElementById('storagepickeruse');
    const defaultPath = pathInput?.getAttribute('placeholder') || '';
    const COMPUTER_ROOT = '__computer__';

    let currentPickerPath = '';
    let currentPickerParent = null;
    let currentWritable = false;

    const applySavedState = payload => {
        const usingDefault = Boolean(payload?.using_default);
        const resolved = payload?.resolved_path || defaultPath;
        if (pathInput) {
            pathInput.value = usingDefault ? '' : (payload?.storage_path || '');
        }
        if (resolvedEl) {
            resolvedEl.textContent = resolved;
        }
    };

    let saving = false;
    const saveButton = document.getElementById('buttonsetting');

    const setSaving = busy => {
        saving = Boolean(busy);
        form.classList.toggle('is-saving', saving);
        [saveButton, resetButton, pickButton].forEach(button => {
            if (button) button.disabled = saving;
        });
        if (pathInput) pathInput.readOnly = saving;
    };

    const progressToken = () => {
        const raw = (typeof crypto !== 'undefined' && crypto.randomUUID)
            ? crypto.randomUUID()
            : `t${Date.now()}${Math.random().toString(16).slice(2)}`;
        return String(raw).replace(/-/g, '').slice(0, 32);
    };

    const applyMoveProgress = data => {
        if (!data || typeof setProgress !== 'function') return;

        const percent = Math.max(0, Math.min(100, Number(data.percent) || 0));
        const failed = Boolean(data.error) || data.phase === 'error';
        const current = Number(data.current) || 0;
        const total = Number(data.total) || 0;
        const meta = total > 0 ? `${current} dari ${total} item` : '';

        setProgress(
            failed ? 'Gagal memindah berkas.' : 'Memindah berkas.',
            failed
                ? (data.error || 'Pemindahan dihentikan. Periksa folder, lalu coba lagi.')
                : 'Jangan tutup halaman ini sampai proses selesai.',
            percent,
            data.label || 'Memindah data…',
            {
                status: data.status || 'Mohon tunggu sebentar, proses ini mungkin memerlukan waktu.',
                meta,
                file: data.file || ''
            }
        );
    };

    const savePath = path => {
        if (saving) return;

        const fields = {
            _token: typeof csrfToken !== 'undefined' ? csrfToken : $('meta[name="csrf-token"]').attr('content'),
            storage_path: path,
            progress_token: progressToken()
        };

        let pollTimer = null;
        const stopPoll = () => {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        };

        const finishUi = () => {
            stopPoll();
            setSaving(false);
            if (typeof unlockProgressConfirm === 'function') {
                unlockProgressConfirm();
            }
        };

        setSaving(true);
        if (typeof lockProgressConfirm === 'function') {
            lockProgressConfirm();
        }
        applyMoveProgress({
            percent: 0,
            phase: 'prepare',
            label: 'Menyiapkan pemindahan',
            status: 'Memeriksa folder sumber dan tujuan…'
        });

        const pollProgress = () => {
            $.ajax({
                url: '/setting/storage-move-progress',
                type: 'GET',
                cache: false,
                data: { token: fields.progress_token },
                success: applyMoveProgress
            });
        };

        pollTimer = setInterval(pollProgress, 220);
        pollProgress();

        $.ajax({
            url: '/storesetting',
            type: 'POST',
            data: fields,
            success: response => {
                stopPoll();
                applyMoveProgress({
                    percent: 100,
                    phase: 'done',
                    label: 'Selesai',
                    status: typeof response === 'string'
                        ? response
                        : (response?.message || 'Pemindahan data selesai.'),
                    current: Number(response?.moved) || 0,
                    total: Number(response?.moved) || 0,
                    file: ''
                });
                applySavedState(response);
                const message = typeof response === 'string'
                    ? response
                    : (response?.message || 'Pengaturan berhasil disimpan.');

                window.setTimeout(() => {
                    finishUi();
                    progressModal?.classList.remove('show');
                    if (typeof showSuccess === 'function') {
                        showSuccess('Pengaturan disimpan.', message);
                    }
                }, 420);
            },
            error: (xhr, textStatus) => {
                const payload = xhr.responseJSON;
                applyMoveProgress({
                    percent: Number(payload?.percent) || 100,
                    phase: 'error',
                    label: 'Gagal memindah berkas',
                    error: firstValidationMessage?.(payload)
                        || payload?.message
                        || 'Periksa folder, lalu coba lagi.',
                    status: firstValidationMessage?.(payload)
                        || payload?.message
                        || 'Periksa folder, lalu coba lagi.',
                    file: ''
                });
                finishUi();
                progressModal?.classList.remove('show');
                if (typeof handleCrudRequestError === 'function') {
                    handleCrudRequestError(xhr, {
                        failTitle: 'Gagal menyimpan pengaturan.',
                        textStatus
                    });
                    return;
                }
                if (typeof showError === 'function') {
                    showError('Gagal menyimpan pengaturan.', 'Periksa folder, lalu coba lagi.');
                }
            }
        });
    };

    const setPickerStatus = (text, isError = false) => {
        if (!pickerStatus) return;
        pickerStatus.textContent = text;
        pickerStatus.classList.toggle('is-error', Boolean(isError));
    };

    const shortcutIcon = kind => {
        if (kind === 'computer') return 'fa-computer';
        if (kind === 'default') return 'fa-box-archive';
        if (kind === 'removable') return 'fa-usb';
        if (kind === 'network') return 'fa-server';
        if (kind === 'drive') return 'fa-hard-drive';
        if (kind === 'public') return 'fa-folder-open';
        return 'fa-folder';
    };

    const renderShortcuts = shortcuts => {
        if (!pickerShortcuts) return;
        pickerShortcuts.replaceChildren();
        (shortcuts || []).forEach(item => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'storage-picker-shortcut' + (item.path === currentPickerPath ? ' is-active' : '');
            button.dataset.path = item.path ?? '';
            const label = document.createElement('span');
            label.textContent = item.label ?? '';
            button.append(faIcon(shortcutIcon(item.kind)), label);
            pickerShortcuts.append(button);
        });
    };

    const renderFolders = entries => {
        if (!pickerList) return;
        pickerList.replaceChildren();

        if (!entries?.length) {
            const item = document.createElement('li');
            item.className = 'storage-picker-empty';
            item.textContent = 'Tidak ada subfolder di lokasi ini.';
            pickerList.append(item);
            return;
        }

        entries.forEach(item => {
            const entry = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'storage-picker-item';
            button.dataset.path = item.path ?? '';
            const label = document.createElement('span');
            label.textContent = item.name ?? '';
            button.append(faIcon(shortcutIcon(item.kind || 'folder')), label);
            entry.append(button);
            pickerList.append(entry);
        });
    };

    const loadFolders = path => {
        setPickerStatus('Membuka folder…');
        $.ajax({
            url: '/setting/storage-folders',
            type: 'GET',
            data: { path: path || '' },
            success: data => {
                const isComputer = Boolean(data.computer);
                currentPickerPath = isComputer ? COMPUTER_ROOT : (data.path || '');
                currentPickerParent = data.parent || null;
                currentWritable = Boolean(data.writable);
                if (pickerPath) pickerPath.value = isComputer ? 'Komputer' : currentPickerPath;
                if (pickerUp) pickerUp.disabled = !currentPickerParent;
                if (pickerUse) pickerUse.disabled = isComputer;
                renderShortcuts(data.shortcuts || []);
                renderFolders(data.entries || []);
                if (isComputer) {
                    setPickerStatus(
                        data.entries?.length
                            ? 'Pilih drive internal atau eksternal yang terhubung, lalu buka foldernya.'
                            : 'Tidak ada drive yang terhubung.'
                    );
                    return;
                }
                setPickerStatus(
                    currentWritable
                        ? `Folder siap dipakai: ${currentPickerPath}`
                        : `Folder terbuka, tetapi tidak dapat ditulisi: ${currentPickerPath}`
                );
            },
            error: xhr => {
                const message = firstValidationMessage?.(xhr.responseJSON)
                    || xhr.responseJSON?.message
                    || 'Folder tidak dapat dibuka.';
                setPickerStatus(message, true);
            }
        });
    };

    const openPicker = () => {
        if (!pickerModal) return;
        pickerModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        loadFolders(COMPUTER_ROOT);
    };

    const closePicker = () => {
        pickerModal?.classList.remove('show');
        document.body.style.overflow = '';
    };

    if (!canEdit) return;

    const bindAccessToggle = config => {
        const accessSwitch = document.getElementById(config.switchId);
        const accessLabel = document.getElementById(config.labelId);
        if (!accessSwitch) return;

        const token = () => (
            typeof csrfToken !== 'undefined'
                ? csrfToken
                : $('meta[name="csrf-token"]').attr('content')
        );

        const setAccessUi = open => {
            accessSwitch.checked = !!open;
            if (accessLabel) {
                accessLabel.textContent = open ? 'Buka' : 'Tutup';
            }
        };

        fetch(config.statusUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(response => response.json())
            .then(data => setAccessUi(data.open))
            .catch(() => { /* biarkan status UI sebelumnya */ });

        accessSwitch.addEventListener('change', () => {
            const open = accessSwitch.checked;
            setAccessUi(open);

            const queueOffline = async () => {
                await OfflineSync.queueAction({
                    key: config.offlineKey,
                    url: config.toggleUrl,
                    module: config.module,
                    fields: {
                        _token: token(),
                        open: open ? 1 : 0
                    }
                });
                showSuccess(
                    'Tersimpan secara luring.',
                    config.offlineMessage
                );
            };

            if (typeof OfflineSync !== 'undefined' && OfflineSync.isOffline()) {
                return void handleCrudRequestError({ status: 0 }, { queueOffline });
            }

            $.ajax({
                url: config.toggleUrl,
                type: 'POST',
                data: {
                    _token: token(),
                    open: open ? 1 : 0,
                },
                success: response => {
                    setAccessUi(response.open);
                    showSuccess(
                        response.open ? 'Akses dibuka.' : 'Akses ditutup.',
                        response.message || ''
                    );
                },
                error: xhr => {
                    if (xhr.status === 0) return void queueOffline();
                    setAccessUi(!open);
                    const info = typeof describeXhrError === 'function'
                        ? describeXhrError(xhr)
                        : { message: xhr.responseJSON?.message || 'Terjadi kesalahan pada peladen.' };
                    showError(config.failTitle, info.message);
                },
            });
        });
    };

    bindAccessToggle({
        switchId: 'visitoraccessswitch',
        labelId: 'visitoraccesslabel',
        statusUrl: '/visitor/access',
        toggleUrl: '/visitor/access',
        offlineKey: 'action:visitor-access',
        module: 'visitor',
        offlineMessage: 'Perubahan akses pengunjung akan disinkronkan saat koneksi kembali.',
        failTitle: 'Gagal mengubah akses pengunjung.',
    });

    bindAccessToggle({
        switchId: 'submissionaccessswitch',
        labelId: 'submissionaccesslabel',
        statusUrl: '/submission/access',
        toggleUrl: '/submission/access',
        offlineKey: 'action:submission-access',
        module: 'submission',
        offlineMessage: 'Perubahan akses penyerahan akan disinkronkan saat koneksi kembali.',
        failTitle: 'Gagal mengubah akses penyerahan.',
    });

    bindAccessToggle({
        switchId: 'barcodeaccessswitch',
        labelId: 'barcodeaccesslabel',
        statusUrl: '/barcode/access',
        toggleUrl: '/barcode/access',
        offlineKey: 'action:barcode-access',
        module: 'barcode',
        offlineMessage: 'Perubahan akses kode batang akan disinkronkan saat koneksi kembali.',
        failTitle: 'Gagal mengubah akses kode batang.',
    });

    $('#storageshortcuts').on('click', '.storage-shortcut', function () {
        const path = this.dataset.path || '';
        const kind = this.dataset.kind || '';
        if (kind === 'computer') {
            openPicker();
            return;
        }
        if (pathInput) {
            pathInput.value = kind === 'default' ? '' : path;
            pathInput.focus();
        }
    });

    pickButton?.addEventListener('click', openPicker);
    pickerComputer?.addEventListener('click', () => loadFolders(COMPUTER_ROOT));
    document.getElementById('storagepickerclose')?.addEventListener('click', closePicker);
    document.getElementById('storagepickercancel')?.addEventListener('click', closePicker);

    pickerModal?.addEventListener('click', event => {
        if (event.target === pickerModal) closePicker();
    });

    pickerUp?.addEventListener('click', () => {
        if (currentPickerParent) loadFolders(currentPickerParent);
    });

    document.getElementById('storagepickergo')?.addEventListener('click', () => {
        const typed = pickerPath?.value?.trim() || '';
        loadFolders(!typed || typed.toLowerCase() === 'komputer' ? COMPUTER_ROOT : typed);
    });

    pickerPath?.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            const typed = pickerPath.value.trim();
            loadFolders(!typed || typed.toLowerCase() === 'komputer' ? COMPUTER_ROOT : typed);
        }
    });

    $('#storagepickershortcuts').on('click', '.storage-picker-shortcut', function () {
        loadFolders(this.dataset.path || '');
    });

    $('#storagepickerlist').on('click', '.storage-picker-item', function () {
        loadFolders(this.dataset.path || '');
    });

    $('#storagepickerlist').on('dblclick', '.storage-picker-item', function () {
        loadFolders(this.dataset.path || '');
    });

    document.getElementById('storagepickeruse')?.addEventListener('click', () => {
        if (!currentPickerPath || currentPickerPath === COMPUTER_ROOT) return;
        if (pathInput) pathInput.value = currentPickerPath === defaultPath ? '' : currentPickerPath;
        closePicker();
    });

    resetButton?.addEventListener('click', () => {
        if (pathInput) pathInput.value = '';
        savePath('');
    });

    $('#settingform').on('submit', event => {
        event.preventDefault();
        savePath(pathInput?.value?.trim() || '');
    });
})();
