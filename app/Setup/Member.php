<?php

namespace App\Setup;

use App\Models\Membership;

/**
 * Tampilan surat keanggotaan dan pengecekan akun yang sudah terdaftar.
 * Dipanggil dari model Membership.
 */
class Member
{
    /**
     * Tampilan tanggal lahir, contoh: 13 September 2002.
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
     * Tampilan tempat dan tanggal lahir, contoh: Bekasi / 13 September 2002.
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
     * Hari dan tanggal surat dari created_at, contoh: Senin, 14 September 2026.
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
     * Nomor HP/telepon dari bebas pustaka terkait (library_clearances.phone_number).
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
