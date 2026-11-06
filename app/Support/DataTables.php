<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Response DataTables server-side yang dipakai semua modul CRUD.
 *
 * Halaman diambil di database (bukan seluruh tabel). COUNT(*) dilewati
 * jika halaman tidak penuh (data kosong / sedikit / halaman terakhir).
 * Offset besar memakai deferred join agar perangkat tetap ringan.
 */
final class DataTables
{
    public const DEFAULT_LENGTH = 25;

    public const MAX_LENGTH = 200;

    public const EXPORT_MAX_LENGTH = 2000;

    public const MAX_START = 250_000;

    public const DEFERRED_OFFSET = 200;

    /**
     * COUNT besar diingat sampai ada tulis data (touch) atau TTL habis.
     */
    private const COUNT_CACHE_TTL = 180;

    private const PAGE_CACHE_TTL = 90;

    private const REVISION_KEY = 'dt.rev';

    private static ?int $revision = null;

    /**
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     * @param  callable(mixed, int): array<int, mixed>  $mapRow
     * @param  array<string, string|\Closure>  $orderMap  nama kolom DataTables => kolom SQL atau Closure(builder, dir)
     * @param  list<string>  $searchColumns
     * @param  callable(EloquentBuilder|QueryBuilder, string): void|null  $search
     */
    public static function make(
        Request $request,
        EloquentBuilder|QueryBuilder $query,
        callable $mapRow,
        array $orderMap = [],
        array $searchColumns = [],
        ?callable $search = null,
        string $defaultOrderColumn = 'id',
        string $defaultOrderDir = 'desc',
        ?int $recordsTotal = null,
        ?string $keyword = null
    ): JsonResponse {
        $draw = (int) $request->input('draw', 1);
        $start = min(self::MAX_START, max(0, (int) $request->input('start', 0)));
        $length = (int) $request->input('length', self::DEFAULT_LENGTH);
        $isExport = filter_var($request->input('export'), FILTER_VALIDATE_BOOLEAN)
            || (string) $request->input('export') === '1';

        if ($isExport) {
            $length = min(max(1, $length), self::EXPORT_MAX_LENGTH);
        } elseif ($length < 1 || $length > self::MAX_LENGTH) {
            $length = self::DEFAULT_LENGTH;
        }

        $passedTotal = $recordsTotal;
        $keyword ??= trim((string) $request->input('search.value', ''));
        $unfiltered = $query->clone();

        try {
            if ($keyword !== '') {
                if ($search) {
                    $search($query, $keyword);
                } elseif ($searchColumns !== []) {
                    self::applyKeyword($query, $keyword, $searchColumns);
                }
            }

            $filtered = $query->clone();

            $orderDir = strtolower((string) $request->input('order.0.dir', $defaultOrderDir));
            if (! in_array($orderDir, ['asc', 'desc'], true)) {
                $orderDir = $defaultOrderDir;
            }

            $orderIndex = (int) $request->input('order.0.column', -1);
            $orderName = $orderIndex >= 0
                ? trim((string) $request->input('columns.'.$orderIndex.'.name', ''))
                : '';

            $ordered = false;

            if ($orderName !== '' && array_key_exists($orderName, $orderMap)) {
                $ordered = self::applyOrder($query, $orderMap[$orderName], $orderDir);
            }

            if (! $ordered) {
                $query->orderBy(self::qualify($query, $defaultOrderColumn), $defaultOrderDir);
            }

            $tieBreak = self::qualify($query, 'id');

            if ($defaultOrderColumn !== 'id') {
                $query->orderByDesc($tieBreak);
            } elseif ($ordered && $orderName !== 'id') {
                $query->orderByDesc($tieBreak);
            }

            $pageKey = self::pageCacheKey($query, $keyword, $start, $length);

            if ($pageKey !== null) {
                $cached = self::cachedPage($pageKey);

                if ($cached !== null) {
                    return response()->json([
                        'draw' => $draw,
                        'recordsTotal' => $cached['recordsTotal'],
                        'recordsFiltered' => $cached['recordsFiltered'],
                        'data' => $cached['data'],
                    ]);
                }
            }

            $fetch = $length + 1;
            $useDeferred = ! $isExport
                && $start >= self::DEFERRED_OFFSET
                && $query instanceof EloquentBuilder;

            if ($useDeferred) {
                [$rows, $hasMore] = self::deferredPage($query, $start, $length);
            } else {
                $rows = $query->skip($start)->take($fetch)->get();
                $hasMore = $rows->count() > $length;

                if ($hasMore) {
                    $rows = $rows->slice(0, $length)->values();
                }
            }

            $pageCount = $rows->count();

            if ($keyword === '' && $passedTotal === null) {
                if ($hasMore) {
                    $recordsTotal = self::count($unfiltered);
                } else {
                    $recordsTotal = $start + $pageCount;
                }

                $recordsFiltered = $recordsTotal;
            } else {
                $recordsTotal = $passedTotal ?? self::count($unfiltered);
                $recordsFiltered = $hasMore
                    ? self::count($filtered)
                    : ($start + $pageCount);
            }

            $data = [];
            $number = $start;

            foreach ($rows as $row) {
                $mapped = $mapRow($row, ++$number);

                if ($mapped !== []) {
                    $data[] = $mapped;
                }
            }
        } catch (\Throwable $e) {
            report($e);

            return self::errorPayload($draw);
        }

        $payload = [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ];

        if ($pageKey !== null) {
            self::storePage($pageKey, $payload);
        }

        $payload['draw'] = $draw;

        return response()->json($payload);
    }

    /**
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     * @param  list<string>  $columns
     */
    public static function applyKeyword(
        EloquentBuilder|QueryBuilder $query,
        string $keyword,
        array $columns
    ): void {
        $keyword = trim($keyword);

        if ($keyword === '' || $columns === []) {
            return;
        }

        $columns = KeywordSearch::matchColumns($columns, $keyword);

        if ($columns === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $pattern = KeywordSearch::matchPattern($keyword);

        $query->where(function ($builder) use ($columns, $pattern) {
            foreach ($columns as $column) {
                $builder->orWhere($column, 'like', $pattern);
            }
        });
    }

    /**
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     */
    public static function orderByRelated(
        EloquentBuilder|QueryBuilder $query,
        string $relatedTable,
        string $relatedColumn,
        string $foreignKey,
        string $ownerKey,
        string $direction
    ): void {
        $alias = 'ord_'.$relatedTable;
        $base = $query instanceof EloquentBuilder ? $query->getQuery() : $query;
        $joined = false;

        foreach ($base->joins ?? [] as $join) {
            $table = $join->table ?? '';

            if (is_string($table) && str_contains($table, $alias)) {
                $joined = true;
                break;
            }
        }

        if (! $joined) {
            self::qualifySelect($query);
            $query->leftJoin(
                $relatedTable.' as '.$alias,
                $alias.'.'.$ownerKey,
                '=',
                $foreignKey
            );
        }

        $query->orderBy($alias.'.'.$relatedColumn, $direction);
    }

    /**
     * Urutkan lewat relasi satu-ke-banyak tanpa menggandakan baris (ambil id terbesar).
     *
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     */
    public static function orderByLatestRelated(
        EloquentBuilder|QueryBuilder $query,
        string $relatedTable,
        string $relatedColumn,
        string $localKey,
        string $foreignKey,
        string $direction
    ): void {
        $alias = 'ord_'.$relatedTable;
        $latest = $alias.'_latest';

        self::qualifySelect($query);

        $sub = DB::table($relatedTable)
            ->select($foreignKey)
            ->selectRaw('MAX(id) as latest_id')
            ->groupBy($foreignKey);

        $query->leftJoinSub($sub, $latest, function ($join) use ($latest, $foreignKey, $localKey) {
            $join->on($latest.'.'.$foreignKey, '=', $localKey);
        });

        $query->leftJoin($relatedTable.' as '.$alias, $alias.'.id', '=', $latest.'.latest_id');
        $query->orderBy($alias.'.'.$relatedColumn, $direction);
    }

    /**
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     */
    private static function qualify(EloquentBuilder|QueryBuilder $query, string $column): string
    {
        if ($column === '' || str_contains($column, '.') || str_contains($column, '(')) {
            return $column;
        }

        return $query instanceof EloquentBuilder
            ? $query->getModel()->qualifyColumn($column)
            : $column;
    }

    /**
     * SELECT milik tabel utama supaya JOIN urutan tidak bentrok nama kolom.
     *
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     */
    private static function qualifySelect(EloquentBuilder|QueryBuilder $query): void
    {
        if (! $query instanceof EloquentBuilder) {
            return;
        }

        $table = $query->getModel()->getTable();
        $columns = $query->getQuery()->columns;

        if ($columns === null || $columns === ['*'] || $columns === [$table.'.*']) {
            $query->select($table.'.*');

            return;
        }

        $qualified = [];

        foreach ($columns as $column) {
            if (! is_string($column) || str_contains($column, '.') || str_contains($column, '(') || str_contains($column, ' ')) {
                $qualified[] = $column;

                continue;
            }

            $qualified[] = $table.'.'.$column;
        }

        $query->select($qualified);
    }

    /**
     * @param  EloquentBuilder<Model>  $query
     * @return array{0: EloquentCollection<int, Model>, 1: bool}
     */
    private static function deferredPage(EloquentBuilder $query, int $start, int $length): array
    {
        $model = $query->getModel();
        $ids = self::pageIds($query, $start, $length + 1);
        $hasMore = count($ids) > $length;
        $ids = array_slice($ids, 0, $length);

        if ($ids === []) {
            return [$model->newCollection(), false];
        }

        $page = $query->clone();
        $base = $page->getQuery();
        $base->limit = null;
        $base->offset = null;

        self::dropOrderJoins($base);
        $rows = $page->whereIn($model->getQualifiedKeyName(), $ids)->get();
        $order = array_flip($ids);
        $sorted = $rows->sortBy(
            static fn (Model $row): int => $order[$row->getKey()] ?? PHP_INT_MAX
        )->values();

        return [$sorted, $hasMore];
    }

    /**
     * @param  EloquentBuilder<Model>  $query
     * @return list<int|string>
     */
    private static function pageIds(EloquentBuilder $query, int $start, int $take): array
    {
        $model = $query->getModel();
        $idQuery = $query->clone();
        $base = $idQuery->getQuery();
        $base->columns = null;
        $base->bindings['select'] = [];
        $idQuery->setEagerLoads([]);

        return $idQuery
            ->skip($start)
            ->take($take)
            ->pluck($model->getQualifiedKeyName())
            ->all();
    }

    /**
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     */
    private static function count(EloquentBuilder|QueryBuilder $query): int
    {
        $counter = $query instanceof EloquentBuilder
            ? $query->clone()->reorder()->toBase()
            : $query->clone()->reorder();

        $key = 'dt.c.'.self::revision().'.'.md5($counter->toSql().'|'.json_encode($counter->getBindings()));
        $cached = Cache::get($key);

        if (! app()->runningUnitTests() && is_int($cached)) {
            return $cached;
        }

        $count = (int) $counter->getCountForPagination();

        if (! app()->runningUnitTests()) {
            Cache::put($key, $count, self::COUNT_CACHE_TTL);
        }

        return $count;
    }

    /**
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     */
    private static function pageCacheKey(
        EloquentBuilder|QueryBuilder $query,
        string $keyword,
        int $start,
        int $length
    ): ?string {
        if (app()->runningUnitTests() || $length > self::MAX_LENGTH) {
            return null;
        }

        $heavy = $keyword !== ''
            || $start >= self::DEFERRED_OFFSET
            || ($query instanceof EloquentBuilder && $query->getEagerLoads() !== []);

        if (! $heavy) {
            return null;
        }

        $base = $query instanceof EloquentBuilder ? $query->getQuery() : $query;
        $eager = $query instanceof EloquentBuilder ? implode(',', array_keys($query->getEagerLoads())) : '';

        return 'dt.p.'.self::revision().'.'.md5(
            $base->toSql().'|'.json_encode($base->getBindings())."|{$start}|{$length}|{$eager}"
        );
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, data: list<array<int, mixed>>}|null
     */
    private static function cachedPage(string $key): ?array
    {
        try {
            $cached = Cache::get($key);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($cached) || ! isset($cached['data'], $cached['recordsTotal'], $cached['recordsFiltered'])) {
            return null;
        }

        return $cached;
    }

    /**
     * @param  array{recordsTotal: int, recordsFiltered: int, data: list<array<int, mixed>>}  $payload
     */
    private static function storePage(string $key, array $payload): void
    {
        try {
            Cache::put($key, $payload, self::PAGE_CACHE_TTL);
        } catch (\Throwable) {
            //
        }
    }

    /**
     * Naikkan revisi agar hitungan halaman langsung mengikuti simpan/hapus.
     */
    public static function touch(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        self::$revision = self::revision() + 1;
        Cache::put(self::REVISION_KEY, self::$revision, 86400);
    }

    private static function revision(): int
    {
        if (self::$revision !== null) {
            return self::$revision;
        }

        if (app()->runningUnitTests()) {
            return self::$revision = 0;
        }

        return self::$revision = (int) Cache::get(self::REVISION_KEY, 0);
    }

    /**
     * JOIN urutan tidak dipakai lagi setelah id halaman diketahui.
     */
    private static function dropOrderJoins(QueryBuilder $query): void
    {
        $query->orders = null;
        $query->bindings['order'] = [];
        $query->limit = null;
        $query->offset = null;

        foreach ($query->joins ?? [] as $join) {
            $table = $join->table ?? '';

            if (! is_string($table) || ! str_contains($table, 'ord_')) {
                return;
            }
        }

        if ($query->joins === null || $query->joins === []) {
            return;
        }

        $query->joins = null;
        $query->bindings['join'] = [];
    }

    /**
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     */
    private static function applyOrder(
        EloquentBuilder|QueryBuilder $query,
        string|\Closure $column,
        string $direction
    ): bool {
        if ($column instanceof \Closure) {
            $column($query, $direction);

            return true;
        }

        if ($column === '') {
            return false;
        }

        $query->orderBy(self::qualify($query, $column), $direction);

        return true;
    }

    private static function errorPayload(int $draw): JsonResponse
    {
        return response()->json([
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'serverError' => true,
            'message' => 'Gagal memuat data. Coba beberapa saat lagi.',
        ]);
    }
}
