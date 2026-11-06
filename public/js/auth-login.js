(() => {
    const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    const setBusy = (button, label) => {
        if (!button) return;
        button.disabled = true;
        const span = button.querySelector('span');
        if (span) span.textContent = label;
    };

    const getFormGroup = input => input?.closest('.form-group');

    const clearFieldError = input => {
        if (!input) return;
        input.classList.remove('is-invalid');
        getFormGroup(input)?.querySelector('.field-error')?.remove();
    };

    const showFieldError = (input, message) => {
        if (!input) return;
        clearFieldError(input);
        input.classList.add('is-invalid');

        const group = getFormGroup(input);
        if (!group || !message) return;

        const error = document.createElement('div');
        error.className = 'field-error';
        error.setAttribute('role', 'alert');
        error.textContent = message;
        group.appendChild(error);
    };

    const bindLiveClear = (...inputs) => {
        inputs.forEach(input => {
            if (!input) return;
            const clear = () => clearFieldError(input);
            input.addEventListener('input', clear);
            input.addEventListener('change', clear);
        });
    };

    const validateFields = rules => {
        let firstInvalid = null;

        rules.forEach(({ input, message }) => {
            if (message) {
                showFieldError(input, message);
                if (!firstInvalid) firstInvalid = input;
            } else {
                clearFieldError(input);
            }
        });

        return firstInvalid;
    };

    /* ——— Login ——— */
    const loginForm = document.getElementById('loginform');
    const loginBtn = document.getElementById('loginbtn');
    const nameInput = document.getElementById('name');
    const password = document.getElementById('password');

    if (loginForm && loginBtn) {
        bindLiveClear(nameInput, password);

        loginForm.addEventListener('submit', event => {
            const nameValue = nameInput?.value.trim() || '';
            const passValue = password?.value || '';

            const firstInvalid = validateFields([
                {
                    input: nameInput,
                    message: !nameValue
                        ? 'Nama harus diisi!'
                        : nameValue.length > 255
                            ? 'Nama maksimal 255 karakter!'
                            : '',
                },
                {
                    input: password,
                    message: !passValue ? 'Kata sandi harus diisi!' : '',
                },
            ]);

            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.focus();
                return;
            }

            window.AuthPageLoader?.arm('login');
            setBusy(loginBtn, 'Memproses…');
        });
    }

    /* ——— Daftar akun ——— */
    const registerForm = document.getElementById('registerform');
    const registerBtn = document.getElementById('registerbtn');
    const identityNumber = document.getElementById('useridentitynumber');
    const regName = document.getElementById('reg_username');
    const email = document.getElementById('useremail');
    const regPassword = document.getElementById('userpassword');
    const role = document.getElementById('userrole');

    if (registerForm && registerBtn) {
        bindLiveClear(identityNumber, regName, email, regPassword, role);

        registerForm.addEventListener('submit', event => {
            const identityValue = identityNumber?.value.trim() || '';
            const nameValue = regName?.value.trim() || '';
            const emailValue = email?.value.trim() || '';
            const passValue = regPassword?.value || '';
            const roleValue = role?.value || '';

            const firstInvalid = validateFields([
                {
                    input: identityNumber,
                    message: !identityValue
                        ? 'Nomor identitas harus diisi!'
                        : identityValue.length > 50
                            ? 'Nomor identitas maksimal 50 karakter!'
                            : !/^[0-9\s./-]+$/.test(identityValue)
                                ? 'Nomor identitas hanya boleh berisi angka!'
                                : '',
                },
                {
                    input: regName,
                    message: !nameValue
                        ? 'Nama harus diisi!'
                        : nameValue.length < 3
                            ? 'Nama minimal 3 karakter!'
                            : nameValue.length > 255
                                ? 'Nama maksimal 255 karakter!'
                                : '',
                },
                {
                    input: email,
                    message: !emailValue
                        ? 'Email harus diisi!'
                        : !EMAIL_RE.test(emailValue)
                            ? 'Format email tidak valid!'
                            : emailValue.length > 255
                                ? 'Email maksimal 255 karakter!'
                                : '',
                },
                {
                    input: regPassword,
                    message: !passValue
                        ? 'Kata sandi wajib diisi.'
                        : passValue.length < 8
                            ? 'Kata sandi minimal 8 karakter.'
                            : '',
                },
                {
                    input: role,
                    message: !roleValue ? 'Peran harus dipilih!' : '',
                },
            ]);

            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.focus();
                return;
            }

            setBusy(registerBtn, 'Mendaftarkan…');
        });
    }

    /* ——— Custom select (auth visitor / login) ——— */
    const initAuthCustomSelects = () => {
        document.querySelectorAll('.auth-login .custom-select').forEach(select => {
            // Halaman penyerahan punya handler sendiri (student-submission.js)
            if (
                select.closest('.auth-submission')
                || select.dataset.searchable === 'true'
                || select.dataset.initialized === 'true'
            ) {
                return;
            }

            const trigger = select.querySelector('.custom-select-trigger');
            const options = select.querySelectorAll('.custom-option');
            const hiddenInput = select.querySelector('input[type="hidden"]');
            const text = trigger?.querySelector('span');

            if (!trigger || !hiddenInput || !text) return;

            select.dataset.initialized = 'true';

            trigger.addEventListener('click', event => {
                event.stopPropagation();
                document.querySelectorAll('.auth-login .custom-select').forEach(el => {
                    if (el === select || el.closest('.auth-submission')) return;
                    el.classList.remove('active');
                });
                select.classList.toggle('active');
            });

            trigger.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    trigger.click();
                }
            });

            options.forEach(option => {
                option.addEventListener('click', () => {
                    options.forEach(o => o.classList.remove('selected'));
                    option.classList.add('selected');

                    const value = option.dataset.value || '';
                    text.textContent = option.textContent.trim();
                    text.classList.toggle('contain', value !== '');
                    hiddenInput.value = value;

                    select.classList.remove('active', 'is-invalid');
                    clearFieldError(hiddenInput);
                    hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
        });

        document.addEventListener('click', () => {
            document.querySelectorAll('.auth-login .custom-select').forEach(el => {
                if (el.closest('.auth-submission')) return;
                el.classList.remove('active');
            });
        });
    };

    initAuthCustomSelects();

    /* ——— Formulir daftar kunjungan publik ——— */
    const visitorForm = document.getElementById('visitorpublicform');
    const visitorSubmitBtn = document.getElementById('visitorsubmitbtn');
    const visitorResetBtn = document.getElementById('visitorresetbtn');
    const visitorNim = document.getElementById('visitor_nim');
    const visitorName = document.getElementById('visitor_student_name');
    const visitorProgram = document.getElementById('visitor_program_study');
    const visitorNeeds = document.getElementById('visitor_needs');
    const visitorProgramSelect = document.getElementById('visitor_program_select');

    const resetVisitorSelect = () => {
        if (!visitorProgramSelect || !visitorProgram) return;

        visitorProgram.value = '';
        visitorProgramSelect.classList.remove('active', 'is-invalid');
        visitorProgramSelect.querySelectorAll('.custom-option').forEach(o => {
            o.classList.remove('selected');
        });

        const span = visitorProgramSelect.querySelector('.custom-select-trigger span');
        if (span) {
            span.textContent = 'Pilih Program Studi';
            span.classList.remove('contain');
        }
    };

    if (visitorForm && visitorSubmitBtn) {
        bindLiveClear(visitorNim, visitorName, visitorProgram, visitorNeeds);

        const showVisitorOfflineSuccess = () => {
            const existing = visitorForm.querySelector('.js-offline-alert');
            existing?.remove();

            const alert = document.createElement('div');
            alert.className = 'login-alert login-alert--success js-offline-alert';
            alert.setAttribute('role', 'status');
            alert.append(faIcon('fa-circle-check'));
            const message = document.createElement('span');
            message.textContent = 'Tersimpan secara luring. Data kunjungan akan dikirim otomatis saat koneksi kembali.';
            alert.append(message);
            visitorForm.prepend(alert);
        };

        visitorForm.addEventListener('submit', async event => {
            const nimValue = visitorNim?.value.trim() || '';
            const nameValue = visitorName?.value.trim() || '';
            const programValue = visitorProgram?.value.trim() || '';
            const needsValue = visitorNeeds?.value.trim() || '';

            if (!programValue) {
                visitorProgramSelect?.classList.add('is-invalid');
            } else {
                visitorProgramSelect?.classList.remove('is-invalid');
            }

            const firstInvalid = validateFields([
                {
                    input: visitorNim,
                    message: !nimValue
                        ? 'NIM / KTP harus diisi!'
                        : nimValue.length > 50
                            ? 'NIM / KTP maksimal 50 karakter!'
                            : '',
                },
                {
                    input: visitorName,
                    message: !nameValue
                        ? 'Nama mahasiswa harus diisi!'
                        : nameValue.length < 3
                            ? 'Nama mahasiswa minimal 3 karakter!'
                            : nameValue.length > 255
                                ? 'Nama mahasiswa maksimal 255 karakter!'
                                : '',
                },
                {
                    input: visitorProgram,
                    message: !programValue ? 'Program studi harus dipilih!' : '',
                },
                {
                    input: visitorNeeds,
                    message: !needsValue
                        ? 'Keperluan harus diisi!'
                        : needsValue.length > 65535
                            ? 'Keperluan maksimal 65535 karakter!'
                            : '',
                },
            ]);

            if (firstInvalid) {
                event.preventDefault();
                if (firstInvalid === visitorProgram) {
                    visitorProgramSelect?.querySelector('.custom-select-trigger')?.focus();
                } else {
                    firstInvalid.focus();
                }
                return;
            }

            if (window.PwaRegister?.isOffline?.() && typeof OfflineDatabase !== 'undefined') {
                event.preventDefault();
                setBusy(visitorSubmitBtn, 'Menyimpan…');

                try {
                    const payload = PwaRegister.formToOutboxPayload(visitorForm);
                    await PwaRegister.queueAction({
                        key: `action:public-visitor:${Date.now()}`,
                        url: visitorForm.action || '/kunjungan',
                        module: 'visitor',
                        fields: payload.fields,
                        files: payload.files
                    });

                    visitorForm.reset();
                    resetVisitorSelect();
                    showVisitorOfflineSuccess();
                } catch (error) {
                    console.error(error);
                }

                visitorSubmitBtn.disabled = false;
                const span = visitorSubmitBtn.querySelector('span');
                if (span) span.textContent = 'Simpan';
                return;
            }

            setBusy(visitorSubmitBtn, 'Menyimpan…');
        });
    }

    if (visitorResetBtn && visitorForm) {
        visitorResetBtn.addEventListener('click', event => {
            event.preventDefault();
            visitorForm.reset();
            resetVisitorSelect();
            [visitorNim, visitorName, visitorProgram, visitorNeeds].forEach(clearFieldError);
            visitorProgramSelect?.classList.remove('is-invalid');
            visitorNim?.focus();
        });
    }

    /* ——— Akses / Metadata: keluar (tutup jendela) ——— */
    const accessExit = document.getElementById('accessexit');
    if (accessExit) {
        accessExit.addEventListener('click', () => {
            // Hanya berhasil jika tab/jendela dibuka lewat window.open()
            window.close();

            // Browser modern memblokir close pada tab biasa → keluar dengan navigasi
            window.setTimeout(() => {
                if (window.history.length > 1) {
                    window.history.back();
                    return;
                }

                window.location.replace('/');
            }, 150);
        });
    }
})();
