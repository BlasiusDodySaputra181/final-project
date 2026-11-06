<?php

namespace App\Models;

use App\Setup\Preference;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public const KEY_VISITOR_ACCESS_OPEN = 'visitor_access_open';

    public const KEY_BARCODE_ACCESS_OPEN = 'barcode_access_open';

    public const KEY_SUBMISSION_ACCESS_OPEN = 'submission_access_open';

    public const KEY_SUBMISSION_CLOSED_USER_IDS = 'submission_closed_user_ids';

    public const KEY_MEMBERSHIP_CLOSED_USER_IDS = 'membership_closed_user_ids';

    public const KEY_FINAL_PROJECT_STORAGE_PATH = 'final_project_storage_path';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return Preference::getValue($key, $default);
    }

    public static function setValue(string $key, mixed $value): void
    {
        Preference::setValue($key, $value);
    }

    public static function isAccessOpen(string $key, string $default = '1'): bool
    {
        return Preference::isAccessOpen($key, $default);
    }

    public static function setAccessOpen(string $key, bool $open): void
    {
        Preference::setAccessOpen($key, $open);
    }

    public static function isVisitorAccessOpen(): bool
    {
        return Preference::isVisitorAccessOpen();
    }

    public static function setVisitorAccessOpen(bool $open): void
    {
        Preference::setVisitorAccessOpen($open);
    }

    public static function isBarcodeAccessOpen(): bool
    {
        return Preference::isBarcodeAccessOpen();
    }

    public static function setBarcodeAccessOpen(bool $open): void
    {
        Preference::setBarcodeAccessOpen($open);
    }

    public static function isSubmissionAccessOpen(): bool
    {
        return Preference::isSubmissionAccessOpen();
    }

    public static function setSubmissionAccessOpen(bool $open): void
    {
        Preference::setSubmissionAccessOpen($open);
    }

    /**
     * Identifiers of student accounts whose submission access is closed.
     * New and updated student accounts are added here; only an officer can reopen them.
     *
     * @return list<int>
     */
    public static function closedSubmissionUserIds(): array
    {
        return Preference::closedSubmissionUserIds();
    }

    public static function isUserSubmissionAccessOpen(int $userId): bool
    {
        return Preference::isUserSubmissionAccessOpen($userId);
    }

    public static function setUserSubmissionAccessOpen(int $userId, bool $open): void
    {
        Preference::setUserSubmissionAccessOpen($userId, $open);
    }

    public static function forgetUserSubmissionAccess(int $userId): void
    {
        Preference::forgetUserSubmissionAccess($userId);
    }

    /**
     * Identifiers of student accounts whose membership access is closed.
     * New and updated student accounts are added here; only an officer can reopen them.
     *
     * @return list<int>
     */
    public static function closedMembershipUserIds(): array
    {
        return Preference::closedMembershipUserIds();
    }

    public static function isUserMembershipAccessOpen(int $userId): bool
    {
        return Preference::isUserMembershipAccessOpen($userId);
    }

    public static function setUserMembershipAccessOpen(int $userId, bool $open): void
    {
        Preference::setUserMembershipAccessOpen($userId, $open);
    }

    public static function forgetUserMembershipAccess(int $userId): void
    {
        Preference::forgetUserMembershipAccess($userId);
    }

    /**
     * Root folder for final-project files and approval sheets.
     * Empty means storage/app/public (the application default).
     */
    public static function finalProjectStoragePath(): string
    {
        return Preference::finalProjectStoragePath();
    }

    public static function setFinalProjectStoragePath(string $path): void
    {
        Preference::setFinalProjectStoragePath($path);
    }
}
