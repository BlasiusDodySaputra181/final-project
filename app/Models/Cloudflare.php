<?php

namespace App\Models;

use App\Support\LocalStack;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Cloudflare extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'link',
        'key',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'key',
    ];

    public static function publicUrl(): ?string
    {
        $link = self::currentValue('link');

        return LocalStack::normalizeStaticShareUrl(is_string($link) ? $link : null);
    }

    public static function tunnelToken(): ?string
    {
        $key = self::currentValue('key');

        if (! is_string($key)) {
            return null;
        }

        $token = self::prepareToken($key);

        return self::validToken($token) ? $token : null;
    }

    /**
     * Keep the tunnel key from a pasted install command, quotes, or the raw token.
     */
    public static function prepareToken(string $value): string
    {
        $trimmed = trim($value);
        $trimmed = trim($trimmed, "\"'`");

        if (preg_match('/(?:--token(?:=|\s+)|install\s+)([A-Za-z0-9+\/=_-]{40,})/i', $trimmed, $match) === 1) {
            return $match[1];
        }

        $compact = preg_replace('/\s+/', '', $trimmed) ?? '';
        $compact = trim($compact, "\"'`");

        if (self::credentials($compact) !== null) {
            return $compact;
        }

        if (preg_match('/ey[A-Za-z0-9+\/=_-]{40,}/', $compact, $match) === 1 && self::credentials($match[0]) !== null) {
            return $match[0];
        }

        return $compact;
    }

    /**
     * Account, tunnel id, and secret inside a Cloudflare connector token.
     *
     * @return array{account: string, tunnel: string, secret: string}|null
     */
    public static function credentials(?string $token = null): ?array
    {
        if ($token === null) {
            $token = self::tunnelToken();
        }

        if (! is_string($token) || ! self::validToken($token)) {
            return null;
        }

        $decoded = base64_decode($token, true);

        if (! is_string($decoded) || $decoded === '') {
            return null;
        }

        $json = json_decode($decoded, true);

        if (! is_array($json)) {
            return null;
        }

        $account = $json['a'] ?? null;
        $tunnel = $json['t'] ?? null;
        $secret = $json['s'] ?? null;

        if (! is_string($account) || ! is_string($tunnel) || ! is_string($secret)) {
            return null;
        }

        $account = trim($account);
        $tunnel = strtolower(trim($tunnel));
        $secret = trim($secret);

        if (preg_match('/\A[A-Za-z0-9]{8,64}\z/', $account) !== 1) {
            return null;
        }

        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/', $tunnel) !== 1) {
            return null;
        }

        if ($secret === '' || strlen($secret) > 512 || preg_match('/\A[A-Za-z0-9+\/=]+\z/', $secret) !== 1) {
            return null;
        }

        return [
            'account' => $account,
            'tunnel' => $tunnel,
            'secret' => $secret,
        ];
    }

    public static function hasToken(): bool
    {
        return self::tunnelToken() !== null;
    }

    public static function validToken(string $token): bool
    {
        $token = trim($token);
        $length = strlen($token);

        return $length >= 40
            && $length <= 4096
            && preg_match('/\A[A-Za-z0-9+\/=_-]+\z/', $token) === 1;
    }

    private static function currentValue(string $column): mixed
    {
        try {
            if (! Schema::hasTable('cloudflares')) {
                return null;
            }

            return static::query()->orderBy('id')->value($column);
        } catch (Throwable) {
            return null;
        }
    }
}
