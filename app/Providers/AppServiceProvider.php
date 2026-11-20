<?php

namespace App\Providers;

use App\Support\CatalogCache;
use App\Support\HttpsCertificate;
use App\Support\ProjectSearchIndex;
use App\View\BladeState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(
            [
                'admin.finalproject.form',
                'admin.import.form',
                'admin.visitor.form',
                'admin.submission.form',
                'admin.membership.form',
            ],
            function ($view) {
                $view->with('studyPrograms', CatalogCache::studyPrograms());
            }
        );

        View::composer(
            ['admin.navbar.sidebars', 'admin.submission.form', 'admin.import.form'],
            function ($view) {
                $view->with('finalProjectTypes', CatalogCache::finalProjectTypes());
            }
        );

        BladeState::register();

        $this->configureHttpsCertificates();
        $this->configureSqliteFile();
    }

    /**
     * Portable PHP has no system trust store, so Groq calls fail certificate checks.
     */
    private function configureHttpsCertificates(): void
    {
        $bundle = HttpsCertificate::bundle();

        if ($bundle === null) {
            return;
        }

        Http::globalOptions([
            'verify' => $bundle,
        ]);
    }

    /**
     * The SQLite file in the application folder is shared with the standalone server.
     */
    private function configureSqliteFile(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        $path = (string) config('database.connections.sqlite.database');

        if ($path === '' || $path === ':memory:') {
            return;
        }

        // local-server:prepare replaces this file. Opening it here locks the
        // file on Windows, and unlink then fails with "Resource temporarily unavailable".
        if ($this->app->runningConsoleCommand('local-server:prepare')) {
            return;
        }

        try {
            DB::statement('PRAGMA journal_mode = WAL');
            DB::statement('PRAGMA synchronous = NORMAL');
            DB::statement('PRAGMA busy_timeout = 20000');
            DB::statement('PRAGMA temp_store = MEMORY');
            DB::statement('PRAGMA cache_size = -65536');
            DB::statement('PRAGMA wal_autocheckpoint = 4000');
        } catch (\Throwable) {
            return;
        }

        try {
            DB::statement('PRAGMA mmap_size = 268435456');
        } catch (\Throwable) {
            // 32-bit PHP cannot map a large window. The page cache above still applies.
        }

        try {
            if (Cache::add('sqlite.optimize', 1, 86400)) {
                DB::statement('PRAGMA optimize');
            }
        } catch (\Throwable) {
            // The database is not ready yet.
        }

        ProjectSearchIndex::ensure();
    }
}
