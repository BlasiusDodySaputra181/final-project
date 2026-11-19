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
     * Membership form (signed-in student).
     */
    public function publicForm(MembershipCrud $crud): View|RedirectResponse
    {
        return $crud->showPublicMembershipForm();
    }

    /**
     * Save membership from the student form.
     */
    public function publicStore(Request $request, MembershipCrud $crud): RedirectResponse
    {
        return $crud->savePublicMembership($request);
    }

    /**
     * Preview or download the membership form (inline in a new tab).
     */
    public function letter(int $id, MembershipCrud $crud)
    {
        return $crud->exportMembershipLetterPdf($id);
    }

    /**
     * Download the signed-in student's membership letter.
     */
    public function publicLetter(int $id, MembershipCrud $crud): RedirectResponse|Response
    {
        return $crud->exportPublicMembershipLetterPdf($id);
    }
}
