/**
 * Service worker ARSIP-TA:
 * - Precache app shell (halaman, CSS, JS, gambar) untuk mode offline
 * - Network-first untuk halaman dan data index (fallback ke cache)
 * - Cache-first untuk aset statis (gambar, font, webfont)
 * - Background Sync: kirim outbox IndexedDB saat koneksi kembali
 *
 * Catatan: POST /reader/import (Groq AI) tidak di-cache dan tidak masuk outbox.
 */
importScripts('/js/offline-database.js');

const CACHE_NAME = 'arsip-ta-v46';
const SYNC_TAG = 'sync-outbox';

const LOGIN_PRECACHE_URLS = [
    '/',
    '/css/auth-login.css',
    '/css/vendor/poppins-auth.css',
    '/js/pwa-register.js',
    '/js/page-loader-boot.js',
    '/js/dom-elements.js',
    '/js/auth-loader.js',
    '/js/auth-login.js',
    '/images/logo-256.webp',
    '/images/building.webp',
    '/images/icons/icon-192.png',
    '/manifest.json',
    '/luring',
    '/css/offline.css',
];

/**
 * App shell semua role:
 * - Petugas & Koordinator: dasbor + modul admin
 * - Mahasiswa: kunjungan, pindai, serah, anggota, bukti, akun
 * Catatan: POST /reader/import (AI) tidak di-precache / tidak di-antre.
 */
const PRECACHE_URLS = [
    '/',
    '/daftar',
    '/dasbor',
    '/profil',
    '/pengaturan',
    '/tugas-akhir/tesis',
    '/tugas-akhir/skripsi',
    '/tugas-akhir/disertasi',
    '/impor',
    '/prodi',
    '/keanggotaan',
    '/jenista',
    '/pengunjung',
    '/hibahbuku',
    '/bebaspustaka',
    '/kodebatang',
    '/pemusnahan',
    '/arsip',
    '/laporan',
    '/penyerahan',
    '/pengguna',

    '/kunjungan',
    '/pindai',
    '/serah',
    '/anggota',
    '/bukti',
    '/cari',
    '/akun',

    '/css/variables.css',
    '/css/layout.css',
    '/css/components.css',
    '/css/tables.css',
    '/css/forms.css',
    '/css/overlays.css',
    '/css/responsive.css',
    '/css/progress.css',
    '/css/help-tour.css',
    '/css/print-preview.css',
    '/css/offline.css',
    '/cetak/pratinjau',
    '/luring',
    '/css/auth-login.css',
    '/css/student-shell.css',
    '/css/student-submission.css',
    '/css/student-barcode.css',
    '/css/student-proof.css',
    '/css/student-search.css',
    '/css/student-datatable.css',
    '/css/vendor/poppins.css',
    '/css/vendor/poppins-auth.css',
    '/css/vendor/fontawesome.min.css',
    '/css/vendor/air-datepicker.min.css',
    '/css/vendor/driver.css',

    '/js/offline-database.js',
    '/js/page-loader-boot.js',
    '/js/offline-sync.js',
    '/js/pwa-register.js',
    '/js/auth-login.js',
    '/js/student-submission.js',
    '/js/student-membership.js',
    '/js/student-profile.js',
    '/js/student-barcode.js',
    '/js/format-count.js',
    '/js/student-search.js',
    '/js/student-proof.js',
    '/js/barcode-camera.js',
    '/js/dom-elements.js',
    '/js/print-preview.js',
    '/js/global-setup.js',
    '/js/component-imports.js',
    '/js/component-table.js',
    '/js/component-datepicker.js',
    '/js/component-dropdown-select.js',
    '/js/component-modals-alerts.js',
    '/js/form-validation-crud.js',
    '/js/component-scanner.js',
    '/js/component-device-scanner.js',
    '/js/component-charts.js',
    '/js/component-button.js',
    '/js/component-help-tour.js',
    '/js/component-access-qr.js',
    '/js/component-settings.js',
    '/js/vendor/jquery-3.7.1.min.js',
    '/js/vendor/jquery.dataTables.min.js',
    '/js/vendor/jquery.validate.min.js',
    '/js/vendor/popper.min.js',
    '/js/vendor/air-datepicker.min.js',
    '/js/vendor/driver.js.iife.js',
    '/js/vendor/html5-qrcode.min.js',
    '/js/vendor/JsBarcode.all.min.js',
    '/js/vendor/chart.umd.min.js',
    '/js/vendor/pdf.min.js',
    '/js/vendor/pdf.worker.min.js',

    '/webfonts/fa-brands-400.woff2',
    '/webfonts/fa-regular-400.woff2',
    '/webfonts/fa-solid-900.woff2',
    '/webfonts/fa-v4compatibility.woff2',

    '/images/logo.png',
    '/images/logo-256.webp',
    '/images/batik.webp',
    '/images/pattern.png',
    '/images/building.webp',
    '/images/icons/icon-192.png',
    '/images/icons/icon-512.png',
    '/images/icons/icon-512-maskable.png',
    '/svg/loader-ring.svg',
    '/svg/auth-icons.svg',
    '/svg/trash.svg',
    '/svg/access-shield.svg',
    '/svg/access-blocked.svg',
    '/svg/metadata-hero.svg',

    '/manifest.json'
];

const INDEX_PATHS = [
    '/finalproject/index',
    '/finalproject/options',
    '/studyprogram/index',
    '/studyprogram/options',
    '/membership/index',
    '/user/options',
    '/finalprojecttype/index',
    '/finalprojecttype/options',
    '/visitor/index',
    '/bookgrant/index',
    '/libraryclearance/index',
    '/barcode/index',
    '/destruction/index',
    '/archive/index',
    '/report/index',
    '/submission/index',
    '/user/index',
    '/cari/index',
    '/bukti/index'
];

self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_NAME);

        // Hanya shell login dulu agar kunjungan pertama tidak bersaing dengan aset admin
        await Promise.allSettled(LOGIN_PRECACHE_URLS.map(url => cache.add(url)));

        await self.skipWaiting();
    })());
});

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        const cacheNames = await caches.keys();

        await Promise.all(
            cacheNames
                .filter(name => name !== CACHE_NAME)
                .map(name => caches.delete(name))
        );

        await self.clients.claim();

        const cache = await caches.open(CACHE_NAME);
        const pending = PRECACHE_URLS.filter(url => !LOGIN_PRECACHE_URLS.includes(url));
        void Promise.allSettled(pending.map(url => cache.add(url)));
    })());
});

/** Cocokkan cache penuh, lalu fallback pathname (abaikan ?v= asset_version). */
async function matchCached(cache, request) {
    const exact = await cache.match(request);
    if (exact) return exact;

    try {
        const url = new URL(request.url);
        if (url.origin === self.location.origin && url.search) {
            return cache.match(url.pathname);
        }
    } catch {
        /* ignore */
    }

    return undefined;
}

async function putVersionedAsset(cache, request, response) {
    await cache.put(request, response.clone());

    try {
        const url = new URL(request.url);
        if (
            url.origin === self.location.origin &&
            url.search &&
            /^\/(css|js)\//.test(url.pathname)
        ) {
            await cache.put(url.pathname, response.clone());
        }
    } catch {
        /* ignore */
    }
}

async function networkFirst(request, fallbackUrl = null) {
    const cache = await caches.open(CACHE_NAME);

    try {
        const response = await fetch(request);

        if (response.ok) {
            await putVersionedAsset(cache, request, response);
        }

        return response;
    } catch {
        const cached = await matchCached(cache, request);

        if (cached) return cached;

        if (fallbackUrl) {
            const fallback = await cache.match(fallbackUrl);
            if (fallback) return fallback;
        }

        const url = new URL(request.url);
        const isPage = request.mode === 'navigate' || request.headers.get('accept')?.includes('text/html');
        if (isPage) {
            const offlinePage = await cache.match('/luring');
            if (offlinePage) return offlinePage;

            return new Response('Tidak ada jaringan. Halaman ini belum tersimpan di perangkat.', {
                status: 503,
                headers: { 'Content-Type': 'text/plain; charset=utf-8' }
            });
        }

        return new Response(JSON.stringify({
            message: 'Offline dan tidak ada salinan cache.',
            data: [],
            recordsTotal: 0,
            recordsFiltered: 0
        }), { status: 503, headers: { 'Content-Type': 'application/json' } });
    }
}

async function cacheFirst(request) {
    const cache = await caches.open(CACHE_NAME);
    const cached = await matchCached(cache, request);

    if (cached) return cached;

    try {
        const response = await fetch(request);

        if (response.ok || response.type === 'opaque') {
            await putVersionedAsset(cache, request, response);
        }

        return response;
    } catch {
        return new Response('', { status: 503, statusText: 'Offline' });
    }
}

self.addEventListener('fetch', event => {
    const { request } = event;

    // The page handles writes through the IndexedDB queue. Leave them alone,
    // including POST /reader/import (Groq). Let that request fail when offline.
    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // Page navigation is network-first, then the matching cached copy.
    // Covers every officer, coordinator, and student module.
    if (request.mode === 'navigate') {
        let fallback = '/';
        if (url.pathname.startsWith('/kunjungan')) fallback = '/kunjungan';
        else if (url.pathname.startsWith('/pindai')) fallback = '/pindai';
        else if (url.pathname.startsWith('/serah')) fallback = '/serah';
        else if (url.pathname.startsWith('/anggota')) fallback = '/anggota';
        else if (url.pathname.startsWith('/bukti')) fallback = '/bukti';
        else if (url.pathname.startsWith('/cari')) fallback = '/cari';
        else if (url.pathname.startsWith('/akun')) fallback = '/akun';
        else if (url.pathname.startsWith('/tugas-akhir/')) fallback = '/tugas-akhir/tesis';
        else if (url.pathname === '/impor') fallback = '/impor';
        else if (url.pathname === '/' || url.pathname.startsWith('/daftar')) fallback = '/';
        else fallback = '/dasbor';

        event.respondWith(networkFirst(request, fallback));
        return;
    }

    if (url.origin === self.location.origin) {

        // Data index: network-first agar selalu segar, cache saat offline
        if (INDEX_PATHS.includes(url.pathname)) {
            event.respondWith(networkFirst(request));
            return;
        }

        // JS/CSS: network-first supaya reload biasa mendapat file terbaru
        if (/^\/(css|js)\//.test(url.pathname)) {
            event.respondWith(networkFirst(request));
            return;
        }

        // Gambar, font, webfont & manifest: cache-first (jarang berubah)
        if (
            /^\/(images|fonts|webfonts)\//.test(url.pathname) ||
            url.pathname === '/manifest.json'
        ) {
            event.respondWith(cacheFirst(request));
            return;
        }

        return;
    }
});

self.addEventListener('sync', event => {
    if (event.tag === SYNC_TAG) {
        event.waitUntil(OfflineDatabase.flushOutbox());
    }
});
