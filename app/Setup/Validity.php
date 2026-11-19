<?php

namespace App\Setup;

use App\Models\FinalProject;
use Illuminate\Database\Eloquent\Builder;

/**
 * Final-project archive validity (at most 10 years) and the related queries.
 * Called from the FinalProject model.
 */
class Validity
{
    /**
     * Whether the final-project year is still valid (<= 10 years).
     */
    public static function withinPeriod(mixed $year, ?int $currentYear = null): bool
    {
        if ($year === null || $year === '') {
            return false;
        }

        $year = (int) $year;
        $currentYear ??= (int) now()->year;

        if ($year > $currentYear) {
            return false;
        }

        return ($currentYear - $year) <= FinalProject::VALIDITY_YEARS;
    }

    /**
     * Whether the final project is past the 10-year limit (status Tidak Berlaku).
     */
    public static function passedPeriod(mixed $year, ?int $currentYear = null): bool
    {
        if ($year === null || $year === '') {
            return false;
        }

        $year = (int) $year;
        $currentYear ??= (int) now()->year;

        if ($year > $currentYear) {
            return false;
        }

        return ($currentYear - $year) > FinalProject::VALIDITY_YEARS;
    }

    /**
     * Validity status label.
     */
    public static function statusLabel(mixed $year, ?int $currentYear = null): string
    {
        return self::withinPeriod($year, $currentYear)
            ? 'Berlaku'
            : 'Tidak Berlaku';
    }

    /**
     * Remaining-validity text for the index view.
     */
    public static function deadlineLabel(mixed $year, ?int $currentYear = null): string
    {
        if ($year === null || $year === '') {
            return '-';
        }

        $year = (int) $year;
        $currentYear ??= (int) now()->year;
        $remainingYears = FinalProject::VALIDITY_YEARS - ($currentYear - $year);

        if ($remainingYears < 0) {
            return 'Sudah berakhir';
        }

        if ($remainingYears === 0) {
            return 'Tahun terakhir';
        }

        if ($remainingYears === 1) {
            return '1 tahun tersisa';
        }

        return $remainingYears.' tahun tersisa';
    }

    /**
     * Inclusive year bounds that are still valid. Range checks use the year index.
     *
     * @return array{0: int, 1: int}
     */
    public static function yearBounds(?int $currentYear = null): array
    {
        $currentYear ??= (int) now()->year;

        return [$currentYear - FinalProject::VALIDITY_YEARS, $currentYear];
    }

    /**
     * Final projects that are still valid (age <= 10 years).
     */
    public static function valid(Builder $query, ?int $currentYear = null): Builder
    {
        [$minYear, $maxYear] = self::yearBounds($currentYear);

        return $query->whereBetween('final_project_year', [$minYear, $maxYear]);
    }

    /**
     * Final projects that are no longer valid (age > 10 years, or an invalid year).
     */
    public static function expired(Builder $query, ?int $currentYear = null): Builder
    {
        [$minYear, $maxYear] = self::yearBounds($currentYear);

        return $query->where(function (Builder $inner) use ($minYear, $maxYear) {
            $inner->whereNull('final_project_year')
                ->orWhere('final_project_year', '>', $maxYear)
                ->orWhere('final_project_year', '<', $minYear);
        });
    }

    /**
     * Final projects past the 10-year limit, for the destruction module.
     */
    public static function pastPeriod(Builder $query, ?int $currentYear = null): Builder
    {
        [$minYear] = self::yearBounds($currentYear);

        return $query
            ->whereNotNull('final_project_year')
            ->where('final_project_year', '<', $minYear);
    }

    /**
     * Compact relations for one table page: display columns only.
     *
     * @param  list<string>  $nested
     * @return array<string, \Closure>
     */
    public static function eagerCard(string $relation = 'finalProject', array $nested = []): array
    {
        return [
            $relation => function ($query) use ($nested) {
                $query->select([
                    'id',
                    'nim',
                    'student_name',
                    'final_project_title',
                    'program_study',
                    'degree_level',
                    'final_project_year',
                    'submission_date',
                    'study_program_id',
                ])->with(array_merge([
                    'studyProgram:id,study_program,cluster',
                ], $nested));
            },
        ];
    }

    /**
     * Whether this NIM already has a submission record in final__projects.
     */
    public static function existsForNim(?string $nim, bool $lock = false): bool
    {
        $nim = trim((string) $nim);

        if ($nim === '') {
            return false;
        }

        $query = FinalProject::query()->where('nim', $nim);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->exists();
    }
}
