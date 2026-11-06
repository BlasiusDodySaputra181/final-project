<?php

namespace App\Setup;

use App\Models\FinalProject;
use Illuminate\Database\Eloquent\Builder;

/**
 * Masa berlaku arsip tugas akhir (maksimal 10 tahun) dan kueri terkait.
 * Dipanggil dari model FinalProject.
 */
class Validity
{
    /**
     * Apakah tahun tugas akhir masih dalam masa berlaku (<= 10 tahun).
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
     * Apakah tugas akhir sudah melewati masa berlaku 10 tahun (status Tidak Berlaku).
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
     * Label status masa berlaku.
     */
    public static function statusLabel(mixed $year, ?int $currentYear = null): string
    {
        return self::withinPeriod($year, $currentYear)
            ? 'Berlaku'
            : 'Tidak Berlaku';
    }

    /**
     * Teks sisa masa berlaku untuk tampilan index.
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
     * Batas tahun inklusif yang masih berlaku. Perbandingan rentang memakai indeks tahun.
     *
     * @return array{0: int, 1: int}
     */
    public static function yearBounds(?int $currentYear = null): array
    {
        $currentYear ??= (int) now()->year;

        return [$currentYear - FinalProject::VALIDITY_YEARS, $currentYear];
    }

    /**
     * Tugas akhir yang masih berlaku (usia <= 10 tahun).
     */
    public static function valid(Builder $query, ?int $currentYear = null): Builder
    {
        [$minYear, $maxYear] = self::yearBounds($currentYear);

        return $query->whereBetween('final_project_year', [$minYear, $maxYear]);
    }

    /**
     * Tugas akhir yang sudah tidak berlaku (usia > 10 tahun / tahun tidak valid).
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
     * Tugas akhir yang telah melewati masa berlaku 10 tahun (untuk modul pemusnahan).
     */
    public static function pastPeriod(Builder $query, ?int $currentYear = null): Builder
    {
        [$minYear] = self::yearBounds($currentYear);

        return $query
            ->whereNotNull('final_project_year')
            ->where('final_project_year', '<', $minYear);
    }

    /**
     * Relasi ringkas untuk satu halaman tabel: kolom tampilan saja.
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
     * Apakah NIM sudah punya data penyerahan (bukti) di final__projects.
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
