<?php

namespace Tests\Unit;

use App\Setup\PortableDatabase;
use App\Support\AccessUrl;
use App\Support\FinalProjectStorage;
use App\Support\LocalConsole;
use App\Support\LocalRuntime;
use App\Support\LocalStack;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PDO;
use Tests\TestCase;

class LocalServerTest extends TestCase
{
    private string $statusFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statusFile = tempnam(sys_get_temp_dir(), 'arsip-status');
        LocalStack::$statusPath = $this->statusFile;
    }

    protected function tearDown(): void
    {
        LocalStack::$statusPath = null;

        if (is_file($this->statusFile)) {
            unlink($this->statusFile);
        }

        parent::tearDown();
    }

    public function test_install_launcher_exists_for_each_operating_system(): void
    {
        $this->assertSame(
            'laravel-assets/scripts/runtime.ps1',
            LocalRuntime::launcherFor('Windows')
        );
        $this->assertSame(
            'laravel-assets/scripts/runtime.sh',
            LocalRuntime::launcherFor('Linux')
        );
        $this->assertSame(
            'laravel-assets/scripts/runtime.sh',
            LocalRuntime::launcherFor('Darwin')
        );
        $this->assertSame(
            'laravel-assets/scripts/runtime.sh',
            LocalRuntime::launcherFor('BSD')
        );
        $this->assertSame(
            'laravel-assets/scripts/runtime.sh',
            LocalRuntime::launcherFor('Solaris')
        );

        foreach (['Windows', 'Linux', 'Darwin', 'BSD', 'Solaris'] as $family) {
            $this->assertFileExists(base_path(LocalRuntime::launcherFor($family)));
        }

        foreach (['Pasang-ke-Perangkat.cmd', 'pasang.sh', 'copot.sh', 'mulai.sh', 'hentikan.sh', 'ARSIP-TA.vbs'] as $script) {
            $this->assertFileExists(base_path($script));
        }

        $windows = file_get_contents(base_path('laravel-assets/scripts/runtime.ps1'));
        $this->assertIsString($windows);
        $this->assertStringContainsString('curl.cainfo', $windows);
        $this->assertStringContainsString('laravel-assets\certs\cacert.pem', $windows);

        $unix = file_get_contents(base_path('laravel-assets/scripts/runtime.sh'));
        $this->assertIsString($unix);
        foreach (['confirm_uninstall', 'Actions=Uninstall', 'uninstall shell', 'id.arsip.ta', 'Darwin'] as $marker) {
            $this->assertStringContainsString($marker, $unix);
        }
    }

    public function test_removing_phase_stays_visible_while_uninstall_is_running(): void
    {
        LocalStack::writeStatus([
            'phase' => 'removing',
            'message' => 'Mencopot shortcut, PHP bawaan, dan cloudflared.',
        ]);

        $snapshot = LocalStack::snapshot();

        $this->assertSame('removing', $snapshot['phase']);
        $this->assertSame('Mencopot pemasangan', $snapshot['phase_label']);
    }

    public function test_only_the_installed_computer_can_open_the_console(): void
    {
        $local = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $lan = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.20']);
        $tunnel = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_CF_CONNECTING_IP' => '203.0.113.8',
        ]);

        $this->assertTrue(LocalConsole::allows($local));
        $this->assertFalse(LocalConsole::allows($lan));
        $this->assertFalse(LocalConsole::allows($tunnel));
    }

    public function test_static_share_link_keeps_the_hostname_and_drops_a_quick_tunnel(): void
    {
        $this->assertSame(
            'https://arsip.universitas.ac.id',
            LocalStack::normalizeStaticShareUrl('arsip.universitas.ac.id')
        );
        $this->assertSame(
            'https://arsip.universitas.ac.id',
            LocalStack::normalizeStaticShareUrl('https://arsip.universitas.ac.id/dasbor')
        );
        $this->assertSame(
            'https://arsip.universitas.ac.id',
            LocalStack::normalizeStaticShareUrl('http://arsip.universitas.ac.id')
        );
        $this->assertNull(LocalStack::normalizeStaticShareUrl('https://perpustakaan.trycloudflare.com'));
        $this->assertNull(LocalStack::normalizeStaticShareUrl('javascript:alert(1)'));
    }

    public function test_share_link_is_only_the_saved_hostname(): void
    {
        $this->assertFalse(LocalStack::isPublicShareUrl('https://arsip-ta.trycloudflare.com'));
        $this->assertFalse(LocalStack::isPublicShareUrl('http://192.168.1.20:8765'));
        $this->assertFalse(LocalStack::isPublicShareUrl('https://192.168.1.20'));
        $this->assertFalse(LocalStack::isPublicShareUrl('https://evil.test'));
        $this->assertFalse(LocalStack::isPublicShareUrl('javascript:alert(1)'));
        $this->assertFalse(LocalStack::isPublicShareUrl('https://arsip-ta.trycloudflare.com/admin'));
    }

    public function test_access_link_ignores_a_temporary_tunnel_and_an_ip(): void
    {
        LocalStack::writeStatus([
            'public_url' => 'https://perpustakaan-fmipa.trycloudflare.com',
        ]);

        $request = Request::create('http://127.0.0.1:8765/dasbor', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $this->assertSame('http://127.0.0.1:8765', AccessUrl::login($request));

        LocalStack::writeStatus([
            'public_url' => 'http://192.168.1.20:8765',
        ]);

        $this->assertSame('http://127.0.0.1:8765', AccessUrl::login($request));
    }

    public function test_env_file_points_at_the_sqlite_file_in_the_install_folder(): void
    {
        $updated = PortableDatabase::pointEnvFileAtSqlite(
            <<<'ENV'
            APP_NAME=ARSIP-TA
            DB_CONNECTION=mysql
            DB_HOST=127.0.0.1
            DB_DATABASE=final-project
            ENV,
            'C:\\laragon\\www\\final-project\\database\\database.sqlite'
        );

        $this->assertStringContainsString('DB_CONNECTION=sqlite', $updated);
        $this->assertStringContainsString(
            'DB_DATABASE=C:/laragon/www/final-project/database/database.sqlite',
            $updated
        );
        $this->assertStringContainsString('DB_HOST=127.0.0.1', $updated);
    }

    public function test_catalog_lists_the_single_install_parts(): void
    {
        $ids = array_column(LocalStack::catalog()['components'], 'id');

        $this->assertSame(
            ['server', 'database', 'database-admin', 'tunnel', 'app'],
            $ids
        );
        $this->assertSame(8765, LocalStack::port());
    }

    public function test_install_can_keep_create_or_copy_a_sqlite_file(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'arsip-db-'.bin2hex(random_bytes(4));
        mkdir($directory);
        $source = $directory.DIRECTORY_SEPARATOR.'lama.sqlite';
        $target = $directory.DIRECTORY_SEPARATOR.'database.sqlite';

        try {
            $pdo = new PDO('sqlite:'.$source);
            $pdo->exec('CREATE TABLE sample (id INTEGER PRIMARY KEY)');
            $pdo = null;
            file_put_contents($target, 'berkas-lama');

            $backup = PortableDatabase::installSqliteFile($target, 'existing', $source);

            $this->assertNotNull($backup);
            $this->assertFileExists($backup);
            $this->assertTrue(PortableDatabase::isSqliteFile($target));
            $this->assertFalse(PortableDatabase::isSqliteFile($directory.DIRECTORY_SEPARATOR.'bukan.txt'));

            $replaced = PortableDatabase::installSqliteFile($target, 'new');

            $this->assertNotNull($replaced);
            $this->assertFileExists($replaced);
            $this->assertSame(0, filesize($target));
            $this->assertSame('keep', PortableDatabase::normalizePlacement(['database_mode' => 'lain'])['database_mode']);
            $this->assertNull(PortableDatabase::validatePlacement([
                'database_mode' => 'keep',
                'archive_mode' => 'keep',
            ]));
            $this->assertSame(
                'Pilih berkas basis data SQLite yang sudah ada.',
                PortableDatabase::validatePlacement([
                    'database_mode' => 'existing',
                    'database_file' => $target,
                    'archive_mode' => 'keep',
                ])
            );
        } finally {
            $this->deleteTree($directory);
        }
    }

    public function test_new_database_replaces_a_sqlite_file_that_is_still_open(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'arsip-db-lock-'.bin2hex(random_bytes(4));
        mkdir($directory);
        $target = $directory.DIRECTORY_SEPARATOR.'database.sqlite';
        $pdo = new PDO('sqlite:'.$target);
        $pdo->exec('CREATE TABLE sample (id INTEGER PRIMARY KEY)');
        $pdo->exec('INSERT INTO sample (id) VALUES (1)');
        $pdo->exec('PRAGMA journal_mode = WAL');

        try {
            $backup = PortableDatabase::installSqliteFile($target, 'new');

            $this->assertNotNull($backup);
            $this->assertFileExists($target);
            $check = new PDO('sqlite:'.$target);
            $tables = $check->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'sample'")->fetchAll();
            $this->assertSame([], $tables);
            $check = null;
        } finally {
            $pdo = null;
            $this->deleteTree($directory);
        }
    }

    public function test_install_folder_can_use_an_existing_archive_or_create_one(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'arsip-folder-'.bin2hex(random_bytes(4));
        $parent = $directory.DIRECTORY_SEPARATOR.'induk';
        $archive = $parent.DIRECTORY_SEPARATOR.'ARSIP TUGAS AKHIR';
        $fresh = $directory.DIRECTORY_SEPARATOR.'baru';
        mkdir($archive, 0777, true);
        mkdir($fresh);

        try {
            $existing = FinalProjectStorage::resolveInstallFolder('existing', $archive);
            $this->assertSame(realpath($parent), realpath($existing['root']));

            $contained = FinalProjectStorage::resolveInstallFolder('existing', $parent);
            $this->assertSame(realpath($parent), realpath($contained['root']));

            $created = FinalProjectStorage::resolveInstallFolder('new', $fresh);
            $this->assertDirectoryExists($fresh.DIRECTORY_SEPARATOR.'ARSIP TUGAS AKHIR');
            $this->assertNotSame('', $created['stored']);

            try {
                FinalProjectStorage::resolveInstallFolder('existing', $directory);
                $this->fail('Folder tanpa arsip harus ditolak.');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('ARSIP TUGAS AKHIR', $e->validator->errors()->first());
            }
        } finally {
            $this->deleteTree($directory);
        }
    }

    private function deleteTree(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if (! is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->deleteTree($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
