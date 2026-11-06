<?php

use App\Models\User;
use App\Support\AccessUrl;
use App\Support\RoleSession;

if (! function_exists('asset_version')) {
    /**
     * Asset URL with filemtime query string for cache busting.
     */
    function asset_version(string $path): string
    {
        static $versions = [];

        if (! isset($versions[$path])) {
            $file = public_path($path);
            $versions[$path] = is_file($file) ? (string) filemtime($file) : '0';
        }

        return asset($path).'?v='.$versions[$path];
    }
}

if (! function_exists('access_login_url')) {
    /**
     * Absolute sign-in URL for this request's host, port, and install folder.
     */
    function access_login_url(): string
    {
        return AccessUrl::login();
    }
}

if (! function_exists('format_count_label')) {
    /**
     * Format angka ringkas: K=1.000, M=1.000.000, B=1.000.000.000, G=1.000.000.000.000.
     * Giga (G) adalah kelipatan berikutnya setelah miliar (B). Contoh: 10K, 10M, 10B, 10G.
     */
    function format_count_label(int $count): string
    {
        $sign = $count < 0 ? '-' : '';
        $abs = abs($count);

        if ($abs < 1000) {
            return (string) $count;
        }

        $suffixes = ['', 'K', 'M', 'B', 'G'];
        $exp = min(4, intdiv((int) log10($abs), 3));
        $value = $abs / (1000 ** $exp);
        $rounded = round($value, $value >= 10 ? 0 : 1);

        if ($rounded >= 1000 && $exp < 4) {
            $exp++;
            $rounded = round($abs / (1000 ** $exp), 1);
        }

        $text = $rounded == (int) $rounded
            ? (string) (int) $rounded
            : rtrim(rtrim(sprintf('%.1f', $rounded), '0'), '.');

        return $sign.$text.$suffixes[$exp];
    }
}

if (! function_exists('role_sessions')) {
    /**
     * Sesi peran yang sedang aktif di peramban ini.
     *
     * @return list<array{guard: string, role: string, user: User, home: string, current: bool}>
     */
    function role_sessions(): array
    {
        return RoleSession::snapshot();
    }
}
