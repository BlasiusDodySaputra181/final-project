<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The server panel is only for an officer using the computer where the app is installed.
 * A request through the public link (cloudflared) carries Cloudflare headers
 * even when the connection arrives from 127.0.0.1.
 */
final class LocalConsole
{
    public const DENIED = 'Panel peladen hanya dapat dibuka di komputer tempat aplikasi dipasang.';

    public static function allows(?Request $request = null): bool
    {
        $request = $request ?? request();
        $remote = (string) $request->server->get('REMOTE_ADDR', '');

        if (! self::isLoopback($remote)) {
            return false;
        }

        foreach (['CF-Connecting-IP', 'CF-Ray', 'CF-Visitor'] as $header) {
            $value = $request->headers->get($header);

            if (is_string($value) && trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private static function isLoopback(string $remote): bool
    {
        if ($remote === '::1') {
            return true;
        }

        return (bool) preg_match('/^127(?:\.\d{1,3}){3}$/', $remote);
    }
}
