<?php

namespace App\Http\Controllers;

use App\Setup\Crud\ArchiveCrud;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArchiveController extends Controller
{
    /**
     * Display the archive page.
     */
    public function page(): View
    {
        return view('admin.archive.index');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, ArchiveCrud $crud): JsonResponse
    {
        return $crud->listArchives($request);
    }

    /**
     * Store a newly created or updated resource in storage.
     */
    public function store(Request $request, ArchiveCrud $crud): JsonResponse
    {
        return $crud->saveArchive($request);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id, ArchiveCrud $crud): JsonResponse
    {
        return $crud->destroyArchive($id);
    }
}
