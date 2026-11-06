<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Progres pemindahan folder arsip saat lokasi berkas diubah.
 * Ditulis ke cache agar halaman pengaturan dapat memantau secara real-time.
 */
class StorageMoveProgress
{
    public const TTL_SECONDS = 600;

    public static function isValidToken(?string $token): bool
    {
        return is_string($token) && preg_match('/^[A-Za-z0-9_-]{16,64}$/', $token) === 1;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function start(string $token, ?int $userId): void
    {
        Cache::put(self::key($token), self::payload([
            'percent' => 0,
            'phase' => 'prepare',
            'label' => 'Menyiapkan pemindahan',
            'status' => 'Memeriksa folder sumber dan tujuan…',
            'current' => 0,
            'total' => 0,
            'file' => null,
            'done' => false,
            'error' => null,
            'user_id' => $userId,
            'result' => null,
        ]), self::TTL_SECONDS);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public static function report(string $token, array $event): void
    {
        $current = Cache::get(self::key($token));

        if (! is_array($current) || ($current['done'] ?? false) === true) {
            return;
        }

        Cache::put(self::key($token), self::payload(array_merge($current, $event, [
            'done' => false,
            'error' => null,
        ])), self::TTL_SECONDS);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public static function finish(string $token, array $result): void
    {
        $current = Cache::get(self::key($token));
        $base = is_array($current) ? $current : [];

        Cache::put(self::key($token), self::payload(array_merge($base, [
            'percent' => 100,
            'phase' => 'done',
            'label' => 'Selesai',
            'status' => 'Pemindahan data selesai.',
            'done' => true,
            'error' => null,
            'result' => $result,
        ])), self::TTL_SECONDS);
    }

    public static function fail(string $token, string $error): void
    {
        $current = Cache::get(self::key($token));
        $base = is_array($current) ? $current : [];

        Cache::put(self::key($token), self::payload(array_merge($base, [
            'phase' => 'error',
            'label' => 'Gagal memindah berkas',
            'status' => $error,
            'done' => true,
            'error' => $error,
        ])), self::TTL_SECONDS);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getForUser(string $token, ?int $userId): ?array
    {
        $state = Cache::get(self::key($token));

        if (! is_array($state)) {
            return null;
        }

        if (($state['user_id'] ?? null) !== $userId) {
            return null;
        }

        unset($state['user_id']);

        return $state;
    }

    public static function reporter(string $token): \Closure
    {
        return static function (array $event) use ($token): void {
            self::report($token, $event);
        };
    }

    public static function waitingPayload(): array
    {
        $payload = self::payload([
            'percent' => 0,
            'phase' => 'wait',
            'label' => 'Menyiapkan pemindahan',
            'status' => 'Menunggu proses dimulai…',
            'done' => false,
            'error' => null,
        ]);
        unset($payload['user_id'], $payload['result']);

        return $payload;
    }

    public static function key(string $token): string
    {
        return 'storage-move:'.$token;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private static function payload(array $state): array
    {
        return [
            'percent' => max(0, min(100, (int) ($state['percent'] ?? 0))),
            'phase' => (string) ($state['phase'] ?? 'prepare'),
            'label' => (string) ($state['label'] ?? 'Menyiapkan pemindahan'),
            'status' => (string) ($state['status'] ?? ''),
            'current' => max(0, (int) ($state['current'] ?? 0)),
            'total' => max(0, (int) ($state['total'] ?? 0)),
            'file' => isset($state['file']) && is_string($state['file']) && $state['file'] !== ''
                ? $state['file']
                : null,
            'done' => (bool) ($state['done'] ?? false),
            'error' => isset($state['error']) && is_string($state['error']) && $state['error'] !== ''
                ? $state['error']
                : null,
            'user_id' => $state['user_id'] ?? null,
            'result' => is_array($state['result'] ?? null) ? $state['result'] : null,
        ];
    }
}
