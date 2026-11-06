<?php

namespace App\Setup;

use App\Models\Setting;
use App\Support\FinalProjectStorage;
use App\Support\StorageMoveProgress;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Nilai pengaturan aplikasi dan halaman lokasi berkas.
 * Model Setting serta SettingController hanya memanggil kelas ini.
 */
class Preference
{
    public static function getValue(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(
            self::cacheKey($key),
            function () use ($key, $default) {
                $row = Setting::query()->where('key', $key)->first();

                return $row?->value ?? $default;
            }
        );
    }

    public static function setValue(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value]
        );

        Cache::forget(self::cacheKey($key));
    }

    public static function isAccessOpen(string $key, string $default = '1'): bool
    {
        return filter_var(self::getValue($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    public static function setAccessOpen(string $key, bool $open): void
    {
        self::setValue($key, $open ? '1' : '0');
    }

    public static function isVisitorAccessOpen(): bool
    {
        return self::isAccessOpen(Setting::KEY_VISITOR_ACCESS_OPEN);
    }

    public static function setVisitorAccessOpen(bool $open): void
    {
        self::setAccessOpen(Setting::KEY_VISITOR_ACCESS_OPEN, $open);
    }

    public static function isBarcodeAccessOpen(): bool
    {
        return self::isAccessOpen(Setting::KEY_BARCODE_ACCESS_OPEN);
    }

    public static function setBarcodeAccessOpen(bool $open): void
    {
        self::setAccessOpen(Setting::KEY_BARCODE_ACCESS_OPEN, $open);
    }

    public static function isSubmissionAccessOpen(): bool
    {
        return self::isAccessOpen(Setting::KEY_SUBMISSION_ACCESS_OPEN);
    }

    public static function setSubmissionAccessOpen(bool $open): void
    {
        self::setAccessOpen(Setting::KEY_SUBMISSION_ACCESS_OPEN, $open);
    }

    /**
     * Identifiers of student accounts whose submission access is closed.
     * New and updated student accounts are added here; only an officer can reopen them.
     *
     * @return list<int>
     */
    public static function closedSubmissionUserIds(): array
    {
        $decoded = json_decode((string) self::getValue(Setting::KEY_SUBMISSION_CLOSED_USER_IDS, '[]'), true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            array_filter($decoded, static fn ($id): bool => is_numeric($id))
        )));
    }

    public static function isUserSubmissionAccessOpen(int $userId): bool
    {
        return ! in_array($userId, self::closedSubmissionUserIds(), true);
    }

    public static function setUserSubmissionAccessOpen(int $userId, bool $open): void
    {
        $ids = array_values(array_filter(
            self::closedSubmissionUserIds(),
            static fn (int $id): bool => $id !== $userId
        ));

        if (! $open) {
            $ids[] = $userId;
        }

        self::setValue(Setting::KEY_SUBMISSION_CLOSED_USER_IDS, json_encode(array_values(array_unique($ids))));
    }

    public static function forgetUserSubmissionAccess(int $userId): void
    {
        self::setUserSubmissionAccessOpen($userId, true);
    }

    /**
     * Identifiers of student accounts whose membership access is closed.
     * New and updated student accounts are added here; only an officer can reopen them.
     *
     * @return list<int>
     */
    public static function closedMembershipUserIds(): array
    {
        $decoded = json_decode((string) self::getValue(Setting::KEY_MEMBERSHIP_CLOSED_USER_IDS, '[]'), true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            array_filter($decoded, static fn ($id): bool => is_numeric($id))
        )));
    }

    public static function isUserMembershipAccessOpen(int $userId): bool
    {
        return ! in_array($userId, self::closedMembershipUserIds(), true);
    }

    public static function setUserMembershipAccessOpen(int $userId, bool $open): void
    {
        $ids = array_values(array_filter(
            self::closedMembershipUserIds(),
            static fn (int $id): bool => $id !== $userId
        ));

        if (! $open) {
            $ids[] = $userId;
        }

        self::setValue(Setting::KEY_MEMBERSHIP_CLOSED_USER_IDS, json_encode(array_values(array_unique($ids))));
    }

    public static function forgetUserMembershipAccess(int $userId): void
    {
        self::setUserMembershipAccessOpen($userId, true);
    }

    /**
     * Root folder for final-project files and approval sheets.
     * Empty means storage/app/public (the application default).
     */
    public static function finalProjectStoragePath(): string
    {
        return trim((string) self::getValue(Setting::KEY_FINAL_PROJECT_STORAGE_PATH, ''));
    }

    public static function setFinalProjectStoragePath(string $path): void
    {
        self::setValue(Setting::KEY_FINAL_PROJECT_STORAGE_PATH, trim($path));
    }

    /**
     * Pengaturan global folder berkas TA dan akses mahasiswa.
     */
    public function page(): View
    {
        $user = auth()->user();
        $customPath = Setting::finalProjectStoragePath();
        $usingDefault = FinalProjectStorage::isUsingDefault();

        return view('admin.setting.index', [
            'canEdit' => $user?->isOfficer() === true,
            'storagePath' => $customPath,
            'defaultPath' => FinalProjectStorage::defaultRoot(),
            'resolvedPath' => FinalProjectStorage::configuredRoot(),
            'usingDefault' => $usingDefault,
            'storageReady' => FinalProjectStorage::isReady(),
            'shortcuts' => FinalProjectStorage::shortcuts(),
            'visitorAccessOpen' => Setting::isVisitorAccessOpen(),
            'submissionAccessOpen' => Setting::isSubmissionAccessOpen(),
            'barcodeAccessOpen' => Setting::isBarcodeAccessOpen(),
        ]);
    }

    /**
     * Simpan lokasi folder penyimpanan berkas TA & lembar persetujuan.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'storage_path' => ['nullable', 'string', 'max:500'],
            'progress_token' => ['nullable', 'string', 'max:64'],
        ], [
            'storage_path.max' => 'Lokasi folder terlalu panjang.',
        ]);

        $token = $validated['progress_token'] ?? null;
        $tracked = StorageMoveProgress::isValidToken($token);
        $userId = $request->user()?->id;

        if ($tracked) {
            StorageMoveProgress::start($token, $userId);
            $this->releaseSessionLock();
        }

        $lock = Cache::lock('final-project-storage-move', 900);

        if (! $lock->get()) {
            $message = 'Pemindahan berkas sedang berjalan. Tunggu hingga selesai, lalu coba lagi.';

            if ($tracked) {
                StorageMoveProgress::fail($token, $message);
            }

            throw ValidationException::withMessages([
                'storage_path' => $message,
            ]);
        }

        try {
            $result = FinalProjectStorage::persistPath(
                $validated['storage_path'] ?? '',
                $tracked ? StorageMoveProgress::reporter($token) : null
            );
        } catch (ValidationException $e) {
            if ($tracked) {
                StorageMoveProgress::fail(
                    $token,
                    $e->validator->errors()->first() ?: 'Gagal memindah berkas.'
                );
            }

            throw $e;
        } catch (\Throwable $e) {
            report($e);

            $message = 'Gagal memindah berkas. Periksa izin folder, lalu coba lagi.';

            if ($tracked) {
                StorageMoveProgress::fail($token, $message);
            }

            throw ValidationException::withMessages([
                'storage_path' => $message,
            ]);
        } finally {
            optional($lock)->release();
        }

        $path = $result['path'];
        $moved = $result['moved'];
        $usingDefault = $path === '';

        $message = $usingDefault
            ? 'Lokasi penyimpanan dikembalikan ke folder default aplikasi.'
            : 'Lokasi penyimpanan berkas tugas akhir berhasil disimpan.';

        if ($moved > 0) {
            $message .= ' Folder ARSIP TUGAS AKHIR beserta '.$moved.' item di dalamnya dipindah dan disusun ke lokasi baru.';
        }

        $payload = [
            'message' => $message,
            'storage_path' => $path,
            'resolved_path' => FinalProjectStorage::configuredRoot(),
            'using_default' => $usingDefault,
            'storage_ready' => FinalProjectStorage::isReady(),
            'moved' => $moved,
        ];

        if ($tracked) {
            StorageMoveProgress::finish($token, $payload);
        }

        return response()->json($payload);
    }

    /**
     * Progres real-time pemindahan folder saat lokasi berkas disimpan.
     */
    public function moveProgress(Request $request): JsonResponse
    {
        $token = $request->query('token');

        if (is_array($token)) {
            $token = null;
        }

        if (! StorageMoveProgress::isValidToken(is_string($token) ? $token : null)) {
            return response()->json([
                'message' => 'Token progres tidak valid.',
            ], 422);
        }

        $state = StorageMoveProgress::getForUser($token, $request->user()?->id);

        if ($state === null) {
            return response()->json(StorageMoveProgress::waitingPayload());
        }

        unset($state['result']);

        return response()->json($state);
    }

    /**
     * Daftar subfolder di server untuk pemilih lokasi.
     */
    public function browse(Request $request): JsonResponse
    {
        $path = $request->query('path');

        if (is_array($path)) {
            $path = '';
        }

        try {
            return response()->json(FinalProjectStorage::browse(is_string($path) ? $path : ''));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Folder tidak dapat dibuka. Periksa izin, lalu coba lagi.',
            ], 422);
        }
    }

    private static function cacheKey(string $key): string
    {
        return 'setting.'.$key;
    }

    /**
     * Lepas kunci sesi agar polling progres tidak menunggu request simpan selesai.
     */
    private function releaseSessionLock(): void
    {
        if (session()->isStarted()) {
            session()->save();
        }

        if (function_exists('session_write_close')) {
            session_write_close();
        }
    }
}
