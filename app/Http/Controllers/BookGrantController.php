<?php

namespace App\Http\Controllers;

use App\Setup\Crud\BookGrantCrud;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookGrantController extends Controller
{
    /**
     * Display the book grant page.
     */
    public function page(): View
    {
        return view('admin.bookgrant.index');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, BookGrantCrud $crud): JsonResponse
    {
        return $crud->listBookGrants($request);
    }

    /**
     * Store a newly created or updated resource in storage.
     */
    public function store(Request $request, BookGrantCrud $crud): JsonResponse
    {
        return $crud->saveBookGrant($request);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id, BookGrantCrud $crud): JsonResponse
    {
        return $crud->destroyBookGrant($id);
    }

    /**
     * Preview or download the book grant receipt (inline in a new tab).
     */
    public function letter(int $id, BookGrantCrud $crud)
    {
        return $crud->exportBookGrantLetterPdf($id);
    }
}
