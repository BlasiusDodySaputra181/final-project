<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Tautan login absolut dari permintaan yang sedang berjalan.
 * Host, port, skema, dan folder instalasi diambil dari request,
 * bukan dari APP_URL yang dikunci di konfigurasi.
 */
final class AccessUrl
{
    public static function login(?Request $request = null): string
    {
        $request = $request ?? request();
        [$scheme, $httpHost, $base] = self::originParts($request);
        $root = rtrim($scheme.'://'.$httpHost.$base, '/');
        $path = self::loginPath();

        return $path === '/' ? $root : $root.$path;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private static function originParts(Request $request): array
    {
        $scheme = $request->getScheme();
        $httpHost = $request->getHttpHost();
        $base = $request->getBaseUrl();

        if (! self::trustsForwardedHeaders($request)) {
            return [$scheme, $httpHost, $base];
        }

        $forwardedScheme = self::forwardedScheme(self::headerValue($request, 'X-Forwarded-Proto'));
        if ($forwardedScheme !== null) {
            $scheme = $forwardedScheme;
        }

        $forwardedHost = self::forwardedHost(self::headerValue($request, 'X-Forwarded-Host'));
        if ($forwardedHost !== null) {
            $httpHost = $forwardedHost;
        }

        if (! self::hostHasPort($httpHost)) {
            $port = self::forwardedPort(self::headerValue($request, 'X-Forwarded-Port'));
            if ($port !== null && ! self::isDefaultPort($scheme, $port)) {
                $httpHost .= ':'.$port;
            }
        }

        $prefix = self::forwardedPrefix(self::headerValue($request, 'X-Forwarded-Prefix'));
        if ($prefix !== null && $base !== $prefix && ! str_starts_with($base, $prefix.'/')) {
            $base = $prefix.$base;
        }

        return [$scheme, $httpHost, $base];
    }

    /**
     * Header terusan hanya dipercaya jika koneksinya datang dari proxy lokal
     * atau jaringan privat. Pengunjung dari internet publik tidak bisa
     * mengganti tautan QR lewat header.
     */
    private static function trustsForwardedHeaders(Request $request): bool
    {
        $peer = (string) $request->server->get('REMOTE_ADDR', '');

        if (filter_var($peer, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var(
            $peer,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    private static function loginPath(): string
    {
        $uri = app('router')->getRoutes()->getByName('login')?->uri() ?? '/';
        $uri = trim($uri);

        if ($uri === '' || $uri === '/') {
            return '/';
        }

        return '/'.ltrim($uri, '/');
    }

    private static function headerValue(Request $request, string $name): ?string
    {
        $value = $request->headers->get($name);

        if (! is_string($value)) {
            return null;
        }

        $value = trim(explode(',', $value, 2)[0]);

        return $value === '' ? null : $value;
    }

    private static function forwardedScheme(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $scheme = strtolower($value);

        return in_array($scheme, ['http', 'https'], true) ? $scheme : null;
    }

    private static function forwardedHost(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $host = strtolower($value);

        if ($host === '' || strlen($host) > 255 || strpbrk($host, "/\\@?# \t") !== false) {
            return null;
        }

        if (str_starts_with($host, '[')) {
            if (! preg_match('/^\[[0-9a-f:]+\](?::\d{1,5})?$/', $host)) {
                return null;
            }
        } elseif (substr_count($host, ':') > 1 || ! preg_match('/^[a-z0-9.-]+(?::\d{1,5})?$/', $host)) {
            return null;
        }

        $port = self::portFromHost($host);

        if ($port !== null && ($port < 1 || $port > 65535)) {
            return null;
        }

        return $host;
    }

    private static function forwardedPort(?string $value): ?int
    {
        if ($value === null || ! preg_match('/^\d{1,5}$/', $value)) {
            return null;
        }

        $port = (int) $value;

        return $port >= 1 && $port <= 65535 ? $port : null;
    }

    private static function forwardedPrefix(?string $value): ?string
    {
        if ($value === null || $value === '/') {
            return null;
        }

        if (
            ! str_starts_with($value, '/')
            || str_contains($value, '..')
            || str_contains($value, '\\')
            || str_contains($value, '//')
            || ! preg_match('#^/[A-Za-z0-9._~!$&\'()*+,;=:@/-]*$#', $value)
        ) {
            return null;
        }

        return rtrim($value, '/');
    }

    private static function hostHasPort(string $host): bool
    {
        if (str_starts_with($host, '[')) {
            return str_contains($host, ']:');
        }

        return str_contains($host, ':');
    }

    private static function portFromHost(string $host): ?int
    {
        if (! self::hostHasPort($host)) {
            return null;
        }

        $port = str_starts_with($host, '[')
            ? substr($host, (int) strrpos($host, ']:') + 2)
            : substr($host, (int) strrpos($host, ':') + 1);

        return preg_match('/^\d{1,5}$/', $port) === 1 ? (int) $port : null;
    }

    private static function isDefaultPort(string $scheme, int $port): bool
    {
        return ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
    }
}
