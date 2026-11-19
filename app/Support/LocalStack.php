<?php

namespace App\Support;

use App\Models\Cloudflare;
use Throwable;

/**
 * Installation status and catalog stored in laravel-assets.
 */
final class LocalStack
{
    public static ?string $statusPath = null;

    public static ?string $runtimeRoot = null;

    /**
     * @return array<string, mixed>
     */
    public static function catalog(): array
    {
        $path = base_path('laravel-assets/catalog.json');
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $components = is_array($decoded['components'] ?? null) ? $decoded['components'] : [];
        $clean = [];

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $title = trim((string) ($component['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $clean[] = [
                'id' => (string) ($component['id'] ?? ''),
                'title' => $title,
                'replaces' => trim((string) ($component['replaces'] ?? '')),
                'detail' => trim((string) ($component['detail'] ?? '')),
            ];
        }

        return [
            'name' => 'ARSIP-TA',
            'port' => self::port(),
            'components' => $clean,
        ];
    }

    public static function port(): int
    {
        $path = base_path('laravel-assets/catalog.json');
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $port = (int) ($decoded['port'] ?? 8765);

        return $port >= 1024 && $port <= 65535 ? $port : 8765;
    }

    public static function sqlitePath(): string
    {
        return database_path('database.sqlite');
    }

    public static function runtimePath(string $name = ''): string
    {
        $root = self::$runtimeRoot ?? base_path('laravel-assets/runtime');

        return $name === '' ? $root : $root.DIRECTORY_SEPARATOR.$name;
    }

    public static function isPublicShareUrl(?string $url): bool
    {
        $normal = self::normalizeStaticShareUrl($url);
        $stored = self::storedLoginUrl();

        return is_string($normal) && is_string($stored) && $normal === $stored;
    }

    /**
     * Permanent https origin typed by the officer. Quick-tunnel hosts are rejected.
     */
    public static function normalizeStaticShareUrl(?string $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url, " \t\n\r\0\x0B\"'`");

        if ($url === '') {
            return null;
        }

        if (preg_match('#\A[a-z][a-z0-9+.-]*://#i', $url) !== 1) {
            $url = 'https://'.$url;
        }

        if (strlen($url) > 200) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['https', 'http'], true)) {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $port = $parts['port'] ?? null;

        if ($port !== null && (int) $port !== 443) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! self::isPublicHostname($host)) {
            return null;
        }

        return 'https://'.$host;
    }

    public static function publicLoginUrl(): ?string
    {
        return self::storedLoginUrl();
    }

    private static function storedLoginUrl(): ?string
    {
        try {
            return Cloudflare::publicUrl();
        } catch (Throwable) {
            return null;
        }
    }

    private static function isPublicHostname(string $host): bool
    {
        if ($host === '' || strlen($host) > 253 || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        if (preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host) !== 1) {
            return false;
        }

        if ($host === 'trycloudflare.com' || str_ends_with($host, '.trycloudflare.com')) {
            return false;
        }

        foreach (['localhost', 'local', 'internal', 'invalid', 'test', 'example', 'lan', 'home', 'arpa', 'localdomain'] as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    public static function writeStatus(array $patch): void
    {
        $path = self::statusFile();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $current = self::readStatus();

        foreach ($patch as $key => $value) {
            $current[$key] = $value;
        }

        $current['updated_at'] = now()->toIso8601String();
        file_put_contents($path, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, mixed>
     */
    public static function readStatus(): array
    {
        $path = self::statusFile();

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(): array
    {
        $status = self::readStatus();
        $port = self::port();
        $phase = (string) ($status['phase'] ?? 'idle');
        $allowed = [
            'idle', 'starting', 'php', 'tunnel-binary', 'database', 'shortcuts',
            'server', 'sharing', 'removing', 'ready', 'error', 'stopped',
        ];

        if (! in_array($phase, $allowed, true)) {
            $phase = 'idle';
        }

        $stored = self::storedLoginUrl();
        $public = is_string($stored) ? $stored : null;
        $message = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($status['message'] ?? ''))) ?? '');

        if (strlen($message) > 400) {
            $message = substr($message, 0, 400);
        }

        $sqlite = self::sqlitePath();
        $driver = (string) config('database.default');
        $listening = self::portOpen($port);

        return [
            'phase' => $phase,
            'phase_label' => self::phaseLabel($phase, $listening),
            'message' => $message,
            'listening' => $listening,
            'online' => (bool) ($status['online'] ?? false),
            'port' => $port,
            'local_url' => 'http://127.0.0.1:'.$port,
            'lan_urls' => [],
            'public_url' => $public,
            'database_file' => $sqlite,
            'database_ready' => is_file($sqlite),
            'database_bytes' => is_file($sqlite) ? (int) filesize($sqlite) : 0,
            'driver' => $driver,
            'driver_database' => (string) config('database.connections.'.$driver.'.database'),
            'php' => is_string($status['php'] ?? null) ? $status['php'] : '',
            'shortcuts' => (bool) ($status['shortcuts'] ?? false),
            'tunnel_binary' => is_file(self::runtimePath('cloudflared.exe')),
            'components' => self::catalog()['components'],
        ];
    }

    public static function phaseLabel(string $phase, bool $listening): string
    {
        if ($listening && in_array($phase, ['ready', 'sharing', 'server'], true)) {
            return 'Berjalan';
        }

        return match ($phase) {
            'starting', 'php', 'tunnel-binary', 'database', 'shortcuts', 'server', 'sharing' => 'Sedang memasang',
            'removing' => 'Mencopot pemasangan',
            'ready' => 'Siap',
            'stopped' => 'Berhenti',
            'error' => 'Perlu perhatian',
            default => 'Belum berjalan',
        };
    }

    private static function portOpen(int $port): bool
    {
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);

        if (! is_resource($connection)) {
            return false;
        }

        fclose($connection);

        return true;
    }

    private static function statusFile(): string
    {
        return self::$statusPath ?? self::runtimePath('status.json');
    }
}
