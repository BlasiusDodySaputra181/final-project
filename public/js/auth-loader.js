(() => {
    const KEY = 'authPageLoader';
    const DURATION_MS = {
        login: 100,
        logout: 100,
    };

    const statusFor = mode => {
        if (mode === 'logout') return 'Sedang keluar...';
        if (mode === 'login') return 'Sedang masuk...';
        return 'Memuat data...';
    };

    const durationFor = mode => DURATION_MS[mode] ?? DURATION_MS.login;

    const getLoader = () => document.getElementById('pageloader');

    const setStatus = mode => {
        const status = getLoader()?.querySelector('.loader-status');
        if (status) status.textContent = statusFor(mode);
    };

    const show = (mode = 'login') => {
        const loader = getLoader();
        if (!loader) return;

        setStatus(mode);
        loader.classList.remove('hide');
        loader.setAttribute('aria-hidden', 'false');
    };

    const hide = () => {
        const loader = getLoader();
        if (!loader) return;

        loader.classList.add('hide');
        loader.setAttribute('aria-hidden', 'true');
    };

    const arm = (mode = 'login') => {
        try {
            sessionStorage.setItem(KEY, mode);
        } catch (_) {
            /* ignore quota / private mode */
        }
        show(mode);
    };

    const consumePending = () => {
        let mode = null;

        try {
            mode = sessionStorage.getItem(KEY);
            if (mode) sessionStorage.removeItem(KEY);
        } catch (_) {
            /* ignore */
        }

        if (!mode) {
            hide();
            return;
        }

        show(mode);
        window.setTimeout(hide, durationFor(mode));
    };

    const bindLogoutForms = () => {
        document.querySelectorAll('form[action*="/logout"]').forEach(form => {
            if (form.dataset.authLoaderBound === '1') return;
            form.dataset.authLoaderBound = '1';
            form.addEventListener('submit', () => arm('logout'));
        });
    };

    window.AuthPageLoader = { arm, show, hide, consumePending, bindLogoutForms };

    const boot = () => {
        consumePending();
        bindLogoutForms();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
