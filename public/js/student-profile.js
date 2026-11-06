(() => {
    const form = document.getElementById('profilepublicform');
    if (!form) return;

    const identityInput = document.getElementById('profileidentitynumber');
    const nameInput = document.getElementById('profilename');
    const emailInput = document.getElementById('profileemail');
    const passwordInput = document.getElementById('profilepassword');
    const passwordConfirmInput = document.getElementById('profilepassword_confirmation');
    const submitBtn = document.getElementById('buttonprofile');

    function clearFieldError(el) {
        if (!el) return;
        el.classList.remove('input-error');
        const group = el.closest('.form-group');
        group?.querySelectorAll('.js-field-error').forEach((node) => node.remove());
    }

    function showFieldError(el, message) {
        if (!el || !message) return;
        clearFieldError(el);
        el.classList.add('input-error');

        const group = el.closest('.form-group');
        if (!group) return;

        const error = document.createElement('div');
        error.className = 'field-error js-field-error';
        error.setAttribute('role', 'alert');
        error.textContent = message;
        group.appendChild(error);
    }

    function emailLooksValid(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    [identityInput, nameInput, emailInput, passwordInput, passwordConfirmInput].forEach((input) => {
        input?.addEventListener('input', () => clearFieldError(input));
    });

    form.addEventListener('submit', (event) => {
        const identity = String(identityInput?.value || '').trim();
        const name = String(nameInput?.value || '').trim();
        const email = String(emailInput?.value || '').trim();
        const password = String(passwordInput?.value || '');
        const passwordConfirm = String(passwordConfirmInput?.value || '');

        const rules = [
            { input: identityInput, message: !identity ? 'NIM harus diisi!' : '' },
            { input: nameInput, message: !name ? 'Nama harus diisi!' : '' },
            {
                input: emailInput,
                message: !email
                    ? 'Email harus diisi!'
                    : (emailLooksValid(email) ? '' : 'Format email tidak valid!'),
            },
            {
                input: passwordInput,
                message: password && password.length < 8 ? 'Kata sandi minimal 8 karakter!' : '',
            },
            {
                input: passwordConfirmInput,
                message: password && password !== passwordConfirm
                    ? 'Konfirmasi kata sandi tidak cocok!'
                    : '',
            },
        ];

        let firstInvalid = null;
        rules.forEach(({ input, message }) => {
            if (message) {
                showFieldError(input, message);
                if (!firstInvalid) firstInvalid = input;
            } else {
                clearFieldError(input);
            }
        });

        if (firstInvalid) {
            event.preventDefault();
            firstInvalid.closest('.form-group')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalid.focus();
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('is-busy');
        }
    });
})();
