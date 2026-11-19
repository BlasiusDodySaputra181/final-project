<?php

namespace App\Setup;

use App\Models\Membership;

/**
 * Membership-letter display and a check for accounts that are already registered.
 * Called from the Membership model.
 */
class Member
{
    /**
     * Birth-date display, for example 13 September 2002.
     */
    public static function formattedDateOfBirth(Membership $membership): string
    {
        $date = $membership->date_of_birth;

        if ($date === null) {
            return '';
        }

        $formattedDate = $date->copy()->locale('id')->translatedFormat('j F Y');

        return $formattedDate === '-' ? '' : $formattedDate;
    }

    /**
     * Place and date of birth, for example Bekasi / 13 September 2002.
     */
    public static function formattedPlaceDateOfBirth(Membership $membership): string
    {
        $place = trim((string) $membership->place_of_birth);
        $formattedDate = self::formattedDateOfBirth($membership);

        if ($place !== '' && $formattedDate !== '') {
            return $place.' / '.$formattedDate;
        }

        return $place !== '' ? $place : $formattedDate;
    }

    /**
     * Letter weekday and date from created_at, for example Senin, 14 September 2026.
     */
    public static function formattedLetterDate(Membership $membership): string
    {
        $date = $membership->created_at;

        if ($date === null) {
            return '';
        }

        $formattedDate = $date->copy()->locale('id')->translatedFormat('l, j F Y');

        return $formattedDate === '-' ? '' : $formattedDate;
    }

    public static function existsForUser(?int $userId): bool
    {
        $userId = (int) $userId;

        if ($userId <= 0) {
            return false;
        }

        return Membership::query()->where('user_id', $userId)->exists();
    }

    /**
     * Phone number from the related library clearance (library_clearances.phone_number).
     */
    public static function relatedPhoneNumber(Membership $membership): string
    {
        if ($membership->relationLoaded('latestLibraryClearance')) {
            return trim((string) ($membership->latestLibraryClearance?->phone_number ?? ''));
        }

        $clearances = $membership->relationLoaded('libraryClearances')
            ? $membership->libraryClearances
            : $membership->libraryClearances()->orderByDesc('id')->limit(8)->get(['id', 'membership_id', 'phone_number']);

        foreach ($clearances->sortByDesc('id') as $clearance) {
            $phone = trim((string) $clearance->phone_number);

            if ($phone !== '') {
                return $phone;
            }
        }

        return '';
    }
}
