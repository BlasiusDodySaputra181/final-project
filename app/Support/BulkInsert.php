<?php

namespace App\Support;

/**
 * Insert chunk size that stays under the SQL variable limit.
 * SQLite's older limit is 999 variables per statement.
 */
final class BulkInsert
{
    public static function size(int $columns, string $driver): int
    {
        $columns = max(1, $columns);
        $limit = $driver === 'sqlite' ? 900 : 4000;

        return max(1, intdiv($limit, $columns));
    }
}
