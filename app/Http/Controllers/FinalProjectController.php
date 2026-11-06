<?php

namespace App\Http\Controllers;

use App\Setup\Crud\FinalProjectCrud;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinalProjectController extends Controller
{
    /**
     * Display the final project page for a selected type (sidebar child).
     */
    public function page(string $type, FinalProjectCrud $crud): View
    {
        return $crud->showFinalProjectPage($type);
    }

    /**
     * Display a listing of the resource, optionally filtered by type.
     */
    public function index(Request $request, FinalProjectCrud $crud): JsonResponse
    {
        return $crud->listFinalProjectsForPage($request);
    }

    /**
     * Opsi tugas akhir untuk select form (pencarian server-side, terbatas).
     */
    public function options(Request $request, FinalProjectCrud $crud): JsonResponse
    {
        return $crud->listFinalProjectOptions($request);
    }

    /**
     * Store a newly created or updated resource in storage.
     */
    public function store(Request $request, FinalProjectCrud $crud): JsonResponse
    {
        return $crud->saveFinalProject($request);
    }

    /**
     * Ukuran file tugas akhir / lembar pengesahan (MB).
     */
    public function showFileSize(Request $request, FinalProjectCrud $crud): JsonResponse
    {
        return $crud->showFileSize($request);
    }

    /**
     * Download the final project document of the specified resource.
     */
    public function downloadFinalProject(int $id, FinalProjectCrud $crud)
    {
        return $crud->downloadFinalProject($id);
    }

    /**
     * Download the approval sheet of the specified resource.
     */
    public function downloadApprovalSheet(int $id, FinalProjectCrud $crud)
    {
        return $crud->downloadApprovalSheet($id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id, FinalProjectCrud $crud): JsonResponse
    {
        return $crud->destroyFinalProject($id);
    }
}
