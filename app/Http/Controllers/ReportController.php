<?php

namespace App\Http\Controllers;

use App\Setup\Crud\ReportCrud;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Display the report page.
     */
    public function page(): View
    {
        return view('admin.report.index');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, ReportCrud $crud): JsonResponse
    {
        return $crud->listReports($request);
    }

    /**
     * Store a newly created or updated resource in storage.
     */
    public function store(Request $request, ReportCrud $crud): JsonResponse
    {
        return $crud->saveReport($request);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id, ReportCrud $crud): JsonResponse
    {
        return $crud->destroyReport($id);
    }
}
