<?php

namespace App\Http\Controllers;

use App\Setup\Crud\MembershipCrud;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MembershipController extends Controller
{
    /**
     * Display the membership page.
     */
    public function page(): View
    {
        return view('admin.membership.index');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, MembershipCrud $crud): JsonResponse
    {
        return $crud->listMemberships($request);
    }

    /**
     * Store a newly created or updated resource in storage.
     */
    public function store(Request $request, MembershipCrud $crud): JsonResponse
    {
        return $crud->saveMembership($request);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id, MembershipCrud $crud): JsonResponse
    {
        return $crud->destroyMembership($id);
    }

    /**
     * Formulir keanggotaan (login role Mahasiswa).
     */
    public function publicForm(MembershipCrud $crud): View|RedirectResponse
    {
        return $crud->showPublicMembershipForm();
    }

    /**
     * Simpan keanggotaan dari formulir mahasiswa.
     */
    public function publicStore(Request $request, MembershipCrud $crud): RedirectResponse
    {
        return $crud->savePublicMembership($request);
    }

    /**
     * Preview/unduh formulir keanggotaan (inline di tab baru).
     */
    public function letter(int $id, MembershipCrud $crud)
    {
        return $crud->exportMembershipLetterPdf($id);
    }

    /**
     * Unduh surat keanggotaan milik mahasiswa login.
     */
    public function publicLetter(int $id, MembershipCrud $crud): RedirectResponse|Response
    {
        return $crud->exportPublicMembershipLetterPdf($id);
    }
}
