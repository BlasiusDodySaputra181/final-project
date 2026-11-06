<?php

namespace App\Http\Controllers;

use App\Setup\Crud\LibraryClearanceCrud;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LibraryClearanceController extends Controller
{
    /**
     * Display the library clearance page.
     */
    public function page(): View
    {
        return view('admin.libraryclearance.index');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, LibraryClearanceCrud $crud): JsonResponse
    {
        return $crud->listLibraryClearances($request);
    }

    /**
     * Store a newly created or updated resource in storage.
     */
    public function store(Request $request, LibraryClearanceCrud $crud): JsonResponse
    {
        return $crud->saveLibraryClearance($request);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id, LibraryClearanceCrud $crud): JsonResponse
    {
        return $crud->destroyLibraryClearance($id);
    }

    /**
     * Preview/unduh surat bebas pustaka (inline di tab baru).
     */
    public function letter(int $id, LibraryClearanceCrud $crud)
    {
        return $crud->exportLibraryClearanceLetterPdf($id);
    }
}
