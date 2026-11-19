<?php

namespace App\Setup;

use App\Models\Cloudflare;
use App\Support\DeviceCapacity;
use App\Support\FinalProjectStorage;
use App\Support\LocalRuntime;
use App\Support\LocalStack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * One-time setup for the local server, database, data panel, and public link.
 */
class LocalServer
{
    public function __construct(private PortableDatabase $database) {}

    /**
     * @return array<string, mixed>
     */
    public function page(): array
    {
        $server = LocalStack::snapshot();

        $cloudflare = $this->cloudflareForm();

        return [
            'server' => $server,
            'canCopy' => in_array($server['driver'], ['mysql', 'mariadb'], true),
            'archiveDefault' => FinalProjectStorage::defaultRoot(),
            'usingDefaultArchive' => FinalProjectStorage::isUsingDefault(),
            'archivePath' => FinalProjectStorage::isUsingDefault() ? '' : FinalProjectStorage::configuredRoot(),
            'cloudflareLink' => $cloudflare['link'],
            'capacity' => DeviceCapacity::report(),
        ];
    }

    /**
     * @return array{link: string, has_key: bool}
     */
    public function cloudflareForm(): array
    {
        return [
            'link' => Cloudflare::publicUrl() ?? '',
            'has_key' => Cloudflare::hasToken(),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function saveCloudflare(string $link, string $key, bool $clear): array
    {
        try {
            $ready = Schema::hasTable('cloudflares');
        } catch (Throwable) {
            $ready = false;
        }

        if (! $ready) {
            return [
                'ok' => false,
                'message' => 'Tabel Cloudflare belum ada. Jalankan peladen lokal sekali lagi agar migrasi terpasang.',
            ];
        }

        $row = Cloudflare::query()->orderBy('id')->first();
        $storedLink = $row instanceof Cloudflare
            ? LocalStack::normalizeStaticShareUrl($row->link)
            : null;

        if ($clear) {
            if ($storedLink !== null) {
                return [
                    'ok' => false,
                    'message' => 'Tautan QR sudah tersimpan dan tidak dapat dihapus. Kode yang dicetak tetap memakai tautan itu.',
                ];
            }

            return [
                'ok' => true,
                'message' => 'Belum ada tautan QR yang tersimpan.',
            ];
        }

        $normalized = LocalStack::normalizeStaticShareUrl($link);

        if ($normalized === null) {
            return [
                'ok' => false,
                'message' => $this->temporaryCloudflareLink($link)
                    ? 'Tautan sementara trycloudflare.com tidak dapat disimpan. Tautan itu berubah dan nanti tidak dapat dibuka. Isi satu tautan Worker, misalnya https://arsip.nama.workers.dev.'
                    : 'Tautan harus nama host https, misalnya https://arsip.nama.workers.dev.',
            ];
        }

        if ($storedLink !== null && $normalized !== $storedLink) {
            return [
                'ok' => false,
                'message' => 'Tautan QR sudah tersimpan sejak diatur dan tidak dapat diganti.',
            ];
        }

        $typed = Cloudflare::prepareToken($key);
        $kept = Cloudflare::tunnelToken() ?? '';

        if ($typed !== '' && ! Cloudflare::validToken($typed)) {
            return [
                'ok' => false,
                'message' => 'Tautan Worker tidak tersimpan. Yang dipakai hanya satu tautan https, misalnya https://arsip.nama.workers.dev.',
            ];
        }

        $token = $typed !== '' ? $typed : $kept;

        try {
            if ($row === null) {
                Cloudflare::query()->create([
                    'link' => $normalized,
                    'key' => $token,
                ]);
            } else {
                $row->fill([
                    'link' => $storedLink ?? $normalized,
                    'key' => $token,
                ])->save();
                Cloudflare::query()->whereKeyNot($row->id)->delete();
            }
        } catch (Throwable) {
            return [
                'ok' => false,
                'message' => 'Tautan belum tersimpan di basis data. Jalankan peladen lokal sekali lagi, lalu coba simpan.',
            ];
        }

        $this->forgetTunnelTokenFile();

        return [
            'ok' => true,
            'message' => $storedLink !== null
                ? 'Tautan kode QR tetap sama dan tidak dapat diganti.'
                : 'Tautan QR disimpan. Kode memakai tautan Worker ini selamanya, termasuk setelah ganti jaringan.',
        ];
    }

    private function temporaryCloudflareLink(string $link): bool
    {
        $link = trim($link, " \t\n\r\0\x0B\"'`");

        if ($link === '') {
            return false;
        }

        if (! str_contains($link, '://')) {
            $link = 'https://'.$link;
        }

        $host = strtolower((string) parse_url($link, PHP_URL_HOST));

        return $host === 'trycloudflare.com' || str_ends_with($host, '.trycloudflare.com');
    }

    /**
     * @param  array<string, mixed>  $placement
     * @return array{ok: bool, message: string, status: array<string, mixed>}
     */
    public function install(array $placement): array
    {
        $normalized = PortableDatabase::normalizePlacement($placement);

        return $this->dispatch('install', 'Memasang peladen, basis data, dan folder berkas.', [
            'copy' => (bool) ($placement['copy'] ?? false) && $normalized['database_mode'] !== 'existing',
            'adopt' => (bool) ($placement['adopt'] ?? false),
            'database_mode' => $normalized['database_mode'],
            'database_file' => $normalized['database_file'],
            'archive_mode' => $normalized['archive_mode'],
            'archive_path' => $normalized['archive_path'],
        ]);
    }

    /**
     * @return array{ok: bool, message: string, status: array<string, mixed>}
     */
    public function start(): array
    {
        return $this->dispatch('start', 'Menyalakan peladen di komputer ini.');
    }

    /**
     * @return array{ok: bool, message: string, status: array<string, mixed>}
     */
    public function share(): array
    {
        return $this->dispatch('share', 'Menyiapkan tautan QR yang tersimpan.');
    }

    /**
     * @return array{ok: bool, message: string, status: array<string, mixed>}
     */
    public function stop(): array
    {
        $result = LocalRuntime::run('stop');

        return [
            'ok' => $result['ok'],
            'message' => $result['message'],
            'status' => LocalStack::snapshot(),
        ];
    }

    /**
     * @return array{ok: bool, message: string, status: array<string, mixed>}
     */
    public function uninstall(): array
    {
        LocalStack::writeStatus([
            'phase' => 'removing',
            'message' => 'Menghapus ARSIP-TA secara permanen dari komputer ini.',
        ]);

        $result = LocalRuntime::spawn('uninstall');

        if (! $result['ok']) {
            LocalStack::writeStatus([
                'phase' => 'error',
                'message' => $result['message'],
            ]);
        }

        return [
            'ok' => $result['ok'],
            'message' => $result['ok']
                ? 'Penghapusan permanen sedang berjalan. Isi berkas aplikasi ditimpa, lalu folder, catatan, pintasan, dan entri registri dihapus.'
                : $result['message'],
            'status' => LocalStack::snapshot(),
        ];
    }

    /**
     * @return array{ok: bool, message: string, status: array<string, mixed>}
     */
    public function unshare(): array
    {
        $result = LocalRuntime::run('unshare');

        return [
            'ok' => $result['ok'],
            'message' => $result['message'],
            'status' => LocalStack::snapshot(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function database(Request $request): array
    {
        $tables = [];

        try {
            foreach (Schema::getTableListing(null, false) as $name) {
                $name = (string) $name;

                if ($name !== '' && ! str_starts_with($name, 'sqlite_')) {
                    $tables[] = $name;
                }
            }
        } catch (Throwable $e) {
            report($e);

            return [
                'tables' => [],
                'table' => '',
                'columns' => [],
                'rows' => [],
                'total' => 0,
                'page' => 1,
                'sort' => '',
                'direction' => 'asc',
                'error' => 'Basis data aplikasi belum dapat dibaca.',
                'database_file' => LocalStack::sqlitePath(),
                'driver' => (string) config('database.default'),
            ];
        }

        sort($tables);
        $selected = (string) $request->query('table', '');

        if (! in_array($selected, $tables, true)) {
            $selected = $tables[0] ?? '';
        }

        $columns = $selected === '' ? [] : Schema::getColumnListing($selected);
        $sort = (string) $request->query('sort', '');
        $direction = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, $columns, true)) {
            $sort = in_array('id', $columns, true) ? 'id' : ($columns[0] ?? '');
        }

        $perPage = 40;
        $page = max(1, (int) $request->query('page', 1));
        $total = 0;
        $rows = [];
        $error = '';

        if ($selected !== '') {
            try {
                $query = DB::table($selected);
                $total = (int) $query->count();
                $pages = max(1, (int) ceil($total / $perPage));
                $page = min($page, $pages);
                $listing = DB::table($selected);

                if ($sort !== '') {
                    $listing->orderBy($sort, $direction);
                }

                foreach ($listing->offset(($page - 1) * $perPage)->limit($perPage)->get() as $row) {
                    $line = [];

                    foreach ($columns as $column) {
                        $line[] = $this->present($row->{$column} ?? null, $column, $selected);
                    }

                    $rows[] = $line;
                }
            } catch (Throwable $e) {
                report($e);
                $error = 'Tabel tidak dapat ditampilkan.';
            }
        }

        return [
            'tables' => $tables,
            'table' => $selected,
            'columns' => $columns,
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'sort' => $sort,
            'direction' => $direction,
            'error' => $error,
            'database_file' => LocalStack::sqlitePath(),
            'driver' => (string) config('database.default'),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $request
     * @return array{ok: bool, message: string, status: array<string, mixed>}
     */
    private function dispatch(string $command, string $message, ?array $request = null): array
    {
        if ($request !== null) {
            $directory = LocalStack::runtimePath();

            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents(
                LocalStack::runtimePath('request.json'),
                json_encode([
                    'copy' => (bool) ($request['copy'] ?? false),
                    'adopt' => (bool) ($request['adopt'] ?? false),
                    'database_mode' => (string) ($request['database_mode'] ?? 'keep'),
                    'database_file' => (string) ($request['database_file'] ?? ''),
                    'archive_mode' => (string) ($request['archive_mode'] ?? 'keep'),
                    'archive_path' => (string) ($request['archive_path'] ?? ''),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }

        LocalStack::writeStatus([
            'phase' => 'starting',
            'message' => $message,
        ]);

        $result = LocalRuntime::spawn($command);

        if (! $result['ok']) {
            LocalStack::writeStatus([
                'phase' => 'error',
                'message' => $result['message'],
            ]);
        }

        return [
            'ok' => $result['ok'],
            'message' => $result['ok'] ? $message : $result['message'],
            'status' => LocalStack::snapshot(),
        ];
    }

    private function forgetTunnelTokenFile(): void
    {
        $path = LocalStack::runtimePath('tunnel-token');

        if (is_file($path)) {
            unlink($path);
        }
    }

    private function present(mixed $value, string $column, string $table): string
    {
        if ($value === null) {
            return '';
        }

        $name = strtolower($column);

        if (
            str_contains($name, 'password')
            || in_array($name, ['remember_token', 'token'], true)
            || ($table === 'cloudflares' && $name === 'key')
            || ($table === 'artificial_intelligences' && $name === 'key')
        ) {
            return 'disembunyikan';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (! is_scalar($value)) {
            return '';
        }

        $text = (string) $value;

        return strlen($text) > 160 ? substr($text, 0, 160).'...' : $text;
    }
}
