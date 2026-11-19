<?php

namespace App\Support;

/**
 * CA bundle for the portable PHP runtime, which ships without a system trust store.
 */
class HttpsCertificate
{
    public static function bundle(): ?string
    {
        $path = base_path('laravel-assets/certs/cacert.pem');

        return is_file($path) ? $path : null;
    }
}
