<?php

namespace App\Models;

use App\Setup\Member;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Membership extends Model
{
    use HasFactory;

    public const GENDER_MALE = 'Laki-laki';

    public const GENDER_FEMALE = 'Perempuan';

    /**
     * @var list<string>
     */
    public const GENDERS = [
        self::GENDER_MALE,
        self::GENDER_FEMALE,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'gender',
        'place_of_birth',
        'date_of_birth',
        'origin_address',
        'current_address',
        'user_id',
        'study_program_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    /**
     * Display date of birth, for example 13 September 2002.
     */
    public function formattedDateOfBirth(): string
    {
        return Member::formattedDateOfBirth($this);
    }

    /**
     * Display place and date of birth, for example Bekasi / 13 September 2002.
     */
    public function formattedPlaceDateOfBirth(): string
    {
        return Member::formattedPlaceDateOfBirth($this);
    }

    /**
     * Letter weekday and date from created_at, for example Senin, 14 September 2026.
     */
    public function formattedLetterDate(): string
    {
        return Member::formattedLetterDate($this);
    }

    public static function existsForUser(?int $userId): bool
    {
        return Member::existsForUser($userId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function libraryClearances(): HasMany
    {
        return $this->hasMany(LibraryClearance::class);
    }

    public function latestLibraryClearance(): HasOne
    {
        return $this->hasOne(LibraryClearance::class)->latestOfMany();
    }

    public function bookGrants(): HasMany
    {
        return $this->hasMany(BookGrant::class);
    }

    /**
     * Phone number from the related library clearance (library_clearances.phone_number).
     */
    public function relatedPhoneNumber(): string
    {
        return Member::relatedPhoneNumber($this);
    }
}
