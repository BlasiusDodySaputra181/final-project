<?php

namespace App\Http\Controllers;

use App\Setup\Crud\SubmissionCrud;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SubmissionController extends Controller
{
    /**
     * Display the submission page.
     * Data is stored in the final__projects table.
     */
    public function page(): View
    {
        return view('admin.submission.index');
    }

    /**
     * Display a listing of final projects for submission.
     */
    public function index(Request $request, SubmissionCrud $crud): JsonResponse
    {
        return $crud->listSubmissions($request);
    }

    /**
     * Store a newly created or updated final project from the submission form.
     */
    public function store(Request $request, SubmissionCrud $crud): JsonResponse
    {
        return $crud->saveSubmission($request);
    }

    /**
     * Remove the specified final project.
     */
    public function destroy(int $id, SubmissionCrud $crud): JsonResponse
    {
        return $crud->destroySubmission($id);
    }

    /**
     * Final project submission form (signed-in student).
     */
    public function publicForm(SubmissionCrud $crud): View|RedirectResponse
    {
        return $crud->showPublicSubmissionForm();
    }

    /**
     * Save a submission from the public form (final__projects, book_grants, and library_clearances).
     */
    public function publicStore(Request $request, SubmissionCrud $crud): RedirectResponse
    {
        return $crud->savePublicSubmission($request);
    }

    /**
     * Submission proof: final projects already submitted (student role).
     */
    public function publicProof(SubmissionCrud $crud): View|RedirectResponse
    {
        return $crud->showPublicSubmissionProof();
    }

    /**
     * Server-side DataTables index for the Proof module.
     */
    public function publicProofIndex(Request $request, SubmissionCrud $crud): JsonResponse
    {
        return $crud->listPublicSubmissionProof($request);
    }

    /**
     * Student submission proof detail (library clearance and book grant).
     */
    public function publicProofDetail(int $id, SubmissionCrud $crud): View|RedirectResponse
    {
        return $crud->showPublicSubmissionProofDetail($id);
    }

    /**
     * Download the student's library clearance letter, only when membership is registered.
     */
    public function publicLibraryClearanceLetter(int $id, SubmissionCrud $crud): RedirectResponse|Response
    {
        return $crud->exportPublicLibraryClearanceLetterPdf($id);
    }

    /**
     * Download the student's book grant letter, only when membership is registered.
     */
    public function publicBookGrantLetter(int $id, SubmissionCrud $crud): RedirectResponse|Response
    {
        return $crud->exportPublicBookGrantLetterPdf($id);
    }

    /**
     * Public submission form access status.
     */
    public function accessStatus(SubmissionCrud $crud): JsonResponse
    {
        return $crud->submissionAccessStatus();
    }

    /**
     * Toggle public submission form access (officer).
     */
    public function toggleAccess(Request $request, SubmissionCrud $crud): JsonResponse
    {
        return $crud->toggleSubmissionAccess($request);
    }
}
