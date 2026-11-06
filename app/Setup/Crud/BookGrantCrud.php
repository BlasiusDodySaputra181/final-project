<?php

namespace App\Setup\Crud;

use App\Models\BookGrant;
use App\Models\FinalProject;
use App\Models\User;
use App\Setup\BookGrantPdf;
use App\Support\CatalogCache;
use App\Support\DataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * CRUD Hibah Buku dan surat tanda terima.
 * Anak dari VisitorCrud. Controller: BookGrantController.
 */
class BookGrantCrud extends VisitorCrud
{
    // --- Daftar admin dan surat ---

    public function listBookGrants(Request $request): JsonResponse
    {
        return DataTables::make(
            $request,
            BookGrant::query()
                ->select([
                    'id', 'book_title', 'book_year', 'author', 'membership_registered',
                    'final_project_id', 'created_at', 'updated_at',
                ])
                ->with(FinalProject::eagerCard()),
            function (BookGrant $bookGrant, int $number) {
                $project = $bookGrant->finalProject;
                [$nim, $studentName, $cluster, $programStudy, $degreeLevel, , $submissionDate]
                    = $this->mapFinalProjectDisplayFields($project);

                return [
                    $number,
                    $bookGrant->id,
                    $nim,
                    $studentName,
                    $cluster,
                    $programStudy,
                    $degreeLevel,
                    $this->dash($bookGrant->membership_registered),
                    $this->dateLabel($submissionDate),
                    $bookGrant->book_title,
                    $bookGrant->book_year,
                    $bookGrant->author,
                    $this->dateLabel($bookGrant->created_at),
                    $this->dateLabel($bookGrant->updated_at),
                    $bookGrant->final_project_id,
                ];
            },
            $this->finalProjectOrderMap('book_grants') + [
                'book_title' => 'book_title',
                'book_year' => 'book_year',
                'author' => 'author',
            ],
            [],
            fn ($query, string $keyword) => $this->searchOwnAndFinalProject(
                $query,
                $keyword,
                ['book_title', 'author', 'book_year', 'membership_registered']
            )
        );
    }

    public function saveBookGrant($request): JsonResponse
    {
        $request->validate([
            'bookgrantid' => ['nullable', 'integer', 'exists:book_grants,id'],
            'book_title' => ['required', 'string', 'max:255'],
            'book_year' => ['required', 'integer', 'min:'.FinalProject::YEAR_MIN, 'max:'.FinalProject::YEAR_MAX],
            'author' => ['required', 'string', 'max:255'],
            'membership_registered' => ['nullable', 'string', 'max:50'],
            'final_project_id' => ['required', 'integer', 'exists:final__projects,id'],
        ]);

        $this->updateOrCreateBookGrant(
            $request->bookgrantid,
            $request->book_title,
            $request->book_year,
            $request->author,
            $request->membership_registered,
            $request->final_project_id
        );

        return response()->json('Data Hibah Buku Berhasil Disimpan');
    }

    public function destroyBookGrant($id): JsonResponse
    {
        if (! $this->deleteBookGrant((int) $id)) {
            return response()->json('Hapus Gagal', 404);
        }

        return response()->json('Hapus Hibah Buku Berhasil');
    }

    /**
     * Unduh tanda terima penyerahan hibah buku (inline di tab baru).
     */
    public function exportBookGrantLetterPdf(int $id)
    {
        $bookGrant = BookGrant::query()
            ->with(['finalProject.studyProgram'])
            ->find($id);

        if (! $bookGrant) {
            abort(404, 'Data hibah buku tidak ditemukan.');
        }

        if (! $this->isMembershipRegistered($bookGrant->membership_registered)) {
            abort(403, $this->membershipLetterDeniedMessage());
        }

        return $this->streamBookGrantLetterPdf($bookGrant);
    }

    /**
     * Unduh surat hibah buku milik mahasiswa login.
     */
    public function exportPublicBookGrantLetterPdf(int $id): RedirectResponse|Response
    {
        if ($redirect = $this->redirectStudentWithoutCompleteProof(Auth::guard('student')->user())) {
            return $redirect;
        }

        $bookGrant = $this->findOwnedBookGrantForStudent($id);

        if (! $bookGrant) {
            return redirect()
                ->route('page-public-proof')
                ->with('error', 'Data hibah buku tidak ditemukan.');
        }

        if (! $this->isMembershipRegistered($bookGrant->membership_registered)) {
            return redirect()
                ->route('page-public-proof-detail', $bookGrant->final_project_id)
                ->with('error', $this->membershipLetterDeniedMessage());
        }

        return $this->streamBookGrantLetterPdf($bookGrant);
    }

    // --- Surat milik mahasiswa ---

    protected function findOwnedBookGrantForStudent(int $id): ?BookGrant
    {
        $nim = $this->studentNim();

        if ($nim === '') {
            return null;
        }

        return BookGrant::query()
            ->with(['finalProject.studyProgram'])
            ->whereKey($id)
            ->whereHas('finalProject', fn ($query) => $query->where('nim', $nim))
            ->first();
    }

    protected function streamBookGrantLetterPdf(BookGrant $bookGrant): Response
    {
        $finalProject = $bookGrant->finalProject;
        $studyProgram = $finalProject?->studyProgram;
        $cluster = (string) ($studyProgram?->cluster ?? '');
        $program = (string) (
            $studyProgram?->study_program
                ?: ($finalProject?->program_study ?? '')
        );

        $officer = CatalogCache::signatory(User::ROLE_OFFICER);

        $pdf = (new BookGrantPdf)->build([
            'student_name' => (string) ($finalProject?->student_name ?? ''),
            'nim' => (string) ($finalProject?->nim ?? ''),
            'cluster' => $cluster,
            'study_program' => $program,
            'book_title' => (string) ($bookGrant->book_title ?? ''),
            'author' => (string) ($bookGrant->author ?? ''),
            'book_year' => (string) ($bookGrant->book_year ?? ''),
            'date_label' => $this->formatTranslatedDate('j F Y', $this->currentDate()),
            'officer_name' => (string) ($officer?->name ?? ''),
            'officer_identity_number' => (string) ($officer?->identity_number ?? ''),
        ]);

        $nim = preg_replace('/\W+/', '', (string) ($finalProject?->nim ?? 'surat')) ?: 'surat';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="tanda-terima-hibah-buku-'.$nim.'.pdf"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
