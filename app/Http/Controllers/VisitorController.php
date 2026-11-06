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
     * Petugas menetapkan / membatalkan pengawasan via checkbox datatable.
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
     * Formulir daftar kunjungan (login role Mahasiswa).
     */
    public function publicForm(VisitorCrud $crud): View
    {
        return $crud->showPublicVisitorForm();
    }

    /**
     * Simpan daftar kunjungan dari formulir publik.
     */
    public function publicStore(Request $request, VisitorCrud $crud): RedirectResponse
    {
        return $crud->savePublicVisitor($request);
    }

    /**
     * Status akses formulir publik.
     */
    public function accessStatus(VisitorCrud $crud): JsonResponse
    {
        return $crud->visitorAccessStatus();
    }

    /**
     * Toggle buka/tutup akses formulir publik (Petugas).
     */
    public function toggleAccess(Request $request, VisitorCrud $crud): JsonResponse
    {
        return $crud->toggleVisitorAccess($request);
    }
}
