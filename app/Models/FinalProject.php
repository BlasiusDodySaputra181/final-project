<?php

namespace App\Models;

use App\Setup\Validity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FinalProject extends Model
{
    use HasFactory;

    /**
     * Masa berlaku arsip tugas akhir (tahun).
     */
    public const VALIDITY_YEARS = 10;

    /**
     * Rentang kolom YEAR MySQL (1901–2155).
     */
    public const YEAR_MIN = 1901;

    public const YEAR_MAX = 2155;

    /**
     * Kolom yang dipakai baris tabel. Menghindari SELECT * saat halaman memuat banyak baris.
     *
     * @var list<string>
     */
    public const LISTING_COLUMNS = [
        'id',
        'nim',
        'student_name',
        'final_project_title',
        'program_study',
        'degree_level',
        'final_project_year',
        'submission_date',
        'final_project_file',
        'approval_sheet',
        'final_project_type_id',
        'study_program_id',
        'created_at',
        'updated_at',
    ];

    /**
     * Nama tabel dipertahankan dari migrasi lama (double underscore).
     *
     * @var string
     */
    protected $table = 'final__projects';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nim',
        'student_name',
        'final_project_title',
        'program_study',
        'degree_level',
        'final_project_year',
        'submission_date',
        'final_project_file',
        'approval_sheet',
        'final_project_type_id',
        'study_program_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'final_project_year' => 'integer',
            'submission_date' => 'date',
        ];
    }

    /**
     * Apakah tahun tugas akhir masih dalam masa berlaku (<= 10 tahun).
     */
    public static function isWithinValidityPeriod(mixed $year, ?int $currentYear = null): bool
    {
        return Validity::withinPeriod($year, $currentYear);
    }

    /**
     * Apakah tugas akhir sudah melewati masa berlaku 10 tahun (status Tidak Berlaku).
     */
    public static function hasPassedValidityPeriod(mixed $year, ?int $currentYear = null): bool
    {
        return Validity::passedPeriod($year, $currentYear);
    }

    /**
     * Label status masa berlaku.
     */
    public static function validityStatusLabel(mixed $year, ?int $currentYear = null): string
    {
        return Validity::statusLabel($year, $currentYear);
    }

    /**
     * Teks sisa masa berlaku untuk tampilan index.
     */
    public static function validityDeadlineLabel(mixed $year, ?int $currentYear = null): string
    {
        return Validity::deadlineLabel($year, $currentYear);
    }

    public function isValid(): bool
    {
        return Validity::withinPeriod($this->final_project_year);
    }

    /**
     * Batas tahun inklusif yang masih berlaku. Perbandingan rentang memakai indeks tahun.
     *
     * @return array{0: int, 1: int}
     */
    public static function validityYearBounds(?int $currentYear = null): array
    {
        return Validity::yearBounds($currentYear);
    }

    /**
     * Tugas akhir yang masih berlaku (usia <= 10 tahun).
     */
    public function scopeValid(Builder $query, ?int $currentYear = null): Builder
    {
        return Validity::valid($query, $currentYear);
    }

    /**
     * Tugas akhir yang sudah tidak berlaku (usia > 10 tahun / tahun tidak valid).
     */
    public function scopeExpired(Builder $query, ?int $currentYear = null): Builder
    {
        return Validity::expired($query, $currentYear);
    }

    /**
     * Tugas akhir yang telah melewati masa berlaku 10 tahun (untuk modul pemusnahan).
     */
    public function scopePastValidityPeriod(Builder $query, ?int $currentYear = null): Builder
    {
        return Validity::pastPeriod($query, $currentYear);
    }

    /**
     * Relasi ringkas untuk satu halaman tabel: kolom tampilan saja.
     *
     * @param  list<string>  $nested
     * @return array<string, \Closure>
     */
    public static function eagerCard(string $relation = 'finalProject', array $nested = []): array
    {
        return Validity::eagerCard($relation, $nested);
    }

    public function finalProjectType(): BelongsTo
    {
        return $this->belongsTo(FinalProjectType::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function bookGrants(): HasMany
    {
        return $this->hasMany(BookGrant::class);
    }

    public function libraryClearances(): HasMany
    {
        return $this->hasMany(LibraryClearance::class);
    }

    public function destructions(): HasMany
    {
        return $this->hasMany(Destruction::class);
    }

    public function archives(): HasMany
    {
        return $this->hasMany(Archive::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(Barcode::class);
    }

    public function latestBarcode(): HasOne
    {
        return $this->hasOne(Barcode::class)->latestOfMany();
    }

    public function latestLibraryClearance(): HasOne
    {
        return $this->hasOne(LibraryClearance::class)->latestOfMany();
    }

    public function latestArchive(): HasOne
    {
        return $this->hasOne(Archive::class)->latestOfMany();
    }

    /**
     * Apakah NIM sudah punya data penyerahan (bukti) di final__projects.
     */
    public static function existsForNim(?string $nim, bool $lock = false): bool
    {
        return Validity::existsForNim($nim, $lock);
    }
}
