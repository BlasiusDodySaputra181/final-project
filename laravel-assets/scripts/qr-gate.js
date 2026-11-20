/**
 * One public front door for the library QR.
 * The printed address never changes. The computer posts the current
 * tunnel link to /__arsip/asal, and visitors stay on the saved address
 * from any network, phone, or other device.
 */
export default {
    async fetch(request, env) {
        const url = new URL(request.url);

        if (url.pathname === '/__arsip/asal') {
            return storeOrigin(request, env);
        }

        const origin = await env.TARGET.get('origin');

        if (!origin) {
            return text('Perpustakaan belum tersambung. Nyalakan komputer server.', 503);
        }

        const dest = new URL(request.url);
        const base = new URL(origin);
        dest.protocol = base.protocol;
        dest.host = base.host;

        const headers = forwardHeaders(request.headers, url);
        const init = {
            method: request.method,
            headers,
            redirect: 'manual',
        };

        if (request.method !== 'GET' && request.method !== 'HEAD') {
            init.body = request.body;
            init.duplex = 'half';
        }

        const response = await pull(dest, init, request.method);

        if (!response || response.status === 1016 || (response.status >= 520 && response.status <= 530)) {
            return retryPage('Komputer perpustakaan tidak menjawab. Nyalakan server dan pastikan internet tersambung.', 502);
        }

        return present(response, origin, url.origin);
    },
};

function forwardHeaders(incoming, url) {
    const headers = new Headers(incoming);

    for (const name of [
        'host',
        'cf-connecting-ip',
        'cf-ray',
        'cf-visitor',
        'cf-ipcountry',
        'cf-ew-via',
        'cf-worker',
        'cdn-loop',
        'accept-encoding',
        'connection',
        'keep-alive',
        'transfer-encoding',
        'te',
        'trailer',
        'upgrade',
        'proxy-connection',
    ]) {
        headers.delete(name);
    }

    for (const name of [...headers.keys()]) {
        if (name.startsWith('cf-')) {
            headers.delete(name);
        }
    }

    headers.set('X-Forwarded-Host', url.host);
    headers.set('X-Forwarded-Proto', 'https');
    headers.set('X-Forwarded-Port', '443');
    headers.set('X-Arsip-Front', url.origin);

    return headers;
}

async function pull(dest, init, method) {
    const attempts = method === 'GET' || method === 'HEAD' ? 3 : 1;
    let last = null;

    for (let attempt = 0; attempt < attempts; attempt++) {
        try {
            const response = await fetch(dest.toString(), init);

            if (response.status !== 530 && response.status !== 1016 && response.status < 520) {
                return response;
            }

            last = response;
        } catch {
            last = null;
        }

        if (attempt + 1 < attempts) {
            await new Promise((resolve) => setTimeout(resolve, 200 * (attempt + 1)));
        }
    }

    return last;
}

async function present(response, origin, front) {
    const out = new Headers(response.headers);
    out.delete('host');
    out.delete('content-encoding');
    out.delete('content-length');

    const location = out.get('Location');

    if (location) {
        out.set('Location', rewriteLocation(location, origin, front));
    }

    rewriteCookies(out, new URL(origin).host);

    const type = (out.get('content-type') || '').toLowerCase();
    const textual = type.startsWith('text/')
        || type.includes('javascript')
        || type.includes('json')
        || type.includes('xml');

    if (!textual) {
        return new Response(response.body, {
            status: response.status,
            statusText: response.statusText,
            headers: out,
        });
    }

    const body = rewriteText(await response.text(), origin, front);

    return new Response(body, {
        status: response.status,
        statusText: response.statusText,
        headers: out,
    });
}

async function storeOrigin(request, env) {
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
    const rewritten = rewriteText(location, origin, front);

    if (rewritten !== location) {
        return rewritten;
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

function rewriteText(text, origin, front) {
    const originHost = new URL(origin).host;
    let next = String(text).split(origin).join(front);
    next = next.split(originHost).join(new URL(front).host);
    next = next.replace(/https?:\/\/(?:127\.0\.0\.1|localhost)(?::\d+)?/gi, front);

    return next;
}

function rewriteCookies(headers, originHost) {
    const cookies = typeof headers.getSetCookie === 'function'
        ? headers.getSetCookie()
        : [];

    if (cookies.length === 0) {
        return;
    }

    headers.delete('set-cookie');
    const pattern = new RegExp('(;\\s*)Domain=' + originHost.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'ig');

    for (const cookie of cookies) {
        let next = cookie.replace(pattern, '');

        if (!/;\s*Secure\b/i.test(next)) {
            next += '; Secure';
        }

        headers.append('set-cookie', next);
    }
}
