<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pencarian server-side: LIKE yang di-escape, FULLTEXT MySQL jika ada,
 * dan EXISTS ke tabel terkait (tanpa orWhereHas yang mahal).
 */
final class KeywordSearch
{
    public const FULLTEXT_MIN = 3;

    private static ?bool $hasFinalProjectFullText = null;

    /**
     * Pola LIKE mengandung kata kunci, wildcard pengguna di-escape.
     */
    public static function likePattern(string $keyword): string
    {
        return '%'.self::escapedKeyword($keyword).'%';
    }

    /**
     * Pola LIKE awalan kata (bisa memakai indeks, tidak memindai seluruh kolom).
     */
    public static function prefixPattern(string $keyword): string
    {
        return self::escapedKeyword($keyword).'%';
    }

    /**
     * Angka murni (NIM, tahun, telepon) dicari dari awal supaya indeks terpakai.
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
     * Pola LIKE: awalan untuk token angka, mengandung untuk teks.
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

        if ($fullTextIndex && self::useFullText($keyword)) {
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
     * Pencarian modul Tugas Akhir / Penyerahan / Cari.
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
     * Cari kolom milik baris + tugas akhir lewat satu subquery id (memakai FK).
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
     * Cocokkan barcode terkait: awalan jika angka, mengandung jika teks.
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
     * Kolom angka/tanggal tidak mungkin cocok dengan kata yang berisi huruf.
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
     * Subquery id yang dimaterialisasi, supaya MATCH memakai indeks fulltext.
     */
    private static function matchedProjectIds(string $keyword, bool $includeDate)
    {
        return DB::query()
            ->fromSub(self::matchingProjectIds($keyword, $includeDate), 'matched')
            ->select('id');
    }

    /**
     * Id tugas akhir yang cocok. MATCH dan LIKE dipisah supaya indeks fulltext terpakai.
     */
    private static function matchingProjectIds(string $keyword, bool $includeDate)
    {
        $like = self::likePattern($keyword);
        $match = DB::table('final__projects')
            ->select('id')
            ->whereRaw(
                'MATCH (`nim`, `student_name`, `final_project_title`, `program_study`) AGAINST (? IN BOOLEAN MODE)',
                [self::booleanQuery($keyword)]
            );

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
        if (mb_strlen(trim($keyword)) < self::FULLTEXT_MIN) {
            return false;
        }

        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        return self::hasFinalProjectFullText();
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
