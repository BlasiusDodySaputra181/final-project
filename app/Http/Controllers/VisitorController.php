<?php

namespace App\Http\Controllers;

use App\Setup\Crud\VisitorCrud;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VisitorController extends Controller
{
    /**
     * Display the visitor page.
     */
    public function page(): View
    {
        return view('admin.visitor.index');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, VisitorCrud $crud): JsonResponse
    {
        return $crud->listVisitors($request);
    }

    /**
     * Store a newly created or updated resource in storage.
     */
    public function store(Request $request, VisitorCrud $crud): JsonResponse
    {
        return $crud->saveVisitor($request);
    }

    /**
     * An officer sets or clears supervision from the datatable checkbox.
     */
    public function supervise(Request $request, VisitorCrud $crud): JsonResponse
    {
        return $crud->superviseVisitors($request);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id, VisitorCrud $crud): JsonResponse
    {
        return $crud->destroyVisitor($id);
    }

    /**
     * Visit registration form (signed-in student).
     */
    public function publicForm(VisitorCrud $crud): View
    {
        return $crud->showPublicVisitorForm();
    }

    /**
     * Save a visit registration from the public form.
     */
    public function publicStore(Request $request, VisitorCrud $crud): RedirectResponse
    {
        return $crud->savePublicVisitor($request);
    }

    /**
     * Public form access status.
     */
    public function accessStatus(VisitorCrud $crud): JsonResponse
    {
        return $crud->visitorAccessStatus();
    }

    /**
     * Toggle public form access (officer).
     */
    public function toggleAccess(Request $request, VisitorCrud $crud): JsonResponse
    {
        return $crud->toggleVisitorAccess($request);
    }
}
