<?php

namespace App\Http\Controllers;

use App\Setup\Crud\DestructionCrud;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DestructionController extends Controller
{
    /**
     * Display the destruction page.
     */
    public function page(): View
    {
        return view('admin.destruction.index');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, DestructionCrud $crud): JsonResponse
    {
        return $crud->listDestructions($request);
    }

    /**
     * Store a newly created or updated resource in storage.
     */
    public function store(Request $request, DestructionCrud $crud): JsonResponse
    {
        return $crud->saveDestruction($request);
    }

    /**
     * Koordinator menyetujui / membatalkan persetujuan via checkbox datatable.
     */
    public function approve(Request $request, DestructionCrud $crud): JsonResponse
    {
        return $crud->approveDestructions($request);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id, DestructionCrud $crud): JsonResponse
    {
        return $crud->destroyDestruction($id);
    }
}
