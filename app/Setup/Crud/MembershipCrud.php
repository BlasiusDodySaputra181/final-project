<?php

namespace App\Setup\Crud;

use App\Models\Membership;
use App\Models\User;
use App\Setup\MembershipPdf;
use App\Support\CatalogCache;
use App\Support\DataTables;
use App\Support\KeywordSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * CRUD Keanggotaan, formulir publik, dan surat.
 * Anak dari StudyProgramCrud. Controller: MembershipController.
 */
class MembershipCrud extends StudyProgramCrud
{
    // --- Daftar admin dan surat keanggotaan ---

    public function listMemberships(Request $request): JsonResponse
    {
        return DataTables::make(
            $request,
            Membership::query()
                ->select([
                    'id', 'gender', 'place_of_birth', 'date_of_birth',
                    'origin_address', 'current_address', 'user_id', 'study_program_id',
                    'created_at', 'updated_at',
                ])
                ->with([
                    'user:id,identity_number,name,email',
                    'studyProgram:id,study_program,cluster',
                    'latestLibraryClearance:id,membership_id,phone_number',
                ]),
            fn (Membership $membership, int $number) => [
                $number,
                $membership->id,
                $this->dash($membership->user?->identity_number),
                $this->dash($membership->user?->name),
                $this->dash($membership->studyProgram?->study_program),
                $this->dash($membership->gender),
                $this->dash($this->dateLabel($membership->date_of_birth)),
                $this->dash($membership->origin_address),
                $this->dash($membership->current_address),
                $this->dateLabel($membership->created_at),
                $this->dateLabel($membership->updated_at),
                $membership->user_id,
                $membership->study_program_id,
                $this->dash($membership->studyProgram?->cluster),
                $this->dash($membership->relatedPhoneNumber()),
                $this->dash($membership->user?->email),
                $membership->place_of_birth ?? '',
                optional($membership->date_of_birth)?->toDateString() ?? '',
            ],
            [
                'id' => 'id',
                'identity_number' => fn ($builder, string $dir) => DataTables::orderByRelated(
                    $builder,
                    'users',
                    'identity_number',
                    'memberships.user_id',
                    'id',
                    $dir
                ),
                'name' => fn ($builder, string $dir) => DataTables::orderByRelated(
                    $builder,
                    'users',
                    'name',
                    'memberships.user_id',
                    'id',
                    $dir
                ),
                'study_program' => fn ($builder, string $dir) => DataTables::orderByRelated(
                    $builder,
                    'study_programs',
                    'study_program',
                    'memberships.study_program_id',
                    'id',
                    $dir
                ),
                'gender' => 'gender',
                'date_of_birth' => 'date_of_birth',
                'place_of_birth' => 'place_of_birth',
            ],
            [],
            function ($query, string $keyword) {
                $like = KeywordSearch::matchPattern($keyword);
                $columns = KeywordSearch::matchColumns([
                    'gender',
                    'place_of_birth',
                    'date_of_birth',
                    'origin_address',
                    'current_address',
                ], $keyword);

                $query->where(function ($builder) use ($like, $keyword, $columns) {
                    foreach ($columns as $column) {
                        $builder->orWhere($column, 'like', $like);
                    }

                    KeywordSearch::orExists(
                        $builder,
                        'users',
                        'users.id',
                        'memberships.user_id',
                        ['name', 'identity_number', 'email'],
                        $keyword
                    );
                    KeywordSearch::orExists(
                        $builder,
                        'study_programs',
                        'study_programs.id',
                        'memberships.study_program_id',
                        ['study_program', 'cluster'],
                        $keyword
                    );
                    KeywordSearch::orExists(
                        $builder,
                        'library_clearances',
                        'library_clearances.membership_id',
                        'memberships.id',
                        ['phone_number'],
                        $keyword
                    );
                });
            }
        );
    }

    public function saveMembership($request): JsonResponse
    {
        $isCreate = ! $request->filled('membershipid');

        $request->merge([
            'place_of_birth' => trim((string) $request->input('place_of_birth')),
            'date_of_birth' => $this->toDatabaseDate($request->input('date_of_birth')),
        ]);

        $validated = $request->validate([
            'membershipid' => ['nullable', 'integer', 'exists:memberships,id'],
            'gender' => ['required', 'string', Rule::in(Membership::GENDERS)],
            'place_of_birth' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'origin_address' => ['required', 'string'],
            'current_address' => ['required', 'string'],
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('role', User::ROLE_STUDENT)
                ),
                $isCreate
                    ? Rule::unique('memberships', 'user_id')
                    : Rule::unique('memberships', 'user_id')->ignore($request->integer('membershipid')),
            ],
            'study_program_id' => ['required', 'integer', 'exists:study_programs,id'],
        ], [
            'gender.required' => 'Jenis kelamin harus dipilih!',
            'gender.in' => 'Jenis kelamin tidak valid!',
            'place_of_birth.required' => 'Tempat lahir harus diisi!',
            'date_of_birth.required' => 'Tanggal lahir harus diisi!',
            'date_of_birth.date' => 'Format tanggal lahir tidak valid!',
            'date_of_birth.before_or_equal' => 'Tanggal lahir tidak boleh melebihi hari ini!',
            'origin_address.required' => 'Alamat asal harus diisi!',
            'current_address.required' => 'Alamat sekarang harus diisi!',
            'user_id.required' => 'Mahasiswa harus dipilih!',
            'user_id.exists' => 'Mahasiswa tidak valid!',
            'user_id.unique' => 'Mahasiswa ini sudah terdaftar keanggotaan.',
            'study_program_id.required' => 'Program studi harus dipilih!',
            'study_program_id.exists' => 'Program studi tidak valid!',
        ]);

        $this->updateOrCreateMembership(
            $request->membershipid,
            $validated['gender'],
            $validated['place_of_birth'],
            $validated['date_of_birth'],
            $validated['origin_address'],
            $validated['current_address'],
            $validated['user_id'],
            $validated['study_program_id']
        );

        return response()->json('Data Keanggotaan Berhasil Disimpan');
    }

    public function destroyMembership($id): JsonResponse
    {
        if (! $this->deleteMembership((int) $id)) {
            return response()->json('Hapus Gagal', 404);
        }

        return response()->json('Hapus Keanggotaan Berhasil');
    }

    /**
     * Unduh formulir keanggotaan per mahasiswa (inline di tab baru).
     */
    public function exportMembershipLetterPdf(int $id)
    {
        $membership = $this->findMembershipForLetter($id);

        if (! $membership) {
            abort(404, 'Data keanggotaan tidak ditemukan.');
        }

        return $this->streamMembershipLetterPdf($membership);
    }

    /**
     * Unduh surat keanggotaan milik mahasiswa login.
     */
    public function exportPublicMembershipLetterPdf(int $id): RedirectResponse|Response
    {
        if ($redirect = $this->redirectStudentWithoutCompleteProof(Auth::guard('student')->user())) {
            return $redirect;
        }

        $membership = $this->findOwnedMembershipForStudent($id);

        if (! $membership) {
            return redirect()
                ->route('page-public-proof')
                ->with('error', 'Data keanggotaan tidak ditemukan.');
        }

        return $this->streamMembershipLetterPdf($membership);
    }

    protected function findMembershipForLetter(int $id): ?Membership
    {
        return Membership::query()
            ->with(['user', 'studyProgram', 'libraryClearances'])
            ->find($id);
    }

    protected function findOwnedMembershipForStudent(int $id): ?Membership
    {
        $userId = (int) (Auth::guard('student')->id() ?? 0);

        if ($userId <= 0) {
            return null;
        }

        return Membership::query()
            ->with(['user', 'studyProgram', 'libraryClearances'])
            ->whereKey($id)
            ->where('user_id', $userId)
            ->first();
    }

    protected function streamMembershipLetterPdf(Membership $membership): Response
    {
        $user = $membership->user;
        $program = $membership->studyProgram;
        $dateLabel = $membership->formattedLetterDate();

        if ($dateLabel === '') {
            $dateLabel = $this->formatTranslatedDate('l, j F Y', $this->currentDate());
        }

        $pdf = (new MembershipPdf)->build([
            'date_label' => $dateLabel,
            'student_name' => (string) ($user?->name ?? ''),
            'nim' => (string) ($user?->identity_number ?? ''),
            'gender' => (string) ($membership->gender ?? ''),
            'cluster' => (string) ($program?->cluster ?? ''),
            'study_program' => (string) ($program?->study_program ?? ''),
            'place_date_of_birth' => $membership->formattedPlaceDateOfBirth(),
            'origin_address' => (string) ($membership->origin_address ?? ''),
            'current_address' => (string) ($membership->current_address ?? ''),
            'phone_number' => $membership->relatedPhoneNumber(),
            'email' => (string) ($user?->email ?? ''),
            'logo_path' => public_path('images/logo.png'),
        ]);

        $nim = preg_replace('/\W+/', '', (string) ($user?->identity_number ?? 'surat')) ?: 'surat';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="surat-keanggotaan-'.$nim.'.pdf"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    // --- Formulir publik mahasiswa ---

    /**
     * Formulir keanggotaan mahasiswa (sekali, seperti serah).
     */
    public function showPublicMembershipForm(): View|RedirectResponse
    {
        $student = Auth::guard('student')->user();

        if ($this->studentHasMembership($student)) {
            return redirect()
                ->route($this->studentProgressRoute($student))
                ->with('error', $this->studentAlreadyRegisteredMembershipMessage($student));
        }

        $closedMessage = $this->studentMembershipClosedMessage($student);

        if ($closedMessage !== null) {
            return view('student.access', [
                'errorMessage' => $closedMessage,
            ]);
        }

        $studyPrograms = CatalogCache::studyPrograms()
            ->sortBy(fn ($program) => [(string) $program->cluster, (string) $program->study_program])
            ->values();

        [$birthDateStored, $birthDateDisplay] = $this->publicBirthDateFields(old('date_of_birth'));

        return view('student.membership', [
            'studyPrograms' => $studyPrograms,
            'student' => $student,
            'genders' => Membership::GENDERS,
            'birthDateStored' => $birthDateStored,
            'birthDateDisplay' => $birthDateDisplay,
        ]);
    }

    /**
     * Simpan keanggotaan dari formulir mahasiswa (satu kali per akun).
     */
    public function savePublicMembership(Request $request): RedirectResponse
    {
        $student = Auth::guard('student')->user();

        if ($this->studentHasMembership($student)) {
            return redirect()
                ->route($this->studentProgressRoute($student))
                ->with('error', $this->studentAlreadyRegisteredMembershipMessage($student));
        }

        $closedMessage = $this->studentMembershipClosedMessage($student);

        if ($closedMessage !== null) {
            return redirect()
                ->route('page-public-membership')
                ->with('error', $closedMessage);
        }

        if (! $student) {
            return redirect()
                ->route('page-public-membership')
                ->with('error', 'Akun mahasiswa tidak ditemukan. Silakan masuk kembali.');
        }

        $request->merge([
            'place_of_birth' => trim((string) $request->input('place_of_birth')),
            'date_of_birth' => $this->toDatabaseDate($request->input('date_of_birth')),
        ]);

        $validated = $request->validate([
            'gender' => ['required', 'string', Rule::in(Membership::GENDERS)],
            'place_of_birth' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'origin_address' => ['required', 'string'],
            'current_address' => ['required', 'string'],
            'study_program_id' => ['required', 'integer', 'exists:study_programs,id'],
        ], [
            'gender.required' => 'Jenis kelamin harus dipilih!',
            'gender.in' => 'Jenis kelamin tidak valid!',
            'place_of_birth.required' => 'Tempat lahir harus diisi!',
            'date_of_birth.required' => 'Tanggal lahir harus diisi!',
            'date_of_birth.date' => 'Format tanggal lahir tidak valid!',
            'date_of_birth.before_or_equal' => 'Tanggal lahir tidak boleh melebihi hari ini!',
            'origin_address.required' => 'Alamat asal harus diisi!',
            'current_address.required' => 'Alamat sekarang harus diisi!',
            'study_program_id.required' => 'Program studi harus dipilih!',
            'study_program_id.exists' => 'Program studi tidak valid!',
        ]);

        $lock = Cache::lock('public-membership:'.$student->id, 30);

        if (! $lock->get()) {
            return redirect()
                ->route('page-public-membership')
                ->withInput()
                ->with('error', 'Permintaan sedang diproses. Tunggu sebentar lalu coba lagi.');
        }

        try {
            DB::transaction(function () use ($validated, $student) {
                if (Membership::query()->where('user_id', $student->id)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages([
                        'gender' => $this->studentAlreadyRegisteredMembershipMessage($student),
                    ]);
                }

                $this->updateOrCreateMembership(
                    null,
                    $validated['gender'],
                    $validated['place_of_birth'],
                    $validated['date_of_birth'],
                    $validated['origin_address'],
                    $validated['current_address'],
                    $student->id,
                    $validated['study_program_id']
                );
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('page-public-membership')
                ->withInput()
                ->with('error', 'Terjadi kesalahan saat menyimpan data. Silakan periksa kembali dan coba lagi.');
        } finally {
            $lock->release();
        }

        if ($this->studentHasSubmittedProof($student)) {
            return redirect()
                ->route('page-public-proof')
                ->with('success', 'Data keanggotaan berhasil disimpan. Menu Bukti sudah tersedia.');
        }

        return redirect()
            ->route('page-public-submission')
            ->with('success', 'Data keanggotaan berhasil disimpan. Menu Bukti sudah tersedia. Lanjutkan penyerahan tugas akhir.');
    }

    // --- Tanggal lahir tampilan ---

    /**
     * @return array{0: string, 1: string}
     */
    private function publicBirthDateFields(mixed $value): array
    {
        if ($value === null || $value === '') {
            return ['', ''];
        }

        $stored = $this->toDatabaseDate($value);

        if (! $this->isDatabaseDate($stored)) {
            return ['', ''];
        }

        return [$stored, $this->formatTranslatedDate('j F Y', $stored)];
    }
}
