<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * SQLite FTS5 index for final-project text search.
 * MySQL keeps its own FULLTEXT index. If FTS5 is missing, search stays on LIKE.
 */
final class ProjectSearchIndex
{
    public const TABLE = 'final__projects_fts';

    /**
     * @var list<string>
     */
    private const TRIGGERS = ['fp_fts_ai', 'fp_fts_ad', 'fp_fts_au'];

    /**
     * Build the index once for an existing database. Later requests only read the flag.
     */
    public static function ensure(): void
    {
        try {
            if (DB::connection()->getDriverName() !== 'sqlite') {
                return;
            }

            if (Cache::get('fp.fts.ready') === true || Cache::get('fp.fts.skip') === true) {
                return;
            }

            if (! Schema::hasTable('final__projects')) {
                return;
            }

            $ok = self::ready() || self::install();
            Cache::put($ok ? 'fp.fts.ready' : 'fp.fts.skip', true, $ok ? 86400 : 3600);
        } catch (Throwable) {
            // Search keeps using LIKE.
        }
    }

    public static function forget(): void
    {
        try {
            Cache::forget('fp.fts.ready');
            Cache::forget('fp.fts.skip');
        } catch (Throwable) {
            // Install migrates the cache table after the database file is created.
        }
    }

    public static function install(?string $connection = null): bool
    {
        $db = DB::connection($connection);

        if ($db->getDriverName() !== 'sqlite') {
            return false;
        }

        try {
            $db->statement(self::createSql());
            self::triggers($db);
            $db->statement('INSERT INTO '.self::TABLE.'('.self::TABLE.") VALUES('rebuild')");

            return true;
        } catch (Throwable) {
            self::drop($connection);

            return false;
        }
    }

    public static function drop(?string $connection = null): void
    {
        $db = DB::connection($connection);

        if ($db->getDriverName() !== 'sqlite') {
            return;
        }

        foreach (self::TRIGGERS as $name) {
            try {
                $db->statement('DROP TRIGGER IF EXISTS '.$name);
            } catch (Throwable) {
                //
            }
        }

        try {
            $db->statement('DROP TABLE IF EXISTS '.self::TABLE);
        } catch (Throwable) {
            //
        }
    }

    public static function ready(?string $connection = null): bool
    {
        $db = DB::connection($connection);

        if ($db->getDriverName() !== 'sqlite') {
            return false;
        }

        try {
            $rows = $db->select(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?",
                [self::TABLE]
            );

            return $rows !== [];
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Run a bulk write without per-row index updates, then rebuild once.
     */
    public static function pause(?string $connection, callable $run): void
    {
        $db = DB::connection($connection);

        if (! self::ready($connection)) {
            $run();

            return;
        }

        foreach (self::TRIGGERS as $name) {
            $db->statement('DROP TRIGGER IF EXISTS '.$name);
        }

        try {
            $run();
        } finally {
            self::triggers($db);

            try {
                $db->statement('INSERT INTO '.self::TABLE.'('.self::TABLE.") VALUES('rebuild')");
            } catch (Throwable) {
                // Search falls back to LIKE until the index can be rebuilt.
            }
        }
    }

    private static function createSql(): string
    {
        return 'CREATE VIRTUAL TABLE IF NOT EXISTS '.self::TABLE.' USING fts5('
            .'nim, student_name, final_project_title, program_study, '
            ."content='final__projects', content_rowid='id', tokenize='unicode61')";
    }

    private static function triggers(Connection $db): void
    {
        $table = self::TABLE;
        $columns = 'rowid, nim, student_name, final_project_title, program_study';
        $values = 'new.id, new.nim, new.student_name, new.final_project_title, new.program_study';
        $old = 'old.id, old.nim, old.student_name, old.final_project_title, old.program_study';

        $db->unprepared(
            "CREATE TRIGGER IF NOT EXISTS fp_fts_ai AFTER INSERT ON final__projects BEGIN
                INSERT INTO {$table}({$columns}) VALUES ({$values});
            END"
        );
        $db->unprepared(
            "CREATE TRIGGER IF NOT EXISTS fp_fts_ad AFTER DELETE ON final__projects BEGIN
                INSERT INTO {$table}({$table}, {$columns}) VALUES ('delete', {$old});
            END"
        );
        $db->unprepared(
            "CREATE TRIGGER IF NOT EXISTS fp_fts_au AFTER UPDATE ON final__projects BEGIN
                INSERT INTO {$table}({$table}, {$columns}) VALUES ('delete', {$old});
                INSERT INTO {$table}({$columns}) VALUES ({$values});
            END"
        );
    }
}
