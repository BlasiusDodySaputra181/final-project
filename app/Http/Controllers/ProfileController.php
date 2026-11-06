<?php

namespace App\Http\Controllers;

use App\Setup\Profile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Student account page (/akun).
     */
    public function publicPage(Request $request, Profile $profile): View
    {
        return $profile->publicPage($request);
    }

    /**
     * Save the student profile. The form redirects instead of returning JSON.
     */
    public function publicStore(Request $request, Profile $profile): RedirectResponse
    {
        return $profile->publicStore($request);
    }

    /**
     * Profil Petugas / Koordinator (/profil).
     */
    public function page(Request $request, Profile $profile): View
    {
        return $profile->page($request);
    }

    /**
     * Simpan profil Petugas / Koordinator.
     * Permintaan AJAX membalas JSON; kiriman form biasa kembali ke halaman profil.
     */
    public function store(Request $request, Profile $profile): JsonResponse|RedirectResponse
    {
        return $profile->store($request);
    }
}
