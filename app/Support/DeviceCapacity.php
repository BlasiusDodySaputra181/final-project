<?php

namespace App\Support;

use FilesystemIterator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Remaining storage on the computer where the application is installed,
 * compared separately with the database and uploaded final-project files.
 */
class DeviceCapacity
{
    private const CACHE_KEY = 'device-capacity-v1';

    private const CACHE_SECONDS = 90;

    /**
     * @return array<string, mixed>
     */
    public static function report(): array
    {
        if (app()->runningUnitTests()) {
            return self::measure();
        }

        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, static fn (): array => self::measure());
        } catch (Throwable) {
            return self::measure();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function measure(): array
    {
        $anchor = base_path();
        $totalRaw = @disk_total_space($anchor);
        $freeRaw = @disk_free_space($anchor);
        $readable = is_float($totalRaw) && $totalRaw > 0;
        $total = $readable ? (int) round($totalRaw) : 0;
        $free = $readable && is_float($freeRaw) ? (int) round(min($totalRaw, max(0, $freeRaw))) : 0;
        $database = self::databaseBytes();
        $archive = self::archiveBytes();
        $archiveRoot = self::archiveRoot();
        $deviceVolume = self::volumeLabel($anchor);
        $archiveVolume = self::volumeLabel($archiveRoot);
        $freePercent = self::share($free, $total);
        $databasePercent = self::share($database, $total);
        $archivePercent = self::share($archive, $total);

        return [
            'readable' => $readable,
            'volume' => $deviceVolume,
            'archive_volume' => $archiveVolume,
            'archive_other_volume' => $readable && $archiveVolume !== '' && $archiveVolume !== $deviceVolume,
            'total_bytes' => $total,
            'free_bytes' => $free,
            'database_bytes' => $database,
            'archive_bytes' => $archive,
            'total_label' => self::formatBytes($total),
            'free_label' => self::formatBytes($free),
            'database_label' => self::formatBytes($database),
            'archive_label' => self::formatBytes($archive),
            'free_percent' => $freePercent,
            'database_percent' => $databasePercent,
            'archive_percent' => $archivePercent,
            'free_percent_label' => self::formatPercent($freePercent),
            'database_percent_label' => self::formatPercent($databasePercent),
            'archive_percent_label' => self::formatPercent($archivePercent),
            'free_width' => self::barWidth($freePercent),
            'database_width' => self::barWidth($databasePercent),
            'archive_width' => self::barWidth($archivePercent),
            'free_level' => self::freeLevel($freePercent),
        ];
    }

    public static function share(int $part, int $whole): float
    {
        if ($part <= 0 || $whole <= 0) {
            return 0.0;
        }

        return ($part / $whole) * 100;
    }

    public static function formatPercent(float $percent): string
    {
        if ($percent <= 0) {
            return '0%';
        }

        if ($percent < 0.01) {
            return '< 0,01%';
        }

        $decimals = $percent >= 10 ? 1 : 2;

        return number_format($percent, $decimals, ',', '.').'%';
    }

    public static function formatBytes(int $bytes): string
    {
        $bytes = max(0, $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $exp = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $exp < count($units) - 1) {
            $value /= 1024;
            $exp++;
        }

        $decimals = match (true) {
            $exp === 0, abs($value - round($value)) < 0.001 => 0,
            $value >= 10 => 1,
            default => 2,
        };

        return number_format($value, $decimals, ',', '.').' '.$units[$exp];
    }

    public static function directoryBytes(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $bytes = 0;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $path,
                    FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
                ),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );
        } catch (Throwable) {
            return 0;
        }

        foreach ($iterator as $file) {
            try {
                if ($file->isFile()) {
                    $bytes += max(0, (int) $file->getSize());
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $bytes;
    }

    private static function databaseBytes(): int
    {
        $driver = (string) config('database.default');

        if ($driver === 'sqlite') {
            return self::sqliteBytes((string) config('database.connections.sqlite.database'));
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return 0;
        }

        $name = (string) config('database.connections.'.$driver.'.database');

        if ($name === '') {
            return 0;
        }

        try {
            $row = DB::selectOne(
                'SELECT COALESCE(SUM(data_length + index_length), 0) AS bytes FROM information_schema.TABLES WHERE table_schema = ?',
                [$name]
            );
        } catch (Throwable) {
            return 0;
        }

        return max(0, (int) ($row->bytes ?? 0));
    }

    private static function sqliteBytes(string $path): int
    {
        if ($path === '' || $path === ':memory:' || ! is_file($path)) {
            return 0;
        }

        $bytes = max(0, (int) filesize($path));

        foreach (['-wal', '-shm'] as $suffix) {
            $sidecar = $path.$suffix;

            if (is_file($sidecar)) {
                $bytes += max(0, (int) filesize($sidecar));
            }
        }

        return $bytes;
    }

    private static function archiveBytes(): int
    {
        $root = self::archiveRoot();
        $candidates = [];

        if (self::isArchiveFolder($root)) {
            $candidates[] = $root;
        }

        $candidates[] = $root.DIRECTORY_SEPARATOR.FinalProjectStorage::ARCHIVE_DIRECTORY;
        $candidates[] = $root.DIRECTORY_SEPARATOR.FinalProjectStorage::LEGACY_ARCHIVE_DIRECTORY;

        $bytes = 0;
        $seen = [];

        foreach ($candidates as $dir) {
            $real = realpath($dir);

            if ($real === false || isset($seen[$real]) || ! is_dir($real)) {
                continue;
            }

            foreach (array_keys($seen) as $parent) {
                if (str_starts_with($real.DIRECTORY_SEPARATOR, $parent.DIRECTORY_SEPARATOR)) {
                    continue 2;
                }
            }

            $seen[$real] = true;
            $bytes += self::directoryBytes($real);
        }

        return $bytes;
    }

    private static function archiveRoot(): string
    {
        try {
            return FinalProjectStorage::configuredRoot();
        } catch (Throwable) {
            return storage_path('app/public');
        }
    }

    private static function isArchiveFolder(string $path): bool
    {
        $name = basename(rtrim(str_replace('\\', '/', $path), '/'));

        return in_array($name, [
            FinalProjectStorage::ARCHIVE_DIRECTORY,
            FinalProjectStorage::LEGACY_ARCHIVE_DIRECTORY,
        ], true);
    }

    private static function volumeLabel(string $path): string
    {
        $real = realpath($path) ?: $path;

        if (PHP_OS_FAMILY === 'Windows' && preg_match('/^([A-Za-z]:)/', $real, $match) === 1) {
            return strtoupper($match[1]);
        }

        return $real;
    }

    private static function barWidth(float $percent): float
    {
        if ($percent <= 0) {
            return 0.0;
        }

        if ($percent < 0.8) {
            return 0.8;
        }

        return min(100, $percent);
    }

    private static function freeLevel(float $percent): string
    {
        if ($percent >= 25) {
            return 'ample';
        }

        if ($percent >= 10) {
            return 'watch';
        }

        return 'low';
    }
}
