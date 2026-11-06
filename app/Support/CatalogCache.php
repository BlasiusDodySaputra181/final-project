<?php

namespace App\Support;

use App\Models\FinalProjectType;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cache katalog program studi, jenis tugas akhir, dan kunci statistik dasbor.
 */
final class CatalogCache
{
    public const DASHBOARD_COUNTS = 'catalog.dashboard.counts';

    public const AUTH_HERO_STATS = 'catalog.auth.hero';

    public const STUDY_PROGRAMS = 'catalog.study-programs';

    public const STUDY_PROGRAM_COUNT = 'catalog.study-program-count';

    public const VISITOR_COUNT = 'catalog.visitor-count';

    public const FINAL_PROJECT_TYPES = 'catalog.final-project-types';

    public const SIGNATORY_PREFIX = 'catalog.signatory.';

    public static function studyPrograms(): Collection
    {
        return self::hydrate(StudyProgram::class, Cache::remember(
            self::STUDY_PROGRAMS,
            300,
            static fn (): array => StudyProgram::query()
                ->orderBy('study_program')
                ->get(['id', 'study_program', 'cluster'])
                ->map(static fn (StudyProgram $program): array => $program->attributesToArray())
                ->all()
        ));
    }

    /**
     * Jenjang unik dari kolom level pada final_project_types.
     *
     * @return list<string>
     */
    public static function degreeLevels(): array
    {
        return self::finalProjectTypes()
            ->map(static fn (FinalProjectType $type): string => trim((string) $type->level))
            ->filter(static fn (string $level): bool => $level !== '')
            ->unique()
            ->values()
            ->all();
    }

    public static function finalProjectTypes(): Collection
    {
        return self::hydrate(FinalProjectType::class, Cache::remember(
            self::FINAL_PROJECT_TYPES,
            300,
            static fn (): array => FinalProjectType::query()
                ->orderBy('level')
                ->orderBy('type')
                ->get(['id', 'level', 'type'])
                ->map(static fn (FinalProjectType $type): array => $type->attributesToArray())
                ->all()
        ));
    }

    public static function forgetDashboard(): void
    {
        Cache::forget(self::DASHBOARD_COUNTS);
    }

    public static function forgetStudyPrograms(): void
    {
        Cache::forget(self::STUDY_PROGRAMS);
        Cache::forget(self::STUDY_PROGRAM_COUNT);
    }

    public static function forgetVisitorCount(): void
    {
        Cache::forget(self::VISITOR_COUNT);
    }

    public static function forgetFinalProjectTypes(): void
    {
        Cache::forget(self::FINAL_PROJECT_TYPES);
    }

    /**
     * Akun penandatangan surat untuk peran Petugas atau Koordinator.
     */
    public static function signatory(string $role): ?User
    {
        $cached = Cache::remember(
            self::SIGNATORY_PREFIX.$role,
            300,
            static function () use ($role): ?array {
                $user = User::query()
                    ->where('role', $role)
                    ->orderBy('id')
                    ->first(['id', 'identity_number', 'name', 'role']);

                return $user?->attributesToArray();
            }
        );

        if (! is_array($cached) || $cached === []) {
            return null;
        }

        return User::hydrate([$cached])->first();
    }

    public static function forgetSignatories(): void
    {
        foreach ([User::ROLE_OFFICER, User::ROLE_COORDINATOR] as $role) {
            Cache::forget(self::SIGNATORY_PREFIX.$role);
        }
    }

    /**
     * @param  class-string  $model
     */
    private static function hydrate(string $model, mixed $cached): Collection
    {
        if ($cached instanceof Collection) {
            $cached = $cached->all();
        }

        if (! is_array($cached)) {
            return collect();
        }

        $rows = array_map(
            static fn (mixed $row): array => is_array($row) ? $row : (array) $row,
            $cached
        );

        return $model::hydrate($rows);
    }
}
