/**
 * One public front door for the library QR.
 * The printed address never changes. The computer posts the current
 * tunnel link to /__arsip/asal, and visitors are forwarded there.
 */
export default {
    async fetch(request, env) {
        const url = new URL(request.url);

        if (url.pathname === '/__arsip/asal') {
            const auth = request.headers.get('Authorization') || '';

            if (request.method !== 'POST' || auth !== 'Bearer ' + env.GATE) {
                return text('Tidak ditemukan.', 404);
            }

            let origin = '';

            try {
                const payload = await request.json();
                origin = String(payload.url || '').trim().toLowerCase();
            } catch {
                origin = '';
            }

            const suffix = '.trycloudflare.com';
            const name = origin.startsWith('https://') && origin.endsWith(suffix)
                ? origin.slice('https://'.length, -suffix.length)
                : '';

            if (name.length < 1 || name.includes('.') || !/^[a-z0-9-]+$/.test(name)) {
                return text('Alamat tidak valid.', 422);
            }

            await env.TARGET.put('origin', origin);

            return new Response(JSON.stringify({ ok: true, origin }), {
                status: 200,
                headers: {
                    'content-type': 'application/json; charset=utf-8',
                    'cache-control': 'no-store',
                },
            });
        }

        const origin = await env.TARGET.get('origin');

        if (!origin) {
            return text('Perpustakaan belum tersambung. Nyalakan komputer server.', 503);
        }

        const dest = new URL(request.url);
        const base = new URL(origin);
        dest.protocol = base.protocol;
        dest.host = base.host;

        const headers = new Headers(request.headers);
        headers.delete('host');
        headers.delete('cf-connecting-ip');
        headers.delete('cf-ray');
        headers.delete('cf-visitor');
        headers.delete('cf-ipcountry');
        headers.set('X-Forwarded-Host', url.host);
        headers.set('X-Forwarded-Proto', 'https');
        headers.set('X-Forwarded-Port', '443');

        const init = {
            method: request.method,
            headers,
            redirect: 'manual',
        };

        if (request.method !== 'GET' && request.method !== 'HEAD') {
            init.body = request.body;
        }

        let response;

        try {
            response = await fetch(dest.toString(), init);
        } catch {
            return retryPage('Komputer perpustakaan tidak menjawab. Nyalakan server dan pastikan internet tersambung.', 502);
        }

        if (response.status === 530 || response.status === 1016) {
            return retryPage('Komputer perpustakaan tidak menjawab. Nyalakan server dan pastikan internet tersambung.', 502);
        }

        const out = new Headers(response.headers);
        out.delete('host');
        out.delete('content-encoding');
        out.delete('content-length');
        const location = out.get('Location');

        if (location) {
            out.set('Location', rewriteLocation(location, origin, url.origin));
        }

        return new Response(response.body, {
            status: response.status,
            statusText: response.statusText,
            headers: out,
        });
    },
};

function retryPage(message, status) {
    const safe = String(message)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    const html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ARSIP-TA</title></head><body><p>'
        + safe
        + '</p><script>window.addEventListener("online",function(){location.reload()});setInterval(function(){if(navigator.onLine)location.reload()},5000);</script></body></html>';

    return new Response(html, {
        status,
        headers: {
            'content-type': 'text/html; charset=utf-8',
            'cache-control': 'no-store',
        },
    });
}

function text(message, status) {
    return new Response(message, {
        status,
        headers: {
            'content-type': 'text/plain; charset=utf-8',
            'cache-control': 'no-store',
        },
    });
}

function rewriteLocation(location, origin, front) {
    if (location.startsWith(origin)) {
        return front + location.slice(origin.length);
    }

    try {
        const target = new URL(location, origin);

        if (target.hostname === '127.0.0.1' || target.hostname === 'localhost') {
            return front + target.pathname + target.search;
        }
    } catch {
        return location;
    }

    return location;
}
