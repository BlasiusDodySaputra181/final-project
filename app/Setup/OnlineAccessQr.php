<?php

namespace App\Setup;

use App\Models\Cloudflare;

/**
 * Link written into the access QR.
 * The address is the Cloudflare hostname saved once on the local server.
 */
final class OnlineAccessQr
{
    public function url(): ?string
    {
        return Cloudflare::publicUrl();
    }
}
