<?php

namespace App\Setup;

use App\Models\Setting;
use App\Support\FinalProjectStorage;
use App\Support\LocalStack;
use App\Support\ProjectSearchIndex;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Prepares database/database.sqlite in the application folder.
 * The MySQL copy runs once. Laragon settings stay in place
 * unless an officer adopts this file as the application database.
 */
class PortableDatabase
{
    /**
     * @var list<string>
     */
    private const SKIP = [
        'migrations',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
    ];

    /**
     * @return array{ok: bool, message: string, lines: list<string>}
     */
    public function prepare(bool $copy, bool $adopt, bool $usePlacement = false): array
    {
        $lines = [];
        $lock = $this->lock();

        if ($lock === null) {
            return [
                'ok' => false,
                'message' => 'Penyiapan basis data sedang berjalan.',
                'lines' => ['Penyiapan basis data sedang berjalan.'],
            ];
        }

        try {
            set_time_limit(0);
            $placement = self::normalizePlacement($usePlacement ? $this->readPlacementFile() : []);

            if ($placement['database_mode'] === 'existing') {
                $copy = false;
            }

            $path = LocalStack::sqlitePath();
            $backup = self::installSqliteFile($path, $placement['database_mode'], $placement['database_file']);

            if ($backup !== null) {
                $lines[] = 'Cadangan basis data disimpan: '.$backup;
            }

            if ($placement['database_mode'] === 'new') {
                $lines[] = 'Basis data baru dibuat di folder aplikasi.';
            } elseif ($placement['database_mode'] === 'existing') {
                $lines[] = 'Berkas basis data yang dipilih disalin ke folder aplikasi.';
            } else {
                $lines[] = 'Basis data di folder aplikasi tetap dipakai.';
            }

            $sqlite = config('database.connections.sqlite');
            $sqlite['database'] = $path;
            config(['database.connections.portable' => $sqlite]);
            DB::purge('portable');

            Artisan::call('migrate', [
                '--database' => 'portable',
                '--force' => true,
            ]);
            ProjectSearchIndex::forget();
            $lines[] = 'Struktur basis data pada berkas aplikasi siap.';

            DB::connection('portable')->statement('PRAGMA foreign_keys = OFF');
            DB::connection('portable')->statement('PRAGMA journal_mode = WAL');
            DB::connection('portable')->statement('PRAGMA busy_timeout = 5000');
            $this->repairUsersTable();
            $lines[] = 'Kolom akun pada berkas aplikasi sudah sesuai.';

            $driver = (string) config('database.default');
            $sourceRows = 0;

            if ($copy && in_array($driver, ['mysql', 'mariadb'], true)) {
                $copied = $this->copyFrom($driver);
                $sourceRows = $copied['source_users'];
                $lines = array_merge($lines, $copied['lines']);
            } elseif ($placement['database_mode'] === 'existing') {
                $lines[] = 'Isi berkas yang dipilih tidak diganti dengan data MySQL.';
            } elseif (! $copy) {
                $lines[] = 'Data yang sudah ada di berkas aplikasi tidak diganti.';
            }

            if ($placement['archive_mode'] === 'keep') {
                $lines[] = 'Lokasi folder berkas di basis data tidak diubah.';
            } else {
                $folder = FinalProjectStorage::resolveInstallFolder(
                    $placement['archive_mode'],
                    $placement['archive_path']
                );
                $this->storePortableSetting(Setting::KEY_FINAL_PROJECT_STORAGE_PATH, $folder['stored']);
                $lines[] = $folder['message'];
            }

            $portableUsers = Schema::connection('portable')->hasTable('users')
                ? (int) DB::connection('portable')->table('users')->count()
                : 0;

            if ($adopt) {
                $lines[] = $this->adopt($path, $driver, $sourceRows, $portableUsers);
            } else {
                $lines[] = 'Pengaturan aplikasi yang sedang terbuka tidak diubah. Pintasan ARSIP-TA memakai berkas di folder ini.';
            }

            $message = $lines[count($lines) - 1] ?? 'Basis data folder aplikasi siap.';
            LocalStack::writeStatus([
                'phase' => 'database',
                'message' => $message,
            ]);

            return [
                'ok' => true,
                'message' => $message,
                'lines' => $lines,
            ];
        } catch (ValidationException $e) {
            return $this->fail($e->validator->errors()->first() ?: 'Folder berkas tidak dapat dipakai.');
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Basis data folder aplikasi belum siap. '.$this->short($e->getMessage()));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function pointEnvFileAtSqlite(string $contents, string $sqlitePath): string
    {
        $sqlitePath = str_replace('\\', '/', $sqlitePath);
        $lines = preg_split("/\r\n|\n|\r/", $contents) ?: [];
        $foundConnection = false;
        $foundDatabase = false;

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*DB_CONNECTION\s*=/', $line) === 1) {
                $lines[$index] = 'DB_CONNECTION=sqlite';
                $foundConnection = true;
            } elseif (preg_match('/^\s*DB_DATABASE\s*=/', $line) === 1) {
                $lines[$index] = 'DB_DATABASE='.$sqlitePath;
                $foundDatabase = true;
            }
        }

        if (! $foundConnection) {
            $lines[] = 'DB_CONNECTION=sqlite';
        }

        if (! $foundDatabase) {
            $lines[] = 'DB_DATABASE='.$sqlitePath;
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function repairUsersTable(): void
    {
        $schema = Schema::connection('portable');

        if (! $schema->hasTable('users')) {
            return;
        }

        $missingIdentity = ! $schema->hasColumn('users', 'identity_number');
        $missingRole = ! $schema->hasColumn('users', 'role');

        if (! $missingIdentity && ! $missingRole) {
            return;
        }

        $schema->table('users', function (Blueprint $table) use ($missingIdentity, $missingRole) {
            if ($missingIdentity) {
                $table->string('identity_number')->nullable()->index();
            }

            if ($missingRole) {
                $table->string('role')->default('Petugas')->index();
            }
        });
    }

    /**
     * @return array{source_users: int, lines: list<string>}
     */
    /**
     * @param  array<string, mixed>  $input
     * @return array{database_mode: string, database_file: string, archive_mode: string, archive_path: string}
     */
    public static function normalizePlacement(array $input): array
    {
        $databaseMode = (string) ($input['database_mode'] ?? 'keep');
        $archiveMode = (string) ($input['archive_mode'] ?? 'keep');

        if (! in_array($databaseMode, ['keep', 'new', 'existing'], true)) {
            $databaseMode = 'keep';
        }

        if (! in_array($archiveMode, ['keep', 'new', 'existing'], true)) {
            $archiveMode = 'keep';
        }

        return [
            'database_mode' => $databaseMode,
            'database_file' => self::cleanPath($input['database_file'] ?? ''),
            'archive_mode' => $archiveMode,
            'archive_path' => self::cleanPath($input['archive_path'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function validatePlacement(array $input): ?string
    {
        $placement = self::normalizePlacement($input);

        if ($placement['database_mode'] === 'existing' && ! self::isSqliteFile($placement['database_file'])) {
            return 'Pilih berkas basis data SQLite yang sudah ada.';
        }

        if ($placement['archive_mode'] === 'keep') {
            return null;
        }

        try {
            FinalProjectStorage::resolveInstallFolder($placement['archive_mode'], $placement['archive_path']);
        } catch (ValidationException $e) {
            return $e->validator->errors()->first() ?: 'Folder berkas tidak dapat dipakai.';
        }

        return null;
    }

    public static function isSqliteFile(string $path): bool
    {
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return false;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 16);
        fclose($handle);

        return $header === "SQLite format 3\0";
    }

    /**
     * Copy, create, or leave the application SQLite file.
     * Existing mode copies the selected file. New mode empties the application file.
     */
    public static function installSqliteFile(string $target, string $mode, string $source = ''): ?string
    {
        $directory = dirname($target);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Folder database tidak dapat dibuat.');
        }

        if ($mode === 'existing') {
            if (! self::isSqliteFile($source)) {
                throw new RuntimeException('Berkas yang dipilih bukan basis data SQLite.');
            }

            if (self::pathsMatch($source, $target)) {
                self::checkpointSqlite($target);
                self::discardSidecars($target);

                return null;
            }

            self::checkpointSqlite($source);
            $backup = self::backupSqlite($target);
            self::replaceSqlite($target, $source);

            return $backup;
        }

        if ($mode === 'new') {
            $backup = self::backupSqlite($target);
            self::resetSqliteFile($target);

            if (! is_file($target) && ! touch($target) && ! is_file($target)) {
                throw new RuntimeException('Basis data baru tidak dapat dibuat.');
            }

            return $backup;
        }

        if (! is_file($target)) {
            touch($target);
        }

        return null;
    }

    public static function pathsMatch(string $left, string $right): bool
    {
        $leftReal = realpath($left);
        $rightReal = realpath($right);

        if ($leftReal !== false && $rightReal !== false) {
            return strcasecmp($leftReal, $rightReal) === 0;
        }

        $normalize = static fn (string $path): string => strtolower(str_replace('\\', '/', $path));

        return $normalize($left) === $normalize($right);
    }

    /**
     * Download a copy of the database the application is using.
     */
    public function exportSnapshot(): string
    {
        set_time_limit(0);
        $destination = tempnam(sys_get_temp_dir(), 'arsip-db-');

        if ($destination === false) {
            throw new RuntimeException('Berkas ekspor tidak dapat dibuat.');
        }

        $driver = (string) config('database.default');
        $configured = (string) config('database.connections.'.$driver.'.database');

        if ($driver === 'sqlite' && $configured !== '' && $configured !== ':memory:' && is_file($configured)) {
            self::checkpointSqlite($configured);

            if (! copy($configured, $destination)) {
                throw new RuntimeException('Basis data tidak dapat diekspor.');
            }

            return $destination;
        }

        if (is_file($destination)) {
            unlink($destination);
        }

        touch($destination);
        $sqlite = config('database.connections.sqlite');

        if (! is_array($sqlite)) {
            $sqlite = [
                'driver' => 'sqlite',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
        }

        $sqlite['database'] = $destination;
        config(['database.connections.exchange' => $sqlite]);
        DB::purge('exchange');
        Artisan::call('migrate', [
            '--database' => 'exchange',
            '--force' => true,
        ]);
        DB::connection('exchange')->statement('PRAGMA foreign_keys = OFF');

        if (in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            $this->copyFrom($driver, 'exchange');
        }

        DB::disconnect('exchange');

        return $destination;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function importFile(string $source): array
    {
        if (! self::isSqliteFile($source)) {
            return [
                'ok' => false,
                'message' => 'Berkas yang dipilih bukan basis data SQLite.',
            ];
        }

        set_time_limit(0);
        $target = LocalStack::sqlitePath();
        $driver = (string) config('database.default');
        $current = (string) config('database.connections.'.$driver.'.database');

        try {
            if ($driver === 'sqlite' && self::pathsMatch($current, $target)) {
                try {
                    DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');
                } catch (Throwable) {
                    // Keep replacing the file.
                }

                DB::disconnect($driver);
            }

            self::installSqliteFile($target, 'existing', $source);
            $sqlite = config('database.connections.sqlite');
            $sqlite['database'] = $target;
            config(['database.connections.portable' => $sqlite]);
            DB::purge('portable');
            Artisan::call('migrate', [
                '--database' => 'portable',
                '--force' => true,
            ]);
            ProjectSearchIndex::forget();
            DB::connection('portable')->statement('PRAGMA foreign_keys = OFF');
            $this->repairUsersTable();
            Cache::forget('setting.'.Setting::KEY_FINAL_PROJECT_STORAGE_PATH);
            FinalProjectStorage::forgetCachedDisk();

            if ($driver === 'sqlite' && self::pathsMatch($current, $target)) {
                $message = 'Basis data diimpor. Berkas lama disimpan sebagai cadangan di folder basis data. Muat ulang halaman.';
            } else {
                $adopted = $this->adopt($target, $driver, 0, 1);
                $message = str_contains($adopted, 'sekarang memakai')
                    ? 'Basis data diimpor. '.$adopted.' Muat ulang halaman.'
                    : 'Berkas diimpor ke folder aplikasi. '.$adopted;
            }

            return [
                'ok' => true,
                'message' => $message,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'message' => 'Impor basis data gagal. '.$this->short($e->getMessage()),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readPlacementFile(): array
    {
        $path = LocalStack::runtimePath('request.json');

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        @unlink($path);

        return is_array($decoded) ? $decoded : [];
    }

    private static function cleanPath(mixed $path): string
    {
        $path = trim(str_replace("\0", '', (string) $path));

        return strlen($path) > 500 ? '' : $path;
    }

    public static function checkpointSqlite(string $path): void
    {
        if (! is_file($path)) {
            return;
        }

        $pdo = null;

        try {
            $pdo = new PDO('sqlite:'.$path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
        } catch (Throwable) {
            // Keep the main file when a checkpoint is not available.
        } finally {
            $pdo = null;
        }
    }

    public static function discardSidecars(string $path): void
    {
        foreach (['-wal', '-shm'] as $suffix) {
            $sidecar = $path.$suffix;

            if (is_file($sidecar)) {
                self::tryUnlink($sidecar);
            }
        }
    }

    /**
     * Drop every application object when Windows will not delete the open file.
     */
    private static function resetSqliteFile(string $path): void
    {
        self::discardSidecars($path);

        if (! is_file($path) || self::unlinkSqliteFile($path)) {
            return;
        }

        try {
            self::wipeSqlite($path);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Berkas basis data sedang dipakai. Tutup ARSIP-TA, lalu ulangi pemasangan. '.$e->getMessage()
            );
        }
    }

    /**
     * Close this process's handles, then delete the file. Returns false when it is still open.
     */
    private static function unlinkSqliteFile(string $path): bool
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            self::releaseConnections($path);
            self::checkpointSqlite($path);
            self::discardSidecars($path);
            clearstatcache(true, $path);

            if (! is_file($path)) {
                return true;
            }

            if (self::tryUnlink($path)) {
                clearstatcache(true, $path);
                self::discardSidecars($path);

                return true;
            }

            usleep(200000);
        }

        clearstatcache(true, $path);

        return ! is_file($path);
    }

    private static function wipeSqlite(string $path): void
    {
        $pdo = new PDO('sqlite:'.$path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        try {
            $pdo->exec('PRAGMA foreign_keys = OFF');
            $pdo->exec('PRAGMA busy_timeout = 5000');

            try {
                $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            } catch (Throwable) {
                // The open handle can still accept a schema reset.
            }

            $statement = $pdo->query(
                "SELECT type, name, sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%'"
            );
            $objects = $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
            $statement = null;
            $rank = ['trigger' => 0, 'view' => 1, 'index' => 2, 'table' => 3];

            usort($objects, function (array $left, array $right) use ($rank): int {
                $leftVirtual = str_contains(strtoupper((string) ($left['sql'] ?? '')), 'VIRTUAL') ? 0 : 1;
                $rightVirtual = str_contains(strtoupper((string) ($right['sql'] ?? '')), 'VIRTUAL') ? 0 : 1;

                if ($leftVirtual !== $rightVirtual) {
                    return $leftVirtual <=> $rightVirtual;
                }

                return ($rank[$left['type']] ?? 9) <=> ($rank[$right['type']] ?? 9);
            });

            foreach ($objects as $object) {
                $type = (string) ($object['type'] ?? '');
                $name = (string) ($object['name'] ?? '');

                if (! isset($rank[$type]) || $name === '') {
                    continue;
                }

                $quoted = '"'.str_replace('"', '""', $name).'"';

                try {
                    $pdo->exec('DROP '.strtoupper($type).' IF EXISTS '.$quoted);
                } catch (Throwable) {
                    // Reported below if an application table is still present.
                }
            }

            $left = $pdo->query(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND sql IS NOT NULL"
            );
            $remaining = $left ? $left->fetchAll(PDO::FETCH_COLUMN) : [];

            if ($remaining !== []) {
                throw new RuntimeException('Basis data sedang dipakai proses lain.');
            }

            try {
                $pdo->exec('VACUUM');
            } catch (Throwable) {
                // An outside handle can keep the bytes until the next successful replace.
            }
        } finally {
            $pdo = null;
        }
    }

    private static function releaseConnections(string $path): void
    {
        try {
            $names = array_keys((array) config('database.connections', []));
        } catch (Throwable) {
            return;
        }

        foreach ($names as $name) {
            $configured = (string) config('database.connections.'.$name.'.database');

            if ($configured === '' || $configured === ':memory:' || ! self::pathsMatch($configured, $path)) {
                continue;
            }

            try {
                DB::disconnect($name);
                DB::purge($name);
            } catch (Throwable) {
                // The next attempt deletes the file after the handle closes.
            }
        }

        gc_collect_cycles();
    }

    private static function tryUnlink(string $path): bool
    {
        if (! is_file($path)) {
            return true;
        }

        set_error_handler(static function (): bool {
            return true;
        });

        try {
            return unlink($path);
        } catch (Throwable) {
            return false;
        } finally {
            restore_error_handler();
        }
    }

    private static function backupSqlite(string $path): ?string
    {
        if (! is_file($path) || filesize($path) === 0) {
            return null;
        }

        self::checkpointSqlite($path);
        $backup = $path.'.bak-'.date('Ymd-His');

        if (is_file($backup)) {
            $backup .= '-'.substr(bin2hex(random_bytes(3)), 0, 6);
        }

        if (! copy($path, $backup)) {
            throw new RuntimeException('Cadangan basis data tidak dapat dibuat.');
        }

        return $backup;
    }

    private static function replaceSqlite(string $target, string $source): void
    {
        $incoming = $target.'.incoming';

        if (is_file($incoming)) {
            unlink($incoming);
        }

        if (! copy($source, $incoming)) {
            throw new RuntimeException('Berkas basis data tidak dapat disalin.');
        }

        if (is_file($target) && ! self::unlinkSqliteFile($target)) {
            @unlink($incoming);

            throw new RuntimeException('Berkas basis data sedang dipakai. Tutup ARSIP-TA, lalu ulangi pemasangan.');
        }

        if (! rename($incoming, $target)) {
            @unlink($incoming);

            throw new RuntimeException('Berkas basis data tidak dapat dipasang.');
        }

        self::discardSidecars($target);
    }

    private function storePortableSetting(string $key, string $value): void
    {
        if (! Schema::connection('portable')->hasTable('settings')) {
            return;
        }

        $connection = DB::connection('portable');
        $now = now()->toDateTimeString();
        $exists = $connection->table('settings')->where('key', $key)->exists();

        if ($exists) {
            $connection->table('settings')->where('key', $key)->update([
                'value' => $value,
                'updated_at' => $now,
            ]);
        } else {
            $connection->table('settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Cache::forget('setting.'.$key);
        FinalProjectStorage::forgetCachedDisk();
    }

    private function copyFrom(string $driver, string $targetConnection = 'portable'): array
    {
        $result = ['source_users' => 0, 'lines' => []];

        try {
            $sourceNames = Schema::connection($driver)->getTableListing(null, false);
        } catch (Throwable $e) {
            $result['lines'][] = 'Data MySQL tidak disalin: '.$this->short($e->getMessage());

            return $result;
        }

        if (Schema::connection($driver)->hasTable('users')) {
            $result['source_users'] = (int) DB::connection($driver)->table('users')->count();
        }

        $source = DB::connection($driver);
        $target = DB::connection($targetConnection);
        $source->disableQueryLog();
        $target->disableQueryLog();
        $sqliteTarget = $target->getDriverName() === 'sqlite';

        if ($sqliteTarget) {
            $target->statement('PRAGMA synchronous = OFF');
        }

        try {
            $this->copyTables($driver, $targetConnection, $sourceNames, $result);
        } finally {
            if ($sqliteTarget) {
                $target->statement('PRAGMA synchronous = NORMAL');
            }
        }

        if ($result['lines'] === []) {
            $result['lines'][] = 'Tidak ada tabel yang disalin.';
        }

        return $result;
    }

    /**
     * @param  list<string>  $sourceNames
     * @param  array{source_users: int, lines: list<string>}  $result
     */
    private function copyTables(string $driver, string $targetConnection, array $sourceNames, array &$result): void
    {
        $known = array_flip(Schema::connection($targetConnection)->getTableListing(null, false));

        foreach ($sourceNames as $table) {
            $table = (string) $table;

            if ($table === '' || str_starts_with($table, 'sqlite_') || str_contains($table, '_fts') || in_array($table, self::SKIP, true)) {
                continue;
            }

            if (! isset($known[$table])) {
                continue;
            }

            try {
                $count = $this->copyTable($driver, $table, $targetConnection);
                $result['lines'][] = $table.': '.$count.' baris disalin.';
            } catch (Throwable $e) {
                $result['lines'][] = $table.' dilewati: '.$this->short($e->getMessage());
            }
        }
    }

    private function copyTable(string $driver, string $table, string $targetConnection = 'portable'): int
    {
        $sourceColumns = Schema::connection($driver)->getColumnListing($table);
        $targetColumns = Schema::connection($targetConnection)->getColumnListing($table);
        $columns = array_values(array_intersect($sourceColumns, $targetColumns));

        if ($columns === []) {
            return 0;
        }

        $order = in_array('id', $columns, true) ? 'id' : $columns[0];
        $write = function () use ($driver, $targetConnection, $table, $columns, $order): int {
            $target = DB::connection($targetConnection);
            $count = 0;

            $target->transaction(function () use ($target, $driver, $table, $columns, $order, &$count) {
                $target->table($table)->delete();
                $batch = [];

                foreach (DB::connection($driver)->table($table)->orderBy($order)->cursor() as $row) {
                    $item = [];

                    foreach ($columns as $column) {
                        $item[$column] = $row->{$column} ?? null;
                    }

                    $batch[] = $item;
                    $count++;

                    if (count($batch) >= 400) {
                        $target->table($table)->insert($batch);
                        $batch = [];
                    }
                }

                if ($batch !== []) {
                    $target->table($table)->insert($batch);
                }
            });

            return $count;
        };

        if ($table !== 'final__projects') {
            return $write();
        }

        $count = 0;
        ProjectSearchIndex::pause($targetConnection, function () use ($write, &$count) {
            $count = $write();
        });

        return $count;
    }

    private function adopt(string $path, string $driver, int $sourceRows, int $portableUsers): string
    {
        if (in_array($driver, ['mysql', 'mariadb'], true) && $sourceRows > 0 && $portableUsers < 1) {
            return 'Berkas aplikasi belum berisi akun, jadi pengaturan MySQL tidak diganti.';
        }

        $envPath = base_path('.env');

        if (! is_file($envPath) || ! is_readable($envPath) || ! is_writable($envPath)) {
            return 'Berkas pengaturan tidak dapat diubah. Pintasan tetap memakai berkas basis data di folder aplikasi.';
        }

        $backup = base_path('.env.mysql-backup');

        if (! is_file($backup)) {
            copy($envPath, $backup);
        }

        $updated = self::pointEnvFileAtSqlite((string) file_get_contents($envPath), $path);
        file_put_contents($envPath, $updated);

        if (is_file(base_path('bootstrap/cache/config.php'))) {
            Artisan::call('config:clear');
        }

        return 'Aplikasi sekarang memakai basis data di folder ini.';
    }

    /**
     * @return resource|null
     */
    private function lock()
    {
        $path = LocalStack::runtimePath('prepare.lock');
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $handle = fopen($path, 'c');

        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            return null;
        }

        return $handle;
    }

    /**
     * @return array{ok: bool, message: string, lines: list<string>}
     */
    private function fail(string $message): array
    {
        LocalStack::writeStatus([
            'phase' => 'error',
            'message' => $message,
        ]);

        return [
            'ok' => false,
            'message' => $message,
            'lines' => [$message],
        ];
    }

    private function short(string $message): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');

        return strlen($message) > 180 ? substr($message, 0, 180).'...' : $message;
    }
}
