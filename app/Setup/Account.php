<?php

namespace App\Setup;

use App\Models\User;

/**
 * Peran akun dan nomor identitas.
 * Dipanggil dari model User, masuk, dan profil.
 */
class Account
{
    public static function isOfficer(User $user): bool
    {
        return $user->role === User::ROLE_OFFICER;
    }

    public static function isCoordinator(User $user): bool
    {
        return $user->role === User::ROLE_COORDINATOR;
    }

    public static function isStudent(User $user): bool
    {
        return $user->role === User::ROLE_STUDENT;
    }

    public static function isAdmin(User $user): bool
    {
        return self::isOfficer($user) || self::isCoordinator($user);
    }

    /**
     * Format nomor identitas 18 digit (NIP): XXXXXXXX XXXXXX X XXX.
     */
    public static function formattedIdentityNumber(User $user): string
    {
        $identityNumber = preg_replace('/\D+/', '', (string) $user->identity_number);

        if (strlen($identityNumber) === 18) {
            return substr($identityNumber, 0, 8).' '.substr($identityNumber, 8, 6).' '.substr($identityNumber, 14, 1).' '.substr($identityNumber, 15, 3);
        }

        return (string) ($user->identity_number ?: '—');
    }

    /**
     * Nomor identitas tanpa spasi/tanda baca. Null jika bukan NIM/NIP.
     */
    public static function identityDigits(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?: '';

        if (strlen($digits) < 8) {
            return null;
        }

        return $digits;
    }
}
