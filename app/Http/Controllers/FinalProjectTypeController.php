<?php

namespace App\Http\Controllers;

use App\Setup\Crud\FinalProjectTypeCrud;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinalProjectTypeController extends Controller
{
    /**
     * Display the final project type page.
     */
    public function page(): View
    {
        return view('admin.finalprojecttype.index');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, FinalProjectTypeCrud $crud): JsonResponse
    {
        return $crud->listFinalProjectTypes($request);
    }

    /**
     * List final project types as select options for other forms.
     */
    public function options(FinalProjectTypeCrud $crud): JsonResponse
    {
        return $crud->listFinalProjectTypeOptions();
    }

    /**
     * Store a newly created or updated resource in storage.
     */
    public function store(Request $request, FinalProjectTypeCrud $crud): JsonResponse
    {
        return $crud->saveFinalProjectType($request);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id, FinalProjectTypeCrud $crud): JsonResponse
    {
        return $crud->destroyFinalProjectType($id);
    }
}
