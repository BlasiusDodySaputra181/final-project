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
     * Display the submission (penyerahan) page.
     * Data disimpan di tabel final__projects.
     */
    public function page(): View
    {
        return view('admin.submission.index');
    }

    /**
     * Display a listing of final projects for penyerahan.
     */
    public function index(Request $request, SubmissionCrud $crud): JsonResponse
    {
        return $crud->listSubmissions($request);
    }

    /**
     * Store a newly created or updated final project from penyerahan form.
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
     * Formulir penyerahan tugas akhir (login role Mahasiswa).
     */
    public function publicForm(SubmissionCrud $crud): View|RedirectResponse
    {
        return $crud->showPublicSubmissionForm();
    }

    /**
     * Simpan penyerahan dari formulir publik (final__projects + book_grants + library_clearances).
     */
    public function publicStore(Request $request, SubmissionCrud $crud): RedirectResponse
    {
        return $crud->savePublicSubmission($request);
    }

    /**
     * Bukti penyerahan: daftar tugas akhir yang sudah diserahkan (role Mahasiswa).
     */
    public function publicProof(SubmissionCrud $crud): View|RedirectResponse
    {
        return $crud->showPublicSubmissionProof();
    }

    /**
     * DataTables server-side index untuk modul Bukti.
     */
    public function publicProofIndex(Request $request, SubmissionCrud $crud): JsonResponse
    {
        return $crud->listPublicSubmissionProof($request);
    }

    /**
     * Detail bukti penyerahan (bebas pustaka + hibah buku) milik mahasiswa.
     */
    public function publicProofDetail(int $id, SubmissionCrud $crud): View|RedirectResponse
    {
        return $crud->showPublicSubmissionProofDetail($id);
    }

    /**
     * Unduh surat bebas pustaka milik mahasiswa (hanya jika keanggotaan terdaftar).
     */
    public function publicLibraryClearanceLetter(int $id, SubmissionCrud $crud): RedirectResponse|Response
    {
        return $crud->exportPublicLibraryClearanceLetterPdf($id);
    }

    /**
     * Unduh surat hibah buku milik mahasiswa (hanya jika keanggotaan terdaftar).
     */
    public function publicBookGrantLetter(int $id, SubmissionCrud $crud): RedirectResponse|Response
    {
        return $crud->exportPublicBookGrantLetterPdf($id);
    }

    /**
     * Status akses formulir penyerahan publik.
     */
    public function accessStatus(SubmissionCrud $crud): JsonResponse
    {
        return $crud->submissionAccessStatus();
    }

    /**
     * Toggle buka/tutup akses formulir penyerahan publik (Petugas).
     */
    public function toggleAccess(Request $request, SubmissionCrud $crud): JsonResponse
    {
        return $crud->toggleSubmissionAccess($request);
    }
}
