<?php

namespace App\Http\Controllers;

use App\Setup\Crud\FinalProjectCrud;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    /**
     * Display the import page.
     */
    public function page(): View
    {
        return view('admin.import.index');
    }

    /**
     * Extract cover information from PDF text using AI.
     */
    public function reader(Request $request, FinalProjectCrud $crud): JsonResponse
    {
        return $crud->readCoverInformation($request);
    }

    /**
     * Store the imported final projects in storage.
     */
    public function store(Request $request, FinalProjectCrud $crud): JsonResponse
    {
        return $crud->importFinalProjects($request);
    }
}
