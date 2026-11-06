<?php

namespace App\Setup;

use App\Models\Archive;
use App\Models\Barcode;
use App\Models\BookGrant;
use App\Models\Destruction;
use App\Models\FinalProject;
use App\Models\FinalProjectType;
use App\Models\LibraryClearance;
use App\Models\Membership;
use App\Models\Report;
use App\Models\StudyProgram;
use App\Models\User;
use App\Models\Visitor;
use App\Support\CatalogCache;
use App\Support\DataTables;
use App\Support\FinalProjectStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Setup data: cache baris, berkas publik, dan simpan model.
 * Anak dari Manager. Induk dari Crud.
 */
class Loader extends Manager
{
    /**
     * Cache id Program Studi berdasarkan nama (untuk impor batch).
     *
     * @var array<string, int|null>|null
     */
    protected ?array $studyProgramIdByName = null;

    /**
     * Cache id Jenis TA berdasarkan jenjang/level.
     *
     * @var array<string, int|null>|null
     */
    protected ?array $finalProjectTypeIdByLevel = null;

    /**
     * Cache model Jenis TA berdasarkan id.
     *
     * @var array<int, FinalProjectType|null>|null
     */
    protected ?array $finalProjectTypeById = null;

    /**
     * Cache id Pengguna berdasarkan nama.
     *
     * @var array<string, int|null>|null
     */
    protected ?array $userIdByName = null;

    // --- Tugas Akhir ---

    protected function resolveStudyProgramId(?string $programStudy): ?int
    {
        if ($programStudy === null || $programStudy === '') {
            return null;
        }

        $this->studyProgramIdByName ??= StudyProgram::query()
            ->pluck('id', 'study_program')
            ->all();

        return $this->studyProgramIdByName[$programStudy] ?? null;
    }

    protected function resolveFinalProjectTypeId(?string $degreeLevel): ?int
    {
        if ($degreeLevel === null || $degreeLevel === '') {
            return null;
        }

        // Satu level bisa punya beberapa jenis tugas akhir — ambil id terkecil secara deterministik.
        $this->finalProjectTypeIdByLevel ??= [];

        if (! array_key_exists($degreeLevel, $this->finalProjectTypeIdByLevel)) {
            $this->finalProjectTypeIdByLevel[$degreeLevel] = FinalProjectType::query()
                ->where('level', $degreeLevel)
                ->orderBy('id')
                ->value('id');
        }

        return $this->finalProjectTypeIdByLevel[$degreeLevel];
    }

    protected function findFinalProjectType(?int $id): ?FinalProjectType
    {
        if (! $id) {
            return null;
        }

        $this->finalProjectTypeById ??= [];

        if (! array_key_exists($id, $this->finalProjectTypeById)) {
            $this->finalProjectTypeById[$id] = FinalProjectType::query()->find($id);
        }

        return $this->finalProjectTypeById[$id];
    }

    /**
     * @return array{type: string, level: string}
     */
    protected function resolveFinalProjectTypeMeta(mixed $typeId, mixed $degreeLevel): array
    {
        $typeId = filled($typeId) ? (int) $typeId : null;
        $degreeLevel = trim((string) $degreeLevel);
        $row = $this->findFinalProjectType($typeId);

        if (! $row && $degreeLevel !== '') {
            $row = $this->findFinalProjectType($this->resolveFinalProjectTypeId($degreeLevel));
        }

        return [
            'type' => (string) ($row?->type ?? ''),
            'level' => (string) ($row?->level ?: $degreeLevel),
        ];
    }

    protected function documentDirectoryFor(
        string $kind,
        mixed $level,
        mixed $year,
        mixed $programStudy,
        mixed $typeId = null
    ): string {
        return FinalProjectStorage::documentDirectoryFromCatalog(
            $kind,
            $typeId,
            $level,
            $year,
            $programStudy,
            $this->resolveStudyProgramId(trim((string) $programStudy) ?: null)
        );
    }

    protected function resolveUserIdByName(?string $name): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }

        $this->userIdByName ??= User::query()
            ->pluck('id', 'name')
            ->all();

        return $this->userIdByName[$name] ?? null;
    }

    /**
     * Path berkas tugas akhir. Indeks 8 = dokumen, 9 = lembar pengesahan.
     *
     * @return list<array<int, mixed>>
     */
    public function findFinalProjectById($id): array
    {
        $project = FinalProject::query()->find($id, ['id', 'final_project_file', 'approval_sheet']);

        if (! $project) {
            return [];
        }

        $row = array_fill(0, 10, null);
        $row[8] = $project->final_project_file;
        $row[9] = $project->approval_sheet;

        return [$row];
    }

    /**
     * Field tampilan umum dari relasi FinalProject (nim, nama, prodi, jenjang, …).
     *
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string, 5: mixed, 6: mixed}
     */
    protected function mapFinalProjectDisplayFields(?FinalProject $finalProject): array
    {
        return [
            $finalProject?->nim ?? '',
            $finalProject?->student_name ?? '',
            $finalProject?->studyProgram?->cluster ?? '',
            $finalProject?->program_study
                ?: ($finalProject?->studyProgram?->study_program ?? ''),
            $finalProject?->degree_level ?? '',
            $finalProject?->final_project_year,
            $finalProject?->submission_date,
        ];
    }

    // --- Berkas publik ---

    public function downloadFromPublicStorage(string $path, $name)
    {
        return FinalProjectStorage::download($path, (string) $name);
    }

    public function inlineFromPublicStorage(string $path, $name)
    {
        return FinalProjectStorage::inline($path, (string) $name);
    }

    public function existsInPublicStorage(string $path): bool
    {
        return FinalProjectStorage::exists($path);
    }

    public function deleteFromPublicStorage(string $path): bool
    {
        return FinalProjectStorage::delete($path);
    }

    public function sizeInPublicStorage(string $path): int
    {
        return FinalProjectStorage::size($path);
    }

    public function uploadToPublicStorage(UploadedFile $file, string $path, string $studentName = '', string $nim = '')
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => $file->getErrorMessage() ?: 'Unggahan berkas gagal. Periksa ukuran dan jenis berkas.',
            ]);
        }

        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'pdf';
        $fileName = FinalProjectStorage::storedFileName($studentName, $nim, (string) $extension);

        $stored = FinalProjectStorage::putFileAs($path, $file, $fileName);

        if ($stored === false) {
            throw ValidationException::withMessages([
                'file' => 'Gagal menyimpan berkas. Periksa ruang penyimpanan, lalu coba lagi.',
            ]);
        }

        return $stored;
    }

    /**
     * Create atau update berdasarkan id tanpa SELECT `WHERE id IS NULL`
     * (pola updateOrCreate(['id' => null]) yang mahal saat insert massal).
     *
     * @param  class-string<Model>  $modelClass
     */
    protected function persistById(string $modelClass, mixed $id, array $attributes): Model
    {
        $id = filled($id) ? (int) $id : null;

        if ($id) {
            $model = $modelClass::query()->find($id);

            if ($model) {
                $model->fill($attributes)->save();
                DataTables::touch();

                return $model;
            }
        }

        $created = $modelClass::query()->create($attributes);
        DataTables::touch();

        return $created;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function deleteByKey(string $modelClass, int $id): bool
    {
        $deleted = $modelClass::query()->whereKey($id)->delete() > 0;

        if ($deleted) {
            DataTables::touch();
        }

        return $deleted;
    }

    public function updateOrCreateFinalProject(
        $id, $nim, $student_name, $final_project_title, $program_study,
        $degree_level, $final_project_year, $submission_date,
        $final_project_file, $approval_sheet, $final_project_type_id = null
    ) {
        // Hubungkan foreign key Program Studi berdasarkan nama yang dipilih
        $study_program_id = $this->resolveStudyProgramId($program_study);

        // Hubungkan foreign key Jenis TA: utamakan id dari request/navigasi,
        // fallback cocokkan berdasarkan jenjang (level)
        $final_project_type_id = $final_project_type_id
            ?: $this->resolveFinalProjectTypeId($degree_level);

        $project = $this->persistById(FinalProject::class, $id, compact(
            'nim', 'student_name', 'final_project_title', 'program_study',
            'degree_level', 'final_project_year', 'submission_date',
            'final_project_file', 'approval_sheet', 'final_project_type_id',
            'study_program_id'
        ));

        FinalProjectStorage::ensureProjectFolders(
            $degree_level,
            $final_project_year,
            $program_study,
            null,
            $final_project_type_id,
            $study_program_id
        );

        CatalogCache::forgetDashboard();

        return $project;
    }

    public function deleteFinalProject(int $id): int
    {
        return (int) DB::transaction(function () use ($id) {
            BookGrant::query()->where('final_project_id', $id)->delete();
            LibraryClearance::query()->where('final_project_id', $id)->delete();
            Destruction::query()->where('final_project_id', $id)->delete();
            Archive::query()->where('final_project_id', $id)->delete();
            Report::query()->where('final_project_id', $id)->delete();
            Barcode::query()->where('final_project_id', $id)->delete();

            $deleted = (int) FinalProject::query()->whereKey($id)->delete();

            if ($deleted > 0) {
                DataTables::touch();
                CatalogCache::forgetDashboard();
            }

            return $deleted;
        });
    }

    // --- Program Studi ---

    public function updateOrCreateStudyProgram($id, $study_program, $cluster)
    {
        $program = $this->persistById(StudyProgram::class, $id, compact('study_program', 'cluster'));
        CatalogCache::forgetStudyPrograms();
        FinalProjectStorage::forgetCachedDisk();
        FinalProjectStorage::syncFoldersFromDatabase();

        return $program;
    }

    public function deleteStudyProgram(int $id): int
    {
        $deleted = (int) StudyProgram::query()->whereKey($id)->delete();

        if ($deleted > 0) {
            DataTables::touch();
            CatalogCache::forgetStudyPrograms();
        }

        return $deleted;
    }

    // --- Keanggotaan ---

    public function updateOrCreateMembership(
        $id,
        $gender,
        $place_of_birth,
        $date_of_birth,
        $origin_address,
        $current_address,
        $user_id,
        $study_program_id
    ) {
        $membership = $this->persistById(Membership::class, $id, compact(
            'gender',
            'place_of_birth',
            'date_of_birth',
            'origin_address',
            'current_address',
            'user_id',
            'study_program_id'
        ));

        $this->syncMembershipRelations($membership->fresh(['user']));

        return $membership;
    }

    public function deleteMembership(int $id): bool
    {
        $deleted = Membership::query()->whereKey($id)->delete() > 0;

        if ($deleted) {
            DataTables::touch();
        }

        return $deleted;
    }

    /**
     * Tautkan bebas pustaka & hibah buku milik NIM akun ke keanggotaan,
     * lalu set membership_registered menjadi Terdaftar.
     */
    protected function syncMembershipRelations(Membership $membership): void
    {
        $nim = trim((string) ($membership->user?->identity_number ?? ''));

        if ($nim === '' || ! $membership->id) {
            return;
        }

        $projectIds = FinalProject::query()
            ->where('nim', $nim)
            ->pluck('id')
            ->all();

        if ($projectIds === []) {
            return;
        }

        LibraryClearance::query()
            ->whereIn('final_project_id', $projectIds)
            ->update([
                'membership_id' => $membership->id,
                'membership_registered' => 'Terdaftar',
            ]);

        BookGrant::query()
            ->whereIn('final_project_id', $projectIds)
            ->update([
                'membership_id' => $membership->id,
                'membership_registered' => 'Terdaftar',
            ]);
    }

    /**
     * Jika mahasiswa pemilik tugas akhir sudah punya keanggotaan,
     * pakai relasi itu dan status Terdaftar. Jika tidak, pakai nilai manual.
     *
     * @return array{0: int|null, 1: mixed}
     */
    protected function applyMembershipRelation(mixed $finalProjectId, mixed $membershipRegistered): array
    {
        $membership = $this->resolveMembershipForFinalProject($finalProjectId);

        if ($membership) {
            return [$membership->id, 'Terdaftar'];
        }

        return [null, $membershipRegistered];
    }

    protected function resolveMembershipForFinalProject(mixed $finalProjectId): ?Membership
    {
        $finalProjectId = filled($finalProjectId) ? (int) $finalProjectId : 0;

        if ($finalProjectId <= 0) {
            return null;
        }

        $nim = trim((string) (FinalProject::query()->whereKey($finalProjectId)->value('nim') ?? ''));

        if ($nim === '') {
            return null;
        }

        $userId = User::query()
            ->where('identity_number', $nim)
            ->where('role', User::ROLE_STUDENT)
            ->value('id');

        if (! $userId) {
            return null;
        }

        return Membership::query()->where('user_id', $userId)->first();
    }

    // --- Jenis Tugas Akhir ---

    public function updateOrCreateFinalProjectType($id, $level, $type)
    {
        $record = $this->persistById(FinalProjectType::class, $id, compact('level', 'type'));
        CatalogCache::forgetFinalProjectTypes();
        FinalProjectStorage::forgetCachedDisk();
        FinalProjectStorage::syncFoldersFromDatabase();

        return $record;
    }

    public function deleteFinalProjectType(int $id): int
    {
        $deleted = (int) FinalProjectType::query()->whereKey($id)->delete();

        if ($deleted > 0) {
            DataTables::touch();
            CatalogCache::forgetFinalProjectTypes();
        }

        return $deleted;
    }

    // --- Pengunjung ---

    public function updateOrCreateVisitor(
        $id,
        $nim,
        $student_name,
        $program_study,
        $needs,
        $supervision,
        $user_id = null
    ) {
        $study_program_id = $this->resolveStudyProgramId($program_study);
        $user_id ??= $this->resolveUserIdByName($supervision);

        $visitor = $this->persistById(Visitor::class, $id, compact(
            'nim',
            'student_name',
            'program_study',
            'needs',
            'supervision',
            'study_program_id',
            'user_id'
        ));

        CatalogCache::forgetDashboard();
        CatalogCache::forgetVisitorCount();

        return $visitor;
    }

    public function deleteVisitor(int $id): int
    {
        $deleted = (int) Visitor::query()->whereKey($id)->delete();

        if ($deleted > 0) {
            DataTables::touch();
            CatalogCache::forgetDashboard();
            CatalogCache::forgetVisitorCount();
        }

        return $deleted;
    }

    // --- Hibah Buku ---

    public function updateOrCreateBookGrant(
        $id,
        $book_title,
        $book_year,
        $author,
        $membership_registered,
        $final_project_id
    ) {
        [$membership_id, $membership_registered] = $this->applyMembershipRelation(
            $final_project_id,
            $membership_registered
        );

        return $this->persistById(BookGrant::class, $id, compact(
            'book_title',
            'book_year',
            'author',
            'membership_registered',
            'final_project_id',
            'membership_id'
        ));
    }

    public function deleteBookGrant(int $id): bool
    {
        return $this->deleteByKey(BookGrant::class, $id);
    }

    // --- Bebas Pustaka ---

    public function updateOrCreateLibraryClearance(
        $id,
        $email,
        $phone_number,
        $graduation_year,
        $semester_graduated,
        $membership_registered,
        $final_project_id
    ) {
        [$membership_id, $membership_registered] = $this->applyMembershipRelation(
            $final_project_id,
            $membership_registered
        );

        return $this->persistById(LibraryClearance::class, $id, compact(
            'email',
            'phone_number',
            'graduation_year',
            'semester_graduated',
            'membership_registered',
            'final_project_id',
            'membership_id'
        ));
    }

    public function deleteLibraryClearance(int $id): bool
    {
        return $this->deleteByKey(LibraryClearance::class, $id);
    }

    // --- Pemusnahan ---

    public function updateOrCreateDestruction(
        $id,
        $description,
        $agreement,
        $final_project_id,
        $user_id
    ) {
        return $this->persistById(Destruction::class, $id, compact(
            'description',
            'agreement',
            'final_project_id',
            'user_id'
        ));
    }

    public function deleteDestruction(int $id): bool
    {
        return $this->deleteByKey(Destruction::class, $id);
    }

    // --- Arsip ---

    public function updateOrCreateArchive(
        $id,
        $description,
        $final_project_id
    ) {
        return $this->persistById(Archive::class, $id, compact(
            'description',
            'final_project_id'
        ));
    }

    public function deleteArchive(int $id): bool
    {
        return $this->deleteByKey(Archive::class, $id);
    }

    // --- Laporan ---

    public function updateOrCreateReport(
        $id,
        $description,
        $final_project_id,
        $user_id
    ) {
        return $this->persistById(Report::class, $id, compact(
            'description',
            'final_project_id',
            'user_id'
        ));
    }

    public function deleteReport(int $id): bool
    {
        return $this->deleteByKey(Report::class, $id);
    }

    // --- Pengguna ---

    public function findUserById($id): ?User
    {
        return User::query()->find($id);
    }

    public function updateOrCreateUser(
        $id,
        $identityNumber,
        $name,
        $email,
        $password,
        $role
    ) {
        $attributes = [
            'identity_number' => $identityNumber,
            'name' => $name,
            'email' => $email,
            'role' => $role,
        ];

        // Password hanya diisi saat create atau saat user mengirim nilai baru.
        // Cast 'hashed' pada model akan mengenkripsi otomatis (Laravel Hash).
        if ($password !== null && $password !== '') {
            $attributes['password'] = $password;
        }

        return $this->persistById(User::class, $id, $attributes);
    }

    public function deleteUser(int $id): bool
    {
        return $this->deleteByKey(User::class, $id);
    }

    // --- Kode Batang ---

    /**
     * Cari barcode beserta relasi tugas akhir & bebas pustaka.
     */
    public function findBarcodeByCode(string $code): ?Barcode
    {
        return Barcode::query()
            ->where('barcode', $code)
            ->with([
                'finalProject.studyProgram:id,study_program,cluster',
                'finalProject.finalProjectType:id,type,level',
                'finalProject.latestLibraryClearance',
            ])
            ->first();
    }

    public function updateOrCreateBarcode(
        $id,
        $label,
        $barcode,
        $final_project_id
    ) {
        return $this->persistById(Barcode::class, $id, compact(
            'label',
            'barcode',
            'final_project_id'
        ));
    }

    public function deleteBarcode(int $id): bool
    {
        return $this->deleteByKey(Barcode::class, $id);
    }
}
