<?php

namespace App\Http\Controllers;

use App\Setup\Preference;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Global settings for the final project file folder and student access.
     */
    public function page(Preference $preference): View
    {
        return $preference->page();
    }

    /**
     * Save the folder that stores final project files and approval sheets.
     */
    public function store(Request $request, Preference $preference): JsonResponse
    {
        return $preference->store($request);
    }

    /**
     * Save the AI key and model used by final-project import.
     */
    public function storeArtificialIntelligence(Request $request, Preference $preference): JsonResponse
    {
        return $preference->storeArtificialIntelligence($request);
    }

    /**
     * Live progress of the folder move when the file location is saved.
     */
    public function moveProgress(Request $request, Preference $preference): JsonResponse
    {
        return $preference->moveProgress($request);
    }

    /**
     * Server subfolders for the location picker.
     */
    public function browse(Request $request, Preference $preference): JsonResponse
    {
        return $preference->browse($request);
    }
}
