/**
 * Lapisan IndexedDB bersama untuk halaman DAN service worker.
 * Salinan index modul CRUD + antrean outbox (sinkron saat online kembali).
 * Catatan: /reader/import (Groq AI) tidak pernah masuk outbox.
 */
const OfflineDatabase = (() => {

    const DATABASE_NAME = 'arsip-ta-offline';
    const DATABASE_VERSION = 1;

    const INDEX_STORE = 'indexRows';
    const OUTBOX_STORE = 'outbox';
    const META_STORE = 'meta';
    const MAX_INDEX_ROWS = 800;

    const FLUSH_LOCK_KEY = 'flushLock';
    const FLUSH_LOCK_DURATION = 30000;

    const isLocalId = id => String(id).startsWith('local-');

    let databasePromise = null;

    function openDatabase() {
        if (databasePromise) return databasePromise;

        databasePromise = new Promise((resolve, reject) => {
            const request = indexedDB.open(DATABASE_NAME, DATABASE_VERSION);

            request.onupgradeneeded = () => {
                const database = request.result;
                if (!database.objectStoreNames.contains(INDEX_STORE)) database.createObjectStore(INDEX_STORE);
                if (!database.objectStoreNames.contains(OUTBOX_STORE)) {
                    database.createObjectStore(OUTBOX_STORE, { keyPath: 'key' });
                }
                if (!database.objectStoreNames.contains(META_STORE)) database.createObjectStore(META_STORE);
            };

            request.onsuccess = () => {
                const database = request.result;
                database.onversionchange = () => {
                    database.close();
                    databasePromise = null;
                };
                resolve(database);
            };
            request.onerror = () => {
                databasePromise = null;
                reject(request.error);
            };
        });

        return databasePromise;
    }

    async function runTransaction(storeName, mode, operation) {
        const database = await openDatabase();

        try {
            return await new Promise((resolve, reject) => {
                const transaction = database.transaction(storeName, mode);
                const request = operation(transaction.objectStore(storeName));

                transaction.oncomplete = () => resolve(request?.result);
                transaction.onerror = () => reject(transaction.error || request?.error);
                transaction.onabort = () => reject(transaction.error || request?.error);
            });
        } catch (error) {
            if (error?.name === 'InvalidStateError') {
                databasePromise = null;
            }
            if (error?.name === 'QuotaExceededError' || error?.code === 22) {
                throw new Error('Penyimpanan perangkat penuh. Hapus data situs atau kurangi data offline.');
            }
            throw error;
        }
    }

    const saveIndexRows = (indexKey, rows) =>
        runTransaction(INDEX_STORE, 'readwrite', store => store.put(rows, indexKey));

    const getIndexRows = async indexKey =>
        (await runTransaction(INDEX_STORE, 'readonly', store => store.get(indexKey))) ?? [];

    /**
     * Gabungkan halaman server-side ke salinan IndexedDB (berdasarkan id baris).
     */
    const mergeIndexRows = async (indexKey, pageRows, meta = {}) => {
        const rows = Array.isArray(pageRows) ? pageRows : [];
        const recordsTotal = Number(meta.recordsTotal);
        const recordsFiltered = Number(meta.recordsFiltered);
        const start = Number(meta.start ?? 0);
        const keyword = String(meta.keyword ?? '').trim();

        if (rows.length === 0) {
            if (start === 0 && (recordsTotal === 0 || (recordsFiltered === 0 && keyword === ''))) {
                return saveIndexRows(indexKey, []);
            }
            return;
        }

        const existing = await getIndexRows(indexKey);
        const byId = new Map();

        (Array.isArray(existing) ? existing : []).forEach(row => {
            if (!Array.isArray(row)) return;
            byId.set(String(row[1] ?? row[0]), row);
        });

        rows.forEach(row => {
            if (!Array.isArray(row)) return;
            byId.set(String(row[1] ?? row[0]), row);
        });

        let merged = Array.from(byId.values());
        if (merged.length > MAX_INDEX_ROWS) {
            merged.sort((a, b) => Number(b?.[1] || 0) - Number(a?.[1] || 0));
            merged = merged.slice(0, MAX_INDEX_ROWS);
        }

        return saveIndexRows(indexKey, merged);
    };

    const putOutboxItem = item => {
        const url = String(item?.url || item?.saveUrl || '');

        // Jangan pernah antrekan reader AI — wajib online
        if (url.includes('/reader/import')) {
            return Promise.reject(new Error('POST /reader/import tidak dapat diantrekan offline.'));
        }

        return runTransaction(OUTBOX_STORE, 'readwrite', store => store.put(item));
    };

    const getOutboxItem = key =>
        runTransaction(OUTBOX_STORE, 'readonly', store => store.get(key));

    const deleteOutboxItem = key =>
        runTransaction(OUTBOX_STORE, 'readwrite', store => store.delete(key));

    const getOutboxItems = async () =>
        ((await runTransaction(OUTBOX_STORE, 'readonly', store => store.getAll())) ?? [])
            .sort((a, b) => a.queuedAt - b.queuedAt);

    /** Kunci flush atomik (cek + set dalam satu transaksi). */
    async function acquireFlushLock() {
        const database = await openDatabase();

        try {
            return await new Promise((resolve, reject) => {
                const transaction = database.transaction(META_STORE, 'readwrite');
                const store = transaction.objectStore(META_STORE);
                const getRequest = store.get(FLUSH_LOCK_KEY);
                let acquired = false;

                getRequest.onsuccess = () => {
                    const lock = getRequest.result;
                    if (lock && lock.until > Date.now()) return;

                    store.put({ until: Date.now() + FLUSH_LOCK_DURATION }, FLUSH_LOCK_KEY);
                    acquired = true;
                };

                getRequest.onerror = () => reject(getRequest.error);
                transaction.oncomplete = () => resolve(acquired);
                transaction.onerror = () => reject(transaction.error);
                transaction.onabort = () => reject(transaction.error);
            });
        } catch (error) {
            if (error?.name === 'InvalidStateError') {
                databasePromise = null;
            }
            throw error;
        }
    }

    const releaseFlushLock = () =>
        runTransaction(META_STORE, 'readwrite', store => store.delete(FLUSH_LOCK_KEY));

    function buildSaveFormData(item) {
        const formData = new FormData();
        const idField = item.idField || 'finalprojectid';

        Object.entries(item.fields ?? {}).forEach(([key, value]) => {
            if (key === idField && isLocalId(value)) return;
            if (value !== null && value !== undefined && value !== '') {
                formData.append(key, value);
            }
        });

        Object.entries(item.files ?? {}).forEach(([key, file]) => {
            if (file instanceof File) formData.append(key, file, file.name);
        });

        return formData;
    }

    function appendFormValue(formData, key, value) {
        if (value === null || value === undefined) return;

        if (Array.isArray(value)) {
            value.forEach(entry => appendFormValue(formData, key, entry));
            return;
        }

        if (value instanceof File) {
            formData.append(key, value, value.name);
            return;
        }

        if (value !== '') formData.append(key, value);
    }

    function buildActionFormData(item) {
        const formData = new FormData();

        Object.entries(item.fields ?? {}).forEach(([key, value]) => {
            appendFormValue(formData, key, value);
        });

        // Berkas berindeks eksplisit: final_project_file[0], approval_sheet[2], ...
        Object.entries(item.files ?? {}).forEach(([key, value]) => {
            appendFormValue(formData, key, value);
        });

        return formData;
    }

    function isLoginHtml(response, bodyText) {
        if (!bodyText) return false;
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('text/html')) return false;
        return /name=["']email["']/i.test(bodyText) || /auth-login/i.test(bodyText);
    }

    async function sendOutboxItem(item) {
        if (item.type === 'save') {
            const response = await fetch(item.saveUrl || '/storefinalproject', {
                method: 'POST',
                body: buildSaveFormData(item),
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
            });
            return response.ok;
        }

        if (item.type === 'delete') {
            const formData = new FormData();
            formData.append('_method', 'DELETE');
            formData.append('_token', item.token);

            const response = await fetch(item.deleteUrl || `/finalproject/destroy/${item.id}`, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
            });
            return response.ok;
        }

        if (item.type === 'action') {
            const response = await fetch(item.url, {
                method: 'POST',
                body: buildActionFormData(item),
                credentials: 'same-origin',
                redirect: 'follow',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) return false;

            // Redirect ke halaman login = sesi habis, jangan anggap sukses
            const bodyText = await response.clone().text().catch(() => '');
            if (isLoginHtml(response, bodyText)) return false;

            return true;
        }

        return false;
    }

    /**
     * Kirim outbox berurutan. Hanya hapus item yang benar-benar sukses.
     * Gagal jaringan: berhenti (item tetap). Gagal HTTP: tetap di antrean, lanjut item berikutnya.
     */
    async function flushOutbox() {
        if (!(await acquireFlushLock())) return 0;

        let sent = 0;

        try {
            for (const item of await getOutboxItems()) {
                let succeeded = false;

                try {
                    succeeded = await sendOutboxItem(item);
                } catch {
                    break;
                }

                if (succeeded) {
                    await deleteOutboxItem(item.key);
                    sent++;
                }
            }
        } finally {
            await releaseFlushLock();
        }

        return sent;
    }

    return {
        isLocalId,
        saveIndexRows,
        getIndexRows,
        mergeIndexRows,
        putOutboxItem,
        getOutboxItem,
        deleteOutboxItem,
        getOutboxItems,
        flushOutbox
    };

})();

self.OfflineDatabase = OfflineDatabase;
