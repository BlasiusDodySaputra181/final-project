<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryClearance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'phone_number',
        'graduation_year',
        'semester_graduated',
        'membership_registered',
        'final_project_id',
        'membership_id',
    ];

    /**
     * MySQL unsigned BIGINT upper bound, compared as text so PHP int size is not a limit.
     */
    public const SEMESTER_MAX = '18446744073709551615';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'graduation_year' => 'integer',
            'semester_graduated' => 'string',
        ];
    }

    /**
     * Positive integer with no digit cap other than unsigned BIGINT.
     *
     * @return list<string|\Closure>
     */
    public static function semesterRules(): array
    {
        return [
            'required',
            'regex:/^\d+$/',
            function (string $attribute, mixed $value, \Closure $fail): void {
                $digits = self::normalizeSemester($value);

                if ($digits === '') {
                    $fail('Semester lulus harus berupa angka!');

                    return;
                }

                if (
                    strlen($digits) > strlen(self::SEMESTER_MAX)
                    || (strlen($digits) === strlen(self::SEMESTER_MAX) && strcmp($digits, self::SEMESTER_MAX) > 0)
                ) {
                    $fail('Semester lulus melebihi batas angka yang dapat disimpan.');
                }
            },
        ];
    }

    public static function normalizeSemester(mixed $value): string
    {
        return ltrim((string) $value, '0');
    }

    public function finalProject(): BelongsTo
    {
        return $this->belongsTo(FinalProject::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }
}
