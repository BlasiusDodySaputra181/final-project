/**
 * Shared PWA core (auth, public, and officer):
 * - service worker registration
 * - offline banner
 * - queueAction and outbox flush
 *
 * OfflineSync (officer) uses this API; do not register the service worker again there.
 */
(() => {
    const SYNC_TAG = 'sync-outbox';
    let deferredInstall = null;

    function inStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;
    }

    function allowsBrowserInstall() {
        const agent = navigator.userAgent || '';
        return /Android|iPhone|iPad|iPod/i.test(agent);
    }

    function syncInstallButtons() {
        const available = allowsBrowserInstall() && deferredInstall !== null && !inStandalone();
        document.querySelectorAll('[data-pwa-install]').forEach(button => {
            button.hidden = !available;
        });
    }

    async function promptInstall() {
        if (!deferredInstall) {
            return { outcome: 'unavailable' };
        }

        deferredInstall.prompt();
        const choice = await deferredInstall.userChoice;
        deferredInstall = null;
        syncInstallButtons();
        return choice;
    }

    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        if (!allowsBrowserInstall()) {
            return;
        }
        deferredInstall = event;
        syncInstallButtons();
        window.dispatchEvent(new CustomEvent('pwa:installable'));
    });

    window.addEventListener('appinstalled', () => {
        deferredInstall = null;
        syncInstallButtons();
        window.dispatchEvent(new CustomEvent('pwa:installed'));
    });

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-pwa-install]');
        if (!button) return;
        event.preventDefault();
        promptInstall();
    });

    function isOffline() {
        return !navigator.onLine;
    }

    function updateOfflineBanner() {
        const banner = document.getElementById('offlinebanner');
        if (banner) banner.classList.toggle('show', isOffline());
    }

    async function requestBackgroundSync() {
        try {
            const registration = await navigator.serviceWorker?.ready;
            await registration?.sync?.register(SYNC_TAG);
        } catch {
            /* Background Sync is optional */
        }
    }

    async function queueAction({ key, url, fields = {}, files = {}, module = 'action' }) {
        if (typeof OfflineDatabase === 'undefined') {
            throw new Error('OfflineDatabase belum dimuat.');
        }

        await OfflineDatabase.putOutboxItem({
            key: key || `action:${url}:${Date.now()}`,
            module,
            type: 'action',
            queuedAt: Date.now(),
            url,
            fields,
            files
        });

        await requestBackgroundSync();
    }

    function formToOutboxPayload(form) {
        const formData = new FormData(form);
        const fields = {};
        const files = {};

        for (const [key, value] of formData.entries()) {
            if (value instanceof File) {
                if (!value.name && value.size === 0) continue;

                if (Object.prototype.hasOwnProperty.call(files, key)) {
                    files[key] = Array.isArray(files[key]) ? files[key] : [files[key]];
                    files[key].push(value);
                } else {
                    files[key] = value;
                }
                continue;
            }

            if (Object.prototype.hasOwnProperty.call(fields, key)) {
                fields[key] = Array.isArray(fields[key]) ? fields[key] : [fields[key]];
                fields[key].push(value);
            } else {
                fields[key] = value;
            }
        }

        return { fields, files };
    }

    function notifyFlush(sent) {
        if (sent <= 0) return;

        if (typeof showSuccess === 'function') {
            showSuccess(
                'Sinkronisasi selesai.',
                `${sent} perubahan luring telah berhasil dikirim ke basis data.`
            );
            return;
        }

        const slot = document.getElementById('pwa-sync-slot');
        if (!slot) return;

        slot.replaceChildren();
        const alert = document.createElement('div');
        alert.className = 'login-alert login-alert--success';
        alert.setAttribute('role', 'status');
        const icon = document.createElement('i');
        icon.className = 'auth-icon';
        icon.setAttribute('aria-hidden', 'true');
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('focusable', 'false');
        const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
        use.setAttribute('href', '/svg/auth-icons.svg#check');
        svg.append(use);
        icon.append(svg);
        const message = document.createElement('span');
        message.textContent = `${sent} perubahan luring berhasil dikirim ke basis data.`;
        alert.append(icon, message);
        slot.append(alert);
    }

    async function flushOutboxIfPossible() {
        if (isOffline() || typeof OfflineDatabase === 'undefined') return 0;

        try {
            const sent = await OfflineDatabase.flushOutbox();
            notifyFlush(sent);
            window.dispatchEvent(new CustomEvent('pwa:flushed', { detail: { sent } }));
            return sent;
        } catch {
            return 0;
        }
    }

    const registerServiceWorker = () => {
        if (!('serviceWorker' in navigator)) return;

        navigator.serviceWorker.register('/service-worker.js')
            .then(registration => registration.update())
            .catch(error => {
                console.warn('Service worker registration failed:', error?.message || error);
            });
    };

    const scheduleServiceWorker = () => {
        const run = () => registerServiceWorker();

        if ('requestIdleCallback' in window) {
            requestIdleCallback(run, { timeout: 4000 });
            return;
        }

        window.setTimeout(run, 1500);
    };

    if (document.readyState === 'complete') {
        scheduleServiceWorker();
    } else {
        window.addEventListener('load', scheduleServiceWorker, { once: true });
    }

    window.addEventListener('online', () => {
        updateOfflineBanner();
        flushOutboxIfPossible();
        requestBackgroundSync();
    });

    window.addEventListener('offline', updateOfflineBanner);

    document.addEventListener('DOMContentLoaded', () => {
        updateOfflineBanner();
        flushOutboxIfPossible();
    });

    window.PwaRegister = {
        isOffline,
        updateOfflineBanner,
        flushOutboxIfPossible,
        requestBackgroundSync,
        queueAction,
        formToOutboxPayload,
        promptInstall,
        syncInstallButtons
    };

    document.addEventListener('DOMContentLoaded', syncInstallButtons);
})();
