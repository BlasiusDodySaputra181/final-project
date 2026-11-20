<?php

namespace App\Http\Middleware;

use App\Models\Cloudflare;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phones open the printed QR. That address is the Cloudflare link saved by the officer.
 * The tunnel reaches this computer as 127.0.0.1 and may present its temporary hostname.
 * Generated links, forms, and cookies stay on the saved address instead.
 */
class UseStaticAccessHost
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        URL::forceRootUrl(null);
        URL::forceScheme(null);

        $front = $this->front($request);

        if ($front === null) {
            return $next($request);
        }

        $host = (string) parse_url($front, PHP_URL_HOST);
        $request->headers->set('X-Forwarded-Host', $host);
        $request->headers->set('X-Forwarded-Proto', 'https');
        $request->headers->set('X-Forwarded-Port', '443');
        $request->headers->set('Host', $host);
        $request->headers->remove('X-Forwarded-Prefix');
        $request->server->set('HTTP_HOST', $host);
        $request->server->set('SERVER_NAME', $host);
        $request->server->set('HTTPS', 'on');
        $request->server->set('REQUEST_SCHEME', 'https');
        $request->server->set('SERVER_PORT', '443');

        URL::forceRootUrl($front);
        URL::forceScheme('https');

        return $next($request);
    }

    private function front(Request $request): ?string
    {
        if (! $this->fromTunnel($request)) {
            return null;
        }

        $front = Cloudflare::publicUrl();

        return is_string($front) && $front !== '' ? $front : null;
    }

    /**
     * Cloudflare reaches PHP through the local tunnel. A visitor on the internet
     * cannot change the address by sending these headers to a public port.
     */
    private function fromTunnel(Request $request): bool
    {
        $remote = (string) $request->server->get('REMOTE_ADDR', '');
        $loopback = $remote === '::1' || preg_match('/^127(?:\.\d{1,3}){3}$/', $remote) === 1;

        if (! $loopback) {
            return false;
        }

        foreach (['CF-Connecting-IP', 'CF-Ray', 'CF-Visitor'] as $header) {
            $value = $request->headers->get($header);

            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        $marked = $request->headers->get('X-Arsip-Front');
        $front = Cloudflare::publicUrl();

        return is_string($marked)
            && is_string($front)
            && rtrim(trim($marked), '/') === rtrim($front, '/');
    }
}
