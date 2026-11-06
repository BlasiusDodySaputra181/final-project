<?php

namespace App\Setup;

use App\Models\FinalProject;
use App\Models\Membership;
use App\Models\Setting;
use App\Models\User;
use App\Support\DataTables;
use App\Support\KeywordSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Induk operasi CRUD.
 *
 * Mewarisi setup dari Loader: tanggal dan simpan model.
 * Method di sini dipakai bersama setiap modul anak (akses formulir, alur mahasiswa, DataTables).
 *
 * Rantai public inheritance:
 * Manager → Loader → Crud → FinalProjectCrud → StudyProgramCrud → MembershipCrud
 * → UserCrud → FinalProjectTypeCrud → VisitorCrud → BookGrantCrud → LibraryClearanceCrud
 * → BarcodeCrud → SubmissionCrud → SearchCrud → DestructionCrud → ArchiveCrud
 * → ReportCrud → Processor.
 */
abstract class Crud extends Loader
{
    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $ownColumns
     */
    protected function searchOwnAndFinalProject($query, string $keyword, array $ownColumns): void
    {
        KeywordSearch::ownAndFinalProject(
            $query,
            $keyword,
            $ownColumns,
            $query->getModel()->getTable()
        );
    }

    /**
     * @return array<string, string|\Closure>
     */
    protected function finalProjectOrderMap(string $foreignTable, string $foreignKey = 'final_project_id'): array
    {
        $order = function (string $column) use ($foreignTable, $foreignKey) {
            return function ($query, string $dir) use ($column, $foreignTable, $foreignKey) {
                DataTables::orderByRelated(
                    $query,
                    'final__projects',
                    $column,
                    $foreignTable.'.'.$foreignKey,
                    'id',
                    $dir
                );
            };
        };

        return [
            'id' => 'id',
            'nim' => $order('nim'),
            'student_name' => $order('student_name'),
            'final_project_title' => $order('final_project_title'),
            'program_study' => $order('program_study'),
            'degree_level' => $order('degree_level'),
            'final_project_year' => $order('final_project_year'),
            'status' => $order('final_project_year'),
        ];
    }

    // --- Akses formulir publik ---

    /**
     * Status akses formulir publik (visitor / barcode / submission).
     */
    protected function accessStatusResponse(callable $getter): JsonResponse
    {
        return response()->json([
            'open' => (bool) $getter(),
        ]);
    }

    /**
     * Toggle akses formulir publik.
     */
    protected function toggleAccessResponse(
        Request $request,
        callable $setter,
        callable $getter,
        string $openMessage,
        string $closeMessage
    ): JsonResponse {
        $request->validate([
            'open' => ['required', 'boolean'],
        ]);

        $setter($request->boolean('open'));
        $open = (bool) $getter();

        return response()->json([
            'open' => $open,
            'message' => $open ? $openMessage : $closeMessage,
        ]);
    }

    // --- Keanggotaan dan NIM mahasiswa ---

    protected function isMembershipRegistered(mixed $membership): bool
    {
        return trim((string) $membership) === 'Terdaftar';
    }

    protected function membershipLetterDeniedMessage(): string
    {
        return 'Keanggotaan belum terdaftar. Surat hanya dapat diunduh untuk tugas akhir yang sudah terdaftar keanggotaan.';
    }

    protected function studentNim(): string
    {
        return trim((string) (Auth::guard('student')->user()?->identity_number ?? ''));
    }

    // --- DataTables halaman mahasiswa ---

    /**
     * Response standar DataTables serverSide untuk tabel publik mahasiswa.
     *
     * @param  Builder<FinalProject>  $query
     * @param  callable(FinalProject, int): array<int, mixed>  $mapRow
     * @param  array<string, string|\Closure>  $orderMap
     * @param  list<string>  $searchColumns
     * @param  callable(Builder<FinalProject>, string): void|null  $search
     */
    protected function publicDataTablesResponse(
        Request $request,
        $query,
        callable $mapRow,
        array $orderMap,
        string $defaultOrderColumn,
        string $defaultOrderDir = 'asc',
        ?int $recordsTotal = null,
        array $searchColumns = [],
        ?callable $search = null,
        ?string $keyword = null
    ): JsonResponse {
        return DataTables::make(
            $request,
            $query,
            $mapRow,
            $orderMap,
            $searchColumns,
            $search,
            $defaultOrderColumn,
            $defaultOrderDir,
            $recordsTotal,
            $keyword
        );
    }

    // --- Alur mahasiswa: anggota, serah, dan bukti ---

    protected function studentSubmissionClosedMessage(?User $student): ?string
    {
        if (! Setting::isSubmissionAccessOpen()) {
            return 'Formulir penyerahan sedang ditutup oleh petugas. Silakan coba lagi sesuai jadwal operasional.';
        }

        if ($student && ! Setting::isUserSubmissionAccessOpen((int) $student->id)) {
            return 'Akses formulir penyerahan untuk akun Anda sedang ditutup oleh petugas.';
        }

        return null;
    }

    protected function studentMembershipClosedMessage(?User $student): ?string
    {
        if (! $student || Setting::isUserMembershipAccessOpen((int) $student->id)) {
            return null;
        }

        if ($this->studentHasSubmittedProof($student)) {
            return 'Akses formulir keanggotaan untuk akun Anda sedang ditutup oleh petugas. Setelah akses dibuka, lengkapi keanggotaan agar data anggota tampil pada menu Bukti.';
        }

        return 'Akses formulir keanggotaan untuk akun Anda sedang ditutup oleh petugas.';
    }

    protected function studentHasMembership(?User $student): bool
    {
        return Membership::existsForUser($student?->id);
    }

    protected function studentAlreadyRegisteredMembershipMessage(?User $student = null): string
    {
        if ($this->studentCanViewProof($student)) {
            return 'Anda sudah terdaftar keanggotaan. Data keanggotaan dapat dilihat pada menu Bukti.';
        }

        return 'Anda sudah terdaftar keanggotaan. Setiap mahasiswa hanya dapat mendaftar satu kali. Menu Bukti sudah tersedia.';
    }

    protected function studentNeedsMembershipMessage(): string
    {
        return 'Lengkapi data keanggotaan terlebih dahulu sebelum menyerahkan tugas akhir.';
    }

    protected function studentNeedsMembershipForProofMessage(): string
    {
        return 'Lengkapi data keanggotaan terlebih dahulu sebelum melihat bukti penyerahan.';
    }

    protected function studentNeedsProofMessage(?User $student): string
    {
        if ($this->studentHasMembership($student) || $this->studentHasSubmittedProof($student)) {
            return 'Menu Bukti sudah tersedia. Buka menu Bukti untuk melihat data yang sudah diisi.';
        }

        return 'Menu Bukti tampil setelah data keanggotaan atau penyerahan tugas akhir tersedia.';
    }

    protected function studentProgressRoute(?User $student): string
    {
        if ($this->studentCanViewProof($student)) {
            return 'page-public-proof';
        }

        if ($this->studentHasMembership($student)) {
            return 'page-public-submission';
        }

        return 'page-public-membership';
    }

    protected function redirectStudentWithoutMembership(?User $student): ?RedirectResponse
    {
        if ($this->studentHasMembership($student)) {
            return null;
        }

        return redirect()
            ->route('page-public-membership')
            ->with('error', $this->studentNeedsMembershipMessage());
    }

    protected function redirectStudentIfAlreadySubmitted(?User $student): ?RedirectResponse
    {
        if (! $this->studentHasSubmittedProof($student)) {
            return null;
        }

        if ($this->studentCanViewProof($student)) {
            return redirect()
                ->route('page-public-proof')
                ->with('error', $this->studentAlreadySubmittedMessage());
        }

        return redirect()
            ->route('page-public-membership')
            ->with('error', $this->studentNeedsMembershipForProofMessage());
    }

    protected function redirectStudentWithoutCompleteProof(?User $student): ?RedirectResponse
    {
        if ($this->studentCanViewProof($student)) {
            return null;
        }

        return redirect()
            ->route($this->studentProgressRoute($student))
            ->with('error', $this->studentNeedsProofMessage($student));
    }

    protected function studentHasSubmittedProof(?User $student): bool
    {
        return FinalProject::existsForNim($student?->identity_number);
    }

    protected function studentCanViewProof(?User $student): bool
    {
        return $this->studentHasMembership($student)
            || $this->studentHasSubmittedProof($student);
    }

    protected function studentAlreadySubmittedMessage(): string
    {
        return 'Anda sudah menyerahkan tugas akhir. Setiap mahasiswa hanya dapat menyerahkan satu kali. Menu Penyerahan akan tampil kembali jika petugas menghapus data penyerahan Anda.';
    }
}
