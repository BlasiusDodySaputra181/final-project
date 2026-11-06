<?php

namespace App\Setup\Crud;

use App\Models\FinalProject;
use App\Models\LibraryClearance;
use App\Models\User;
use App\Setup\LibraryClearancePdf;
use App\Support\CatalogCache;
use App\Support\DataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * CRUD Bebas Pustaka dan surat.
 * Anak dari BookGrantCrud. Controller: LibraryClearanceController.
 */
class LibraryClearanceCrud extends BookGrantCrud
{
    // --- Daftar admin dan surat ---

    public function listLibraryClearances(Request $request): JsonResponse
    {
        return DataTables::make(
            $request,
            LibraryClearance::query()
                ->select([
                    'id', 'email', 'phone_number', 'graduation_year', 'semester_graduated',
                    'membership_registered', 'final_project_id', 'created_at', 'updated_at',
                ])
                ->with(FinalProject::eagerCard()),
            function (LibraryClearance $clearance, int $number) {
                [$nim, $studentName, $cluster, $programStudy, $degreeLevel]
                    = $this->mapFinalProjectDisplayFields($clearance->finalProject);

                return [
                    $number,
                    $clearance->id,
                    $nim,
                    $studentName,
                    $cluster,
                    $programStudy,
                    $degreeLevel,
                    $this->dash($clearance->membership_registered),
                    $clearance->email,
                    $clearance->phone_number,
                    $clearance->graduation_year,
                    $clearance->semester_graduated,
                    $this->dateLabel($clearance->created_at),
                    $this->dateLabel($clearance->updated_at),
                    $clearance->final_project_id,
                ];
            },
            $this->finalProjectOrderMap('library_clearances') + [
                'email' => 'email',
                'phone_number' => 'phone_number',
                'graduation_year' => 'graduation_year',
                'semester_graduated' => 'semester_graduated',
            ],
            [],
            fn ($query, string $keyword) => $this->searchOwnAndFinalProject(
                $query,
                $keyword,
                ['email', 'phone_number', 'graduation_year', 'semester_graduated', 'membership_registered']
            )
        );
    }

    public function saveLibraryClearance($request): JsonResponse
    {
        $request->validate([
            'libraryclearanceid' => ['nullable', 'integer', 'exists:library_clearances,id'],
            'email' => ['required', 'email', 'max:255'],
            'phone_number' => ['required', 'string', 'max:30'],
            'graduation_year' => ['required', 'integer', 'min:'.FinalProject::YEAR_MIN, 'max:'.FinalProject::YEAR_MAX],
            'semester_graduated' => ['required', 'integer', 'min:1', 'max:20'],
            'membership_registered' => ['nullable', 'string', 'max:50'],
            'final_project_id' => ['required', 'integer', 'exists:final__projects,id'],
        ]);

        $this->updateOrCreateLibraryClearance(
            $request->libraryclearanceid,
            $request->email,
            $request->phone_number,
            $request->graduation_year,
            $request->semester_graduated,
            $request->membership_registered,
            $request->final_project_id
        );

        return response()->json('Data Bebas Pustaka Berhasil Disimpan');
    }

    public function destroyLibraryClearance($id): JsonResponse
    {
        if (! $this->deleteLibraryClearance((int) $id)) {
            return response()->json('Hapus Gagal', 404);
        }

        return response()->json('Hapus Bebas Pustaka Berhasil');
    }

    /**
     * Unduh surat keterangan bebas pinjaman buku (inline di tab baru).
     */
    public function exportLibraryClearanceLetterPdf(int $id)
    {
        $libraryClearance = LibraryClearance::query()
            ->with(['finalProject.studyProgram', 'finalProject.finalProjectType'])
            ->find($id);

        if (! $libraryClearance) {
            abort(404, 'Data bebas pustaka tidak ditemukan.');
        }

        if (! $this->isMembershipRegistered($libraryClearance->membership_registered)) {
            abort(403, $this->membershipLetterDeniedMessage());
        }

        return $this->streamLibraryClearanceLetterPdf($libraryClearance);
    }

    /**
     * Unduh surat bebas pustaka milik mahasiswa login.
     */
    public function exportPublicLibraryClearanceLetterPdf(int $id): RedirectResponse|Response
    {
        if ($redirect = $this->redirectStudentWithoutCompleteProof(Auth::guard('student')->user())) {
            return $redirect;
        }

        $libraryClearance = $this->findOwnedLibraryClearanceForStudent($id);

        if (! $libraryClearance) {
            return redirect()
                ->route('page-public-proof')
                ->with('error', 'Data bebas pustaka tidak ditemukan.');
        }

        if (! $this->isMembershipRegistered($libraryClearance->membership_registered)) {
            return redirect()
                ->route('page-public-proof-detail', $libraryClearance->final_project_id)
                ->with('error', $this->membershipLetterDeniedMessage());
        }

        return $this->streamLibraryClearanceLetterPdf($libraryClearance);
    }

    // --- Surat milik mahasiswa ---

    protected function findOwnedLibraryClearanceForStudent(int $id): ?LibraryClearance
    {
        $nim = $this->studentNim();

        if ($nim === '') {
            return null;
        }

        return LibraryClearance::query()
            ->with(['finalProject.studyProgram', 'finalProject.finalProjectType'])
            ->whereKey($id)
            ->whereHas('finalProject', fn ($query) => $query->where('nim', $nim))
            ->first();
    }

    protected function streamLibraryClearanceLetterPdf(LibraryClearance $libraryClearance): Response
    {
        $finalProject = $libraryClearance->finalProject;
        $degreeLevel = $finalProject?->finalProjectType?->level
            ?: ($finalProject?->degree_level ?? '');
        $studyProgram = $finalProject?->studyProgram?->study_program
            ?: ($finalProject?->program_study ?? '');

        $coordinator = CatalogCache::signatory(User::ROLE_COORDINATOR);
        $officer = CatalogCache::signatory(User::ROLE_OFFICER);

        $pdf = (new LibraryClearancePdf)->build([
            'student_name' => (string) ($finalProject?->student_name ?? ''),
            'nim' => (string) ($finalProject?->nim ?? ''),
            'study_program' => (string) $studyProgram,
            'degree_level' => (string) $degreeLevel,
            'email' => (string) ($libraryClearance->email ?? ''),
            'phone_number' => (string) ($libraryClearance->phone_number ?? ''),
            'date_label' => $this->formatTranslatedDate('j F Y', $this->currentDate()),
            'coordinator_name' => (string) ($coordinator?->name ?? ''),
            'coordinator_identity_number' => (string) ($coordinator?->identity_number ?? ''),
            'officer_name' => (string) ($officer?->name ?? ''),
            'officer_identity_number' => (string) ($officer?->identity_number ?? ''),
        ]);

        $nim = preg_replace('/\W+/', '', (string) ($finalProject?->nim ?? 'surat')) ?: 'surat';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="surat-bebas-pustaka-'.$nim.'.pdf"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
