<?php

namespace App\Setup\Crud;

use App\Models\FinalProject;
use App\Models\Membership;
use App\Models\Setting;
use App\Support\CatalogCache;
use App\Support\DataTables;
use App\Support\FinalProjectStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * CRUD Penyerahan admin, formulir publik, dan bukti mahasiswa.
 * Anak dari BarcodeCrud. Controller: SubmissionController.
 * Surat hibah dan bebas pustaka diwarisi dari induk.
 */
class SubmissionCrud extends BarcodeCrud
{
    // --- Formulir publik dan simpan penyerahan ---

    /**
     * Formulir penyerahan tugas akhir (login role Mahasiswa).
     */
    public function showPublicSubmissionForm(): View|RedirectResponse
    {
        $student = Auth::guard('student')->user();

        if ($redirect = $this->redirectStudentIfAlreadySubmitted($student)) {
            return $redirect;
        }

        $closedMessage = $this->studentSubmissionClosedMessage($student);

        if ($closedMessage !== null) {
            return view('student.access', [
                'errorMessage' => $closedMessage,
            ]);
        }

        $studyPrograms = CatalogCache::studyPrograms()
            ->sortBy(fn ($program) => [(string) $program->cluster, (string) $program->study_program])
            ->values();

        $finalProjectTypes = CatalogCache::finalProjectTypes()
            ->sortBy(fn ($type) => [(string) $type->level, (string) $type->type])
            ->values();

        [$submissionDateStored, $submissionDateDisplay] = $this->publicSubmissionDateFields(
            old('submission_date', $this->currentDate()->toDateString())
        );

        return view('student.submission', [
            'studyPrograms' => $studyPrograms,
            'finalProjectTypes' => $finalProjectTypes,
            'submissionAccessOpen' => true,
            'student' => $student,
            'submissionDateStored' => $submissionDateStored,
            'submissionDateDisplay' => $submissionDateDisplay,
        ]);
    }

    /**
     * Simpan penyerahan publik: final__projects + book_grants + library_clearances (satu transaksi).
     */
    public function savePublicSubmission(Request $request): RedirectResponse
    {
        $student = Auth::guard('student')->user();

        if ($redirect = $this->redirectStudentIfAlreadySubmitted($student)) {
            return $redirect;
        }

        if ($redirect = $this->redirectStudentWithoutMembership($student)) {
            return $redirect;
        }

        $closedMessage = $this->studentSubmissionClosedMessage($student);

        if ($closedMessage !== null) {
            return redirect()
                ->route('page-public-submission')
                ->with('error', $closedMessage);
        }

        $accountNim = trim((string) ($student?->identity_number ?? ''));
        $accountName = trim((string) ($student?->name ?? ''));

        if ($accountNim === '' || $accountName === '') {
            return redirect()
                ->route('page-public-submission')
                ->with('error', 'Akun mahasiswa belum memiliki NIM atau nama. Hubungi petugas untuk melengkapi data akun sebelum menyerahkan tugas akhir.');
        }

        // Kunci identitas ke akun login agar modul bukti selalu cocok.
        // Tanggal penyerahan ditampilkan bahasa Indonesia, disimpan Y-m-d seperti role Petugas.
        $request->merge([
            'nim' => $accountNim,
            'student_name' => $accountName,
            'submission_date' => $this->toDatabaseDate($request->input('submission_date')),
        ]);

        $withoutBooks = $request->boolean('without_books');

        // Hibah buku bersifat opsional:
        // - tanpa buku → tidak menyimpan book_grants
        // - dengan buku → boleh 1 atau lebih, masing-masing berelasi ke final_project_id
        $books = $withoutBooks
            ? []
            : collect($request->input('books', []))
                ->filter(function ($book) {
                    if (! is_array($book)) {
                        return false;
                    }

                    return filled($book['author'] ?? null)
                        || filled($book['book_year'] ?? null)
                        || filled($book['book_title'] ?? null);
                })
                ->values()
                ->all();

        $request->merge([
            'without_books' => $withoutBooks ? 1 : 0,
            'books' => $books,
        ]);

        $rules = [
            'nim' => ['required', 'string', 'max:50'],
            'student_name' => ['required', 'string', 'min:3', 'max:255'],
            'program_study' => ['required', 'string', 'max:255', 'exists:study_programs,study_program'],
            'degree_level' => ['required', 'string', 'max:50', 'exists:final_project_types,level'],
            'final_project_type_id' => [
                'required',
                'integer',
                Rule::exists('final_project_types', 'id')->where(
                    fn ($query) => $query->where('level', $request->input('degree_level'))
                ),
            ],
            'final_project_year' => ['required', 'integer', 'min:'.FinalProject::YEAR_MIN, 'max:'.FinalProject::YEAR_MAX],
            'submission_date' => ['required', 'date'],
            'final_project_title' => ['required', 'string'],
            'final_project_file' => ['required', 'file', 'mimes:pdf'],
            'approval_sheet' => ['required', 'file', 'mimes:pdf'],
            'email' => ['required', 'email', 'max:255'],
            'phone_number' => ['required', 'string', 'max:30'],
            'graduation_year' => ['required', 'integer', 'min:'.FinalProject::YEAR_MIN, 'max:'.FinalProject::YEAR_MAX],
            'semester_graduated' => ['required', 'integer', 'min:1', 'max:20'],
            'without_books' => ['nullable', 'boolean'],
            'books' => [$withoutBooks ? 'nullable' : 'required', 'array', $withoutBooks ? 'max:0' : 'min:1'],
            'books.*.author' => [$withoutBooks ? 'nullable' : 'required', 'string', 'max:255'],
            'books.*.book_year' => [$withoutBooks ? 'nullable' : 'required', 'integer', 'min:'.FinalProject::YEAR_MIN, 'max:'.FinalProject::YEAR_MAX],
            'books.*.book_title' => [$withoutBooks ? 'nullable' : 'required', 'string', 'max:255'],
        ];

        $messages = [
            'nim.required' => 'NIM harus diisi!',
            'student_name.required' => 'Nama mahasiswa harus diisi!',
            'student_name.min' => 'Nama mahasiswa minimal 3 karakter!',
            'program_study.required' => 'Program studi harus dipilih!',
            'program_study.exists' => 'Program studi tidak valid!',
            'degree_level.required' => 'Jenjang harus dipilih!',
            'degree_level.exists' => 'Jenjang tidak valid!',
            'final_project_type_id.required' => 'Jenjang harus dipilih!',
            'final_project_type_id.exists' => 'Jenjang tidak valid!',
            'final_project_year.required' => 'Tahun tugas akhir harus diisi!',
            'submission_date.required' => 'Tanggal penyerahan harus diisi!',
            'submission_date.date' => 'Format tanggal penyerahan tidak valid!',
            'final_project_title.required' => 'Judul tugas akhir harus diisi!',
            'final_project_file.required' => 'Berkas tugas akhir harus diunggah!',
            'final_project_file.mimes' => 'Berkas tugas akhir harus berformat PDF!',
            'approval_sheet.required' => 'Lembar persetujuan harus diunggah!',
            'approval_sheet.mimes' => 'Lembar persetujuan harus berformat PDF!',
            'email.required' => 'Email harus diisi!',
            'email.email' => 'Format email tidak valid!',
            'phone_number.required' => 'Nomor telepon harus diisi!',
            'graduation_year.required' => 'Tahun lulus harus diisi!',
            'semester_graduated.required' => 'Semester lulus harus diisi!',
            'books.required' => 'Isi minimal satu hibah buku, atau centang Tanpa Buku.',
            'books.min' => 'Isi minimal satu hibah buku, atau centang Tanpa Buku.',
            'books.max' => 'Centang Tanpa Buku berarti tidak boleh mengisi data buku.',
            'books.*.author.required' => 'Pengarang buku harus diisi!',
            'books.*.book_year.required' => 'Tahun buku harus diisi!',
            'books.*.book_title.required' => 'Judul buku harus diisi!',
        ];

        $validated = $request->validate($rules, $messages);

        $lock = Cache::lock('public-submission:'.sha1($accountNim), 30);

        if (! $lock->get()) {
            return redirect()
                ->route('page-public-submission')
                ->withInput()
                ->with('error', 'Permintaan sedang diproses. Tunggu sebentar lalu coba lagi.');
        }

        try {
            DB::transaction(function () use ($request, $validated, $withoutBooks) {
                if (FinalProject::existsForNim($validated['nim'], true)) {
                    throw ValidationException::withMessages([
                        'nim' => $this->studentAlreadySubmittedMessage(),
                    ]);
                }

                $finalProjectFile = $this->uploadToPublicStorage(
                    $request->file('final_project_file'),
                    $this->documentDirectoryFor(
                        FinalProjectStorage::DOCUMENT_FINAL_PROJECT,
                        $validated['degree_level'],
                        $validated['final_project_year'],
                        $validated['program_study'],
                        $validated['final_project_type_id'] ?? null
                    ),
                    $validated['student_name'],
                    $validated['nim']
                );

                $approvalSheet = $this->uploadToPublicStorage(
                    $request->file('approval_sheet'),
                    $this->documentDirectoryFor(
                        FinalProjectStorage::DOCUMENT_APPROVAL_SHEET,
                        $validated['degree_level'],
                        $validated['final_project_year'],
                        $validated['program_study'],
                        $validated['final_project_type_id'] ?? null
                    ),
                    $validated['student_name'],
                    $validated['nim']
                );

                $finalProject = $this->updateOrCreateFinalProject(
                    null,
                    $validated['nim'],
                    $validated['student_name'],
                    $validated['final_project_title'],
                    $validated['program_study'],
                    $validated['degree_level'],
                    $validated['final_project_year'],
                    $validated['submission_date'],
                    $finalProjectFile,
                    $approvalSheet,
                    $validated['final_project_type_id']
                );

                // Relasi hibah buku → final__projects (boleh 0 / 1 / banyak)
                if (! $withoutBooks) {
                    foreach ($validated['books'] ?? [] as $book) {
                        $this->updateOrCreateBookGrant(
                            null,
                            $book['book_title'],
                            $book['book_year'],
                            $book['author'],
                            null,
                            $finalProject->id
                        );
                    }
                }

                $this->updateOrCreateLibraryClearance(
                    null,
                    $validated['email'],
                    $validated['phone_number'],
                    $validated['graduation_year'],
                    $validated['semester_graduated'],
                    null,
                    $finalProject->id
                );
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('page-public-submission')
                ->withInput()
                ->with('error', 'Terjadi kesalahan saat menyimpan data. Silakan periksa kembali dan coba lagi.');
        } finally {
            $lock->release();
        }

        $bookCount = $withoutBooks ? 0 : count($validated['books'] ?? []);
        $successMessage = $withoutBooks
            ? 'Data tugas akhir dan bebas pustaka berhasil disimpan. Hibah buku tidak disertakan.'
            : (
                $bookCount === 1
                    ? 'Data tugas akhir, 1 hibah buku, dan bebas pustaka berhasil disimpan.'
                    : 'Data tugas akhir, '.$bookCount.' hibah buku, dan bebas pustaka berhasil disimpan.'
            );

        return redirect()
            ->route('page-public-proof')
            ->with('success', $successMessage);
    }

    // --- Halaman bukti ---

    /**
     * Halaman bukti mahasiswa: tampil jika anggota atau serah sudah diisi (petugas/mahasiswa).
     */
    public function showPublicSubmissionProof(): View|RedirectResponse
    {
        $user = Auth::guard('student')->user();

        if ($redirect = $this->redirectStudentWithoutCompleteProof($user)) {
            return $redirect;
        }

        $membership = $user
            ? Membership::query()
                ->where('user_id', $user->id)
                ->with(['studyProgram', 'user', 'libraryClearances'])
                ->first()
            : null;

        $hasSubmission = $this->studentHasSubmittedProof($user);

        return view('student.proof', [
            'student' => $user,
            'membership' => $membership,
            'accessBlocked' => ! $hasSubmission,
            'accessBadge' => null,
            'proofIndexUrl' => route('index-public-proof'),
            'proofDetailUrlTemplate' => url('bukti').'/',
        ]);
    }

    /**
     * DataTables server-side: daftar bukti penyerahan milik mahasiswa login.
     */
    public function listPublicSubmissionProof(Request $request): JsonResponse
    {
        $user = Auth::guard('student')->user();
        $nim = $this->studentCanViewProof($user)
            ? trim((string) ($user?->identity_number ?? ''))
            : '';

        $columns = [
            'id',
            'nim',
            'student_name',
            'final_project_title',
            'program_study',
            'degree_level',
            'final_project_year',
            'submission_date',
        ];

        $query = FinalProject::query()
            ->select($columns)
            ->when($nim === '', fn ($builder) => $builder->whereRaw('0 = 1'))
            ->when($nim !== '', fn ($builder) => $builder->where('nim', $nim));

        return $this->publicDataTablesResponse(
            $request,
            $query,
            function (FinalProject $project, int $number) {
                return [
                    $number,
                    $project->nim ?: '-',
                    $project->student_name ?: '-',
                    $project->final_project_title ?: '-',
                    $project->program_study ?: '-',
                    $project->degree_level ?: '-',
                    $project->final_project_year ?: '-',
                    $this->formatTranslatedDate('j F Y', $project->submission_date),
                    $project->id,
                ];
            },
            [
                'id' => 'id',
                'nim' => 'nim',
                'student_name' => 'student_name',
                'final_project_title' => 'final_project_title',
                'program_study' => 'program_study',
                'degree_level' => 'degree_level',
                'final_project_year' => 'final_project_year',
                'submission_date' => 'submission_date',
            ],
            'submission_date',
            'desc',
            null,
            [
                'nim',
                'student_name',
                'final_project_title',
                'program_study',
                'degree_level',
                'final_project_year',
                'submission_date',
            ]
        );
    }

    // --- Detail bukti ---

    /**
     * Detail bukti penyerahan: data library_clearances + book_grants terkait tugas akhir.
     */
    public function showPublicSubmissionProofDetail(int $id): View|RedirectResponse
    {
        $user = Auth::guard('student')->user();

        if ($redirect = $this->redirectStudentWithoutCompleteProof($user)) {
            return $redirect;
        }

        $nim = trim((string) ($user->identity_number ?? ''));

        $finalProject = FinalProject::query()
            ->where('id', $id)
            ->where('nim', $nim)
            ->with(['bookGrants', 'libraryClearances', 'finalProjectType', 'studyProgram'])
            ->first();

        if (! $finalProject) {
            return redirect()
                ->route('page-public-proof')
                ->with('error', 'Data bukti penyerahan tidak ditemukan.');
        }

        return view('student.proof-detail', [
            'finalProject' => $finalProject,
            'bookGrants' => $finalProject->bookGrants,
            'libraryClearances' => $finalProject->libraryClearances,
            'membership' => Membership::query()
                ->where('user_id', $user->id)
                ->with(['studyProgram', 'user', 'libraryClearances'])
                ->first(),
            'student' => $user,
        ]);
    }

    // --- Akses formulir penyerahan ---

    /**
     * Status akses formulir penyerahan publik (untuk UI petugas).
     */
    public function submissionAccessStatus(): JsonResponse
    {
        return $this->accessStatusResponse([Setting::class, 'isSubmissionAccessOpen']);
    }

    /**
     * Buka / tutup akses formulir penyerahan publik.
     */
    public function toggleSubmissionAccess(Request $request): JsonResponse
    {
        return $this->toggleAccessResponse(
            $request,
            [Setting::class, 'setSubmissionAccessOpen'],
            [Setting::class, 'isSubmissionAccessOpen'],
            'Akses formulir penyerahan dibuka.',
            'Akses formulir penyerahan ditutup.'
        );
    }

    // --- Tanggal penyerahan tampilan ---

    /**
     * @return array{0: string, 1: string}
     */
    private function publicSubmissionDateFields(mixed $value): array
    {
        $stored = $this->toDatabaseDate($value);

        if (! is_string($stored) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $stored)) {
            $stored = $this->currentDate()->toDateString();
        }

        return [$stored, $this->formatTranslatedDate('j F Y', $stored)];
    }

    // --- CRUD admin (delegasi ke tugas akhir) ---

    public function listSubmissions(Request $request): JsonResponse
    {
        return $this->listFinalProjects($request, null);
    }

    public function saveSubmission($request): JsonResponse
    {
        $this->persistFinalProjectFromRequest($request, 'submissionid');

        return response()->json('Data Penyerahan Berhasil Disimpan');
    }

    public function destroySubmission($id): JsonResponse
    {
        if (! $this->removeFinalProject((int) $id)) {
            return response()->json('Hapus Gagal', 404);
        }

        return response()->json('Hapus Penyerahan Berhasil');
    }
}
