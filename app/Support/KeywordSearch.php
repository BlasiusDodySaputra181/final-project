<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Server-side search: escaped LIKE, MySQL FULLTEXT when available,
 * and EXISTS on related tables (avoids a costly orWhereHas).
 */
final class KeywordSearch
{
    public const FULLTEXT_MIN = 3;

    private static ?bool $hasFinalProjectFullText = null;

    /**
     * @var array<string, bool>
     */
    private static array $sqliteFts = [];

    /**
     * Contains LIKE pattern, with user wildcards escaped.
     */
    public static function likePattern(string $keyword): string
    {
        return '%'.self::escapedKeyword($keyword).'%';
    }

    /**
     * Prefix LIKE pattern (can use an index instead of scanning the whole column).
     */
    public static function prefixPattern(string $keyword): string
    {
        return self::escapedKeyword($keyword).'%';
    }

    /**
     * Pure numbers (NIM, year, phone) match from the start so an index is used.
     */
    public static function isCodeToken(string $keyword): bool
    {
        return preg_match('/^\d{2,}$/', trim($keyword)) === 1;
    }

    public static function isYearToken(string $keyword): bool
    {
        return preg_match('/^(19|20|21)\d{2}$/', trim($keyword)) === 1;
    }

    /**
     * LIKE pattern: prefix for numeric tokens, contains for text.
     */
    public static function matchPattern(string $keyword): string
    {
        return self::isCodeToken($keyword)
            ? self::prefixPattern($keyword)
            : self::likePattern($keyword);
    }

    private static function escapedKeyword(string $keyword): string
    {
        return addcslashes(trim($keyword), '%_\\');
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     * @param  list<string>  $columns
     */
    public static function apply(
        EloquentBuilder|QueryBuilder $query,
        string $keyword,
        array $columns,
        ?string $fullTextIndex = null
    ): void {
        $keyword = trim($keyword);

        if ($keyword === '' || $columns === []) {
            return;
        }

        if ($fullTextIndex && self::mysqlFullText($keyword)) {
            $quoted = implode(', ', array_map(
                static fn (string $column): string => self::quoteColumn($column),
                $columns
            ));

            $query->whereRaw(
                'MATCH ('.$quoted.') AGAINST (? IN BOOLEAN MODE)',
                [self::booleanQuery($keyword)]
            );

            return;
        }

        $columns = self::matchColumns($columns, $keyword);

        if ($columns === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        DataTables::applyKeyword($query, $keyword, $columns);
    }

    /**
     * Search used by the final-project, submission, and lookup modules.
     *
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function finalProjects(EloquentBuilder|QueryBuilder $query, string $keyword): void
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            return;
        }

        $like = self::likePattern($keyword);
        $columns = [
            'nim',
            'student_name',
            'final_project_title',
            'program_study',
            'degree_level',
            'final_project_year',
            'submission_date',
        ];

        if (self::isCodeToken($keyword)) {
            $prefix = self::prefixPattern($keyword);

            $query->where(function ($builder) use ($prefix, $keyword) {
                $builder->where('nim', 'like', $prefix);

                if (self::isYearToken($keyword)) {
                    $builder->orWhere('final_project_year', (int) $keyword);
                }
            });

            return;
        }

        if (self::useFullText($keyword)) {
            $query->whereIn(self::projectKey($query), self::matchedProjectIds($keyword, true));

            return;
        }

        $columns = self::matchColumns($columns, $keyword);

        if ($columns === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where(function ($builder) use ($like, $columns) {
            foreach ($columns as $column) {
                $builder->orWhere($column, 'like', $like);
            }
        });
    }

    /**
     * Search the row's columns and the final project via one id subquery (uses the FK).
     *
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<string>  $ownColumns
     */
    public static function ownAndFinalProject(
        EloquentBuilder $query,
        string $keyword,
        array $ownColumns,
        string $foreignTable,
        string $foreignKey = 'final_project_id'
    ): void {
        $keyword = trim($keyword);

        if ($keyword === '') {
            return;
        }

        $like = self::matchPattern($keyword);
        $qualifiedFk = $foreignTable.'.'.$foreignKey;

        $ownColumns = self::matchColumns($ownColumns, $keyword);

        $query->where(function ($builder) use ($keyword, $like, $ownColumns, $qualifiedFk) {
            foreach ($ownColumns as $column) {
                $builder->orWhere($column, 'like', $like);
            }

            if (self::isCodeToken($keyword)) {
                $prefix = self::prefixPattern($keyword);
                $builder->orWhereIn($qualifiedFk, function ($sub) use ($prefix, $keyword) {
                    $sub->select('id')
                        ->from('final__projects')
                        ->where(function ($fp) use ($prefix, $keyword) {
                            $fp->where('nim', 'like', $prefix);

                            if (self::isYearToken($keyword)) {
                                $fp->orWhere('final_project_year', (int) $keyword);
                            }
                        });
                });

                return;
            }

            if (self::useFullText($keyword)) {
                $builder->orWhereIn($qualifiedFk, self::matchedProjectIds($keyword, false));

                return;
            }

            $builder->orWhereIn($qualifiedFk, function ($sub) use ($like) {
                $sub->select('id')
                    ->from('final__projects')
                    ->where(function ($fp) use ($like) {
                        foreach (['nim', 'student_name', 'final_project_title', 'program_study', 'degree_level'] as $column) {
                            $fp->orWhere($column, 'like', $like);
                        }
                    });
            });
        });
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     * @param  list<string>  $columns
     */
    public static function orExists(
        EloquentBuilder|QueryBuilder $query,
        string $table,
        string $localColumn,
        string $foreignColumn,
        array $columns,
        string $keyword
    ): void {
        $keyword = trim($keyword);

        if ($keyword === '' || $columns === []) {
            return;
        }

        $columns = self::matchColumns($columns, $keyword);

        if ($columns === []) {
            return;
        }

        $like = self::matchPattern($keyword);

        $query->orWhereExists(function ($sub) use ($table, $localColumn, $foreignColumn, $columns, $like) {
            $sub->selectRaw('1')
                ->from($table)
                ->whereColumn($localColumn, $foreignColumn)
                ->where(function ($inner) use ($table, $columns, $like) {
                    foreach ($columns as $column) {
                        $inner->orWhere($table.'.'.$column, 'like', $like);
                    }
                });
        });
    }

    /**
     * Match a related barcode: prefix when numeric, contains when text.
     *
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function orBarcode(
        EloquentBuilder|QueryBuilder $query,
        string $keyword,
        string $projectKey = 'final__projects.id'
    ): void {
        $keyword = trim($keyword);

        if ($keyword === '') {
            return;
        }

        $pattern = self::matchPattern($keyword);

        $query->orWhereExists(function ($sub) use ($pattern, $projectKey) {
            $sub->selectRaw('1')
                ->from('barcodes')
                ->whereColumn('barcodes.final_project_id', $projectKey)
                ->where('barcodes.barcode', 'like', $pattern);
        });
    }

    /**
     * Numeric and date columns cannot match a keyword that contains letters.
     *
     * @param  list<string>  $columns
     * @return list<string>
     */
    public static function matchColumns(array $columns, string $keyword): array
    {
        if (preg_match('/\p{L}/u', $keyword) !== 1) {
            return array_values($columns);
        }

        $skip = [
            'final_project_year' => true,
            'book_year' => true,
            'graduation_year' => true,
            'semester_graduated' => true,
            'date_of_birth' => true,
            'submission_date' => true,
            'phone_number' => true,
            'created_at' => true,
            'updated_at' => true,
        ];

        return array_values(array_filter(
            $columns,
            static function (string $column) use ($skip): bool {
                $name = str_contains($column, '.') ? substr(strrchr($column, '.'), 1) : $column;

                return ! isset($skip[$name]);
            }
        ));
    }

    /**
     * Materialized id subquery so MATCH can use the fulltext index.
     */
    private static function matchedProjectIds(string $keyword, bool $includeDate)
    {
        return DB::query()
            ->fromSub(self::matchingProjectIds($keyword, $includeDate), 'matched')
            ->select('id');
    }

    /**
     * Matching final-project ids. MATCH and LIKE are split so the fulltext index is used.
     */
    private static function matchingProjectIds(string $keyword, bool $includeDate)
    {
        $like = self::likePattern($keyword);
        $match = self::fullTextIds($keyword);

        $union = $match->union(
            DB::table('final__projects')->select('id')->where('degree_level', 'like', $like)
        );

        if (preg_match('/\p{L}/u', $keyword) === 1) {
            return $union;
        }

        $union = $union->union(
            DB::table('final__projects')->select('id')->where('final_project_year', 'like', $like)
        );

        if ($includeDate) {
            $union = $union->union(
                DB::table('final__projects')->select('id')->where('submission_date', 'like', $like)
            );
        }

        return $union;
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    private static function projectKey(EloquentBuilder|QueryBuilder $query): string
    {
        return $query instanceof EloquentBuilder
            ? $query->getModel()->getQualifiedKeyName()
            : 'final__projects.id';
    }

    public static function booleanQuery(string $keyword): string
    {
        $tokens = preg_split('/\s+/u', trim($keyword)) ?: [];
        $parts = [];

        foreach ($tokens as $token) {
            $token = trim($token, "+-<>~*\"'()");

            if ($token === '') {
                continue;
            }

            $parts[] = $token.'*';
        }

        return $parts === [] ? $keyword : implode(' ', $parts);
    }

    public static function useFullText(string $keyword): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return self::ftsQuery($keyword) !== '' && self::hasSqliteFts();
        }

        return self::mysqlFullText($keyword);
    }

    /**
     * FTS5 query. Tokens are quoted so user input cannot change the query syntax.
     */
    public static function ftsQuery(string $keyword): string
    {
        if (mb_strlen(trim($keyword)) < self::FULLTEXT_MIN) {
            return '';
        }

        $tokens = preg_split('/\s+/u', trim($keyword)) ?: [];
        $parts = [];

        foreach ($tokens as $token) {
            $token = preg_replace('/[^\p{L}\p{N}]+/u', '', $token) ?? '';

            if (mb_strlen($token) < 2) {
                continue;
            }

            $parts[] = '"'.$token.'"*';
        }

        return implode(' ', $parts);
    }

    private static function mysqlFullText(string $keyword): bool
    {
        if (mb_strlen(trim($keyword)) < self::FULLTEXT_MIN) {
            return false;
        }

        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        return self::hasFinalProjectFullText();
    }

    private static function fullTextIds(string $keyword)
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return DB::table(ProjectSearchIndex::TABLE)
                ->selectRaw('rowid as id')
                ->whereRaw(ProjectSearchIndex::TABLE.' MATCH ?', [self::ftsQuery($keyword)]);
        }

        return DB::table('final__projects')
            ->select('id')
            ->whereRaw(
                'MATCH (`nim`, `student_name`, `final_project_title`, `program_study`) AGAINST (? IN BOOLEAN MODE)',
                [self::booleanQuery($keyword)]
            );
    }

    private static function hasSqliteFts(): bool
    {
        $name = DB::connection()->getName();

        if (array_key_exists($name, self::$sqliteFts)) {
            return self::$sqliteFts[$name];
        }

        return self::$sqliteFts[$name] = ProjectSearchIndex::ready();
    }

    private static function hasFinalProjectFullText(): bool
    {
        if (self::$hasFinalProjectFullText !== null) {
            return self::$hasFinalProjectFullText;
        }

        $cached = Cache::get('fp.fulltext');

        if (is_bool($cached)) {
            return self::$hasFinalProjectFullText = $cached;
        }

        try {
            if (! Schema::hasTable('final__projects')) {
                return self::$hasFinalProjectFullText = false;
            }

            $indexes = DB::select('SHOW INDEX FROM `final__projects` WHERE `Key_name` = ?', ['fp_search_fulltext']);
            self::$hasFinalProjectFullText = $indexes !== [];
            Cache::put('fp.fulltext', self::$hasFinalProjectFullText, 3600);

            return self::$hasFinalProjectFullText;
        } catch (\Throwable) {
            return self::$hasFinalProjectFullText = false;
        }
    }

    private static function quoteColumn(string $column): string
    {
        $column = str_replace('`', '', $column);

        if (str_contains($column, '.')) {
            [$table, $name] = explode('.', $column, 2);

            return '`'.$table.'`.`'.$name.'`';
        }

        return '`'.$column.'`';
    }
}
