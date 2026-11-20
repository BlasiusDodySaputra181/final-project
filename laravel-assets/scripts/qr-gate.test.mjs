import assert from 'node:assert/strict';
import worker from './qr-gate.js';

const store = new Map();
const env = {
    GATE: 'g'.repeat(40),
    TARGET: {
        async get(key) {
            return store.has(key) ? store.get(key) : null;
        },
        async put(key, value) {
            store.set(key, value);
        },
    },
};

const front = 'https://arsip.nama.workers.dev';
const origin = 'https://perpustakaan-fmipa.trycloudflare.com';
const realFetch = globalThis.fetch;
const calls = [];

function call(url, init) {
    return worker.fetch(new Request(url, init), env);
}

function mockFetch(handler) {
    globalThis.fetch = async (input, init) => {
        calls.push({ input: String(input), init });
        return handler(input, init);
    };
}

try {
    const missing = await call(front + '/login');
    assert.equal(missing.status, 503);
    assert.match(await missing.text(), /belum tersambung/);

    const denied = await call(front + '/__arsip/asal', {
        method: 'POST',
        headers: { 'content-type': 'application/json', authorization: 'Bearer salah' },
        body: JSON.stringify({ url: origin }),
    });
    assert.equal(denied.status, 404);

    const saved = await call(front + '/__arsip/asal', {
        method: 'POST',
        headers: {
            'content-type': 'application/json',
            authorization: 'Bearer ' + env.GATE,
        },
        body: JSON.stringify({ url: origin }),
    });
    assert.equal(saved.status, 200);
    assert.equal(store.get('origin'), origin);

    calls.length = 0;
    mockFetch(() => new Response(
        '<form action="' + origin + '/login">http://127.0.0.1:8765/dasbor</form>',
        {
            status: 200,
            headers: {
                'content-type': 'text/html; charset=utf-8',
                location: origin + '/dasbor',
                'set-cookie': 'laravel_session=abc; Path=/; Domain=perpustakaan-fmipa.trycloudflare.com; HttpOnly',
            },
        },
    ));

    const page = await call(front + '/login', {
        headers: {
            'cf-ray': 'abc',
            'cdn-loop': 'cloudflare',
            'accept-encoding': 'gzip',
        },
    });
    const html = await page.text();
    assert.equal(page.status, 200);
    assert.match(html, /action="https:\/\/arsip\.nama\.workers\.dev\/login"/);
    assert.match(html, /https:\/\/arsip\.nama\.workers\.dev\/dasbor/);
    assert.doesNotMatch(html, /trycloudflare|127\.0\.0\.1/);
    assert.equal(page.headers.get('location'), front + '/dasbor');
    const cookie = typeof page.headers.getSetCookie === 'function'
        ? page.headers.getSetCookie().join('; ')
        : (page.headers.get('set-cookie') || '');
    assert.match(cookie, /Secure/);
    assert.doesNotMatch(cookie, /trycloudflare/);
    assert.equal(calls.length, 1);
    assert.equal(calls[0].init.headers.get('cf-ray'), null);
    assert.equal(calls[0].init.headers.get('cdn-loop'), null);
    assert.equal(calls[0].init.headers.get('accept-encoding'), null);
    assert.equal(calls[0].init.headers.get('x-forwarded-host'), 'arsip.nama.workers.dev');
    assert.equal(calls[0].init.headers.get('x-forwarded-proto'), 'https');
    assert.equal(calls[0].init.headers.get('x-arsip-front'), front);

    calls.length = 0;
    mockFetch(async (_input, init) => {
        assert.equal(init.duplex, 'half');
        assert.equal(await new Response(init.body).text(), 'nama=petugas');
        return new Response('{"ok":true}', {
            status: 200,
            headers: { 'content-type': 'application/json' },
        });
    });
    const posted = await call(front + '/login', {
        method: 'POST',
        headers: { 'content-type': 'application/x-www-form-urlencoded' },
        body: 'nama=petugas',
        duplex: 'half',
    });
    assert.equal(posted.status, 200);
    assert.equal(calls.length, 1);

    calls.length = 0;
    let attempt = 0;
    mockFetch(() => {
        attempt += 1;
        if (attempt < 3) {
            return new Response('down', { status: 530 });
        }
        return new Response('siap', {
            status: 200,
            headers: { 'content-type': 'text/plain' },
        });
    });
    const recovered = await call(front + '/');
    assert.equal(recovered.status, 200);
    assert.equal(await recovered.text(), 'siap');
    assert.equal(attempt, 3);

    calls.length = 0;
    mockFetch(() => new Response('down', { status: 530 }));
    const offline = await call(front + '/');
    assert.equal(offline.status, 502);
    assert.match(await offline.text(), /tidak menjawab/);
    assert.equal(calls.length, 3);

    console.log('qr-gate ok');
} finally {
    globalThis.fetch = realFetch;
}
