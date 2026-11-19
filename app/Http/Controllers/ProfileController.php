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
     * Officer or coordinator profile (/profil).
     */
    public function page(Request $request, Profile $profile): View
    {
        return $profile->page($request);
    }

    /**
     * Save the officer or coordinator profile.
     * AJAX requests return JSON. A normal form post returns to the profile page.
     */
    public function store(Request $request, Profile $profile): JsonResponse|RedirectResponse
    {
        return $profile->store($request);
    }
}
