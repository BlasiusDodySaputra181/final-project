<?php

namespace App\Setup\Crud;

use App\Models\ArtificialIntelligence;
use App\Models\FinalProject;
use App\Models\FinalProjectType;
use App\Models\StudyProgram;
use App\Setup\Crud;
use App\Support\CatalogCache;
use App\Support\DataTables;
use App\Support\FinalProjectStorage;
use App\Support\KeywordSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Final project CRUD, import, and file download.
 * Extends Crud. Controllers: FinalProjectController, ImportController.
 */
class FinalProjectCrud extends Crud
{
    // --- File columns on a final project row ---

    /**
     * Column index in a final project row array.
     */
    private const FILE_COLUMN = 8;

    private const APPROVAL_SHEET_COLUMN = 9;

    // --- Read, upload, and assemble data ---

    /**
     * Final project page for one type (sidebar child).
     */
    public function showFinalProjectPage(string $type): View
    {
        $finalProjectType = FinalProjectType::findBySlug($type);

        abort_unless($finalProjectType, 404);

        return view('admin.finalproject.index', compact('finalProjectType'));
    }

    /**
     * List final projects, filtered by type when the request includes type_id.
     */
    public function listFinalProjectsForPage(Request $request): JsonResponse
    {
        $typeId = $request->filled('type_id')
            ? $request->integer('type_id')
            : null;

        return $this->listFinalProjects($request, $typeId);
    }

    /**
     * Final project options for a select (limited server-side search).
     */
    public function listFinalProjectOptions(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->input('q', ''));
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('ids', [])),
            static fn (int $id): bool => $id > 0
        )));
        $limit = min(50, max(1, (int) $request->input('limit', 30)));

        $query = FinalProject::query()
            ->select(['id', 'nim', 'student_name'])
            ->orderByDesc('id');

        if ($request->boolean('expired')) {
            $query->pastValidityPeriod();
        }

        if ($keyword !== '') {
            if (preg_match('/^\d{2,}$/', $keyword) === 1) {
                $query->where('nim', 'like', KeywordSearch::prefixPattern($keyword));
            } else {
                KeywordSearch::finalProjects($query, $keyword);
            }
        }

        $rows = $query->limit($limit)->get();

        if ($ids !== []) {
            $missing = array_values(array_diff($ids, $rows->pluck('id')->all()));

            if ($missing !== []) {
                $extra = FinalProject::query()
                    ->select(['id', 'nim', 'student_name'])
                    ->whereIn('id', $missing)
                    ->when(
                        $request->boolean('expired'),
                        fn ($builder) => $builder->pastValidityPeriod()
                    )
                    ->get();

                $rows = $extra->concat($rows);
            }
        }

        return response()->json($rows->map(static fn ($finalProject): array => [
            'id' => $finalProject->id,
            'value' => $finalProject->id,
            'label' => trim(($finalProject->nim ?: '-').' — '.($finalProject->student_name ?: '-')),
        ])->values());
    }

    public function listFinalProjects(Request $request, ?int $finalProjectTypeId = null): JsonResponse
    {
        $query = FinalProject::query()->select(FinalProject::LISTING_COLUMNS);

        if ($finalProjectTypeId !== null) {
            $query->where('final_project_type_id', $finalProjectTypeId);
        }

        return DataTables::make(
            $request,
            $query,
            fn (FinalProject $project, int $number) => [
                $number,
                $project->id,
                $project->nim,
                $project->student_name,
                $project->final_project_title,
                $project->program_study,
                $project->degree_level,
                $project->final_project_year,
                $this->dateLabel($project->submission_date),
                $this->validityLabel($project->final_project_year),
                $project->final_project_file,
                $project->approval_sheet,
                $this->dateLabel($project->created_at),
                $this->dateLabel($project->updated_at),
                $project->final_project_type_id,
            ],
            [
                'id' => 'id',
                'nim' => 'nim',
                'student_name' => 'student_name',
                'program_study' => 'program_study',
                'status' => 'final_project_year',
                'final_project_year' => 'final_project_year',
            ],
            [],
            fn ($builder, string $keyword) => KeywordSearch::finalProjects($builder, $keyword)
        );
    }

    /**
     * Save the new upload, then delete the old file. A failed upload keeps the previous file.
     */
    private function replaceUploadedFile($request, string $key, string $path, ?string $previousFile = null): ?string
    {
        if (! $request->hasFile($key)) {
            return $previousFile;
        }

        $uploaded = $this->uploadToPublicStorage(
            $request->file($key),
            $path,
            (string) $request->input('student_name', ''),
            (string) $request->input('nim', '')
        );

        if (! empty($previousFile) && $previousFile !== $uploaded && $this->existsInPublicStorage($previousFile)) {
            $this->deleteFromPublicStorage($previousFile);
        }

        return $uploaded;
    }

    public function readCoverInformation($request): JsonResponse
    {
        if (! ArtificialIntelligence::isStored()) {
            return response()->json([
                'message' => ArtificialIntelligence::missingStorageMessage(),
            ], 422);
        }

        $request->validate([
            'text' => ['required', 'string', 'max:100000'],
        ], [
            'text.required' => 'Teks sampul kosong. Format tugas akhir tidak berisi teks yang dapat dibaca.',
        ]);

        $text = $request->string('text')->trim()->toString();

        if ($text === '') {
            throw ValidationException::withMessages([
                'text' => 'Teks sampul kosong. Format tugas akhir tidak berisi teks yang dapat dibaca.',
            ]);
        }

        try {
            return response()->json([
                $this->extractCoverInformation($text),
            ]);
        } catch (\Throwable $e) {
            report($e);

            $message = $e instanceof \RuntimeException && $e->getMessage() !== ''
                ? $e->getMessage()
                : 'Gagal mengekstrak informasi sampul';

            return response()->json([
                'message' => $message,
            ], 500);
        }
    }

    /**
     * Persist a final project from the request. Both the final project form and the submission form use this.
     */
    protected function persistFinalProjectFromRequest($request, string $idKey): void
    {
        $request->validate([
            $idKey => ['nullable', 'integer', 'exists:final__projects,id'],
            'nim' => ['required', 'string', 'max:50'],
            'student_name' => ['required', 'string', 'max:255'],
            'final_project_title' => ['required', 'string'],
            'program_study' => ['required', 'string', 'max:255'],
            'degree_level' => ['required', 'string', 'max:50', 'exists:final_project_types,level'],
            'final_project_year' => ['required', 'integer', 'min:'.FinalProject::YEAR_MIN, 'max:'.FinalProject::YEAR_MAX],
            'submission_date' => ['required', 'date'],
            'final_project_type_id' => [
                'nullable',
                'integer',
                Rule::exists('final_project_types', 'id')->where(
                    fn ($query) => $query->where('level', $request->input('degree_level'))
                ),
            ],
            'final_project_file' => ['nullable', 'file', 'mimes:pdf'],
            'approval_sheet' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'degree_level.required' => 'Jenjang harus dipilih!',
            'degree_level.exists' => 'Jenjang tidak valid!',
            'final_project_type_id.exists' => 'Jenjang tidak valid!',
        ]);

        $typeMeta = $this->resolveFinalProjectTypeMeta(
            $request->input('final_project_type_id'),
            $request->input('degree_level')
        );

        if ($typeMeta['level'] !== '') {
            $request->merge([
                'degree_level' => $typeMeta['level'],
            ]);
        }

        $finalProjectFile = $approvalSheet = null;
        $recordId = $request->input($idKey);

        if ($request->filled($idKey)) {
            $finalProject = $this->findFinalProjectById($recordId);

            if (count($finalProject) > 0) {
                $finalProjectFile = $finalProject[0][self::FILE_COLUMN];
                $approvalSheet = $finalProject[0][self::APPROVAL_SHEET_COLUMN];
            }
        }

        $projectDirectory = $this->documentDirectoryFor(
            FinalProjectStorage::DOCUMENT_FINAL_PROJECT,
            $request->input('degree_level'),
            $request->input('final_project_year'),
            $request->input('program_study'),
            $request->input('final_project_type_id')
        );
        $approvalDirectory = $this->documentDirectoryFor(
            FinalProjectStorage::DOCUMENT_APPROVAL_SHEET,
            $request->input('degree_level'),
            $request->input('final_project_year'),
            $request->input('program_study'),
            $request->input('final_project_type_id')
        );

        $finalProjectFile = $this->replaceUploadedFile(
            $request,
            'final_project_file',
            $projectDirectory,
            $finalProjectFile
        );

        $approvalSheet = $this->replaceUploadedFile(
            $request,
            'approval_sheet',
            $approvalDirectory,
            $approvalSheet
        );

        $studentName = (string) $request->input('student_name', '');
        $nim = (string) $request->input('nim', '');

        if (! $request->hasFile('final_project_file')) {
            $finalProjectFile = FinalProjectStorage::alignStoredFile(
                $finalProjectFile,
                $projectDirectory,
                $studentName,
                $nim
            );
        }

        if (! $request->hasFile('approval_sheet')) {
            $approvalSheet = FinalProjectStorage::alignStoredFile(
                $approvalSheet,
                $approvalDirectory,
                $studentName,
                $nim
            );
        }

        $this->updateOrCreateFinalProject(
            $recordId,
            $request->nim,
            $request->student_name,
            $request->final_project_title,
            $request->program_study,
            $request->degree_level,
            $request->final_project_year,
            $request->submission_date,
            $finalProjectFile,
            $approvalSheet,
            $request->final_project_type_id
        );
    }

    // --- Save, import, and delete ---

    public function saveFinalProject($request): JsonResponse
    {
        $this->persistFinalProjectFromRequest($request, 'finalprojectid');

        return response()->json('Data Tugas Akhir Berhasil Disimpan');
    }

    /**
     * Match each imported level to Jenis Tugas Akhir by text only, ignoring case.
     * Rewrites the value to the stored level so a later exact lookup still succeeds.
     *
     * @param  list<mixed>  $levels
     * @param  list<mixed>  $typeIds
     * @return array{levels: list<string>, type_ids: list<mixed>}
     */
    protected function alignImportDegreeLevels(array $levels, array $typeIds): array
    {
        $example = 'misalnya S1 = skripsi, S2 = tesis, S3 = disertasi, dan jenis lainnya';
        $types = FinalProjectType::query()
            ->orderBy('id')
            ->get(['id', 'level', 'type'])
            ->filter(fn ($type) => trim((string) $type->level) !== '')
            ->values();

        if ($types->isEmpty()) {
            throw ValidationException::withMessages([
                'degree_level' => 'Tidak ada data jenis tugas akhir, sehingga impor tidak disimpan. Isi Jenis Tugas Akhir terlebih dahulu, '.$example.'.',
            ]);
        }

        $canonicalLevels = [];
        $missing = [];

        foreach ($levels as $index => $level) {
            $needle = $this->comparableDegreeText($level);

            if ($needle === '') {
                $missing[] = '(kosong)';

                continue;
            }

            $byLevel = $types
                ->filter(fn ($type) => $this->comparableDegreeText($type->level) === $needle)
                ->values();

            if ($byLevel->isNotEmpty()) {
                $canonicalLevels[] = (string) $byLevel->first()->level;
                $currentTypeId = $typeIds[$index] ?? null;

                if (($currentTypeId === null || $currentTypeId === '') && $byLevel->count() === 1) {
                    $typeIds[$index] = (int) $byLevel->first()->id;
                }

                continue;
            }

            $byName = $types
                ->filter(fn ($type) => $this->comparableDegreeText($type->type) === $needle)
                ->values();

            if ($byName->count() === 1) {
                $match = $byName->first();
                $canonicalLevels[] = (string) $match->level;
                $currentTypeId = $typeIds[$index] ?? null;

                if ($currentTypeId === null || $currentTypeId === '') {
                    $typeIds[$index] = (int) $match->id;
                }

                continue;
            }

            $text = trim((string) $level);
            $missing[] = $text !== '' ? $text : '(kosong)';
        }

        $missing = array_values(array_unique($missing));

        if ($missing !== []) {
            $shown = array_map(
                fn (string $value) => mb_strlen($value) > 40 ? mb_substr($value, 0, 40).'…' : $value,
                array_slice($missing, 0, 8)
            );
            $more = count($missing) > 8 ? ', dan lainnya' : '';

            throw ValidationException::withMessages([
                'degree_level' => 'Jenjang pada data impor tidak sesuai dengan jenis tugas akhir yang tersedia, sehingga data tidak disimpan. Yang tidak cocok: '
                    .implode(', ', $shown)
                    .$more
                    .'. Sesuaikan data Jenis Tugas Akhir, '.$example.'.',
            ]);
        }

        return [
            'levels' => $canonicalLevels,
            'type_ids' => $typeIds,
        ];
    }

    /**
     * Match each imported program name to Program Studi by text only, ignoring case.
     * Rewrites the value to the stored name so a later exact lookup still succeeds.
     *
     * @param  list<mixed>  $programs
     * @return list<string>
     */
    protected function alignImportStudyPrograms(array $programs): array
    {
        $example = 'misalnya Ilmu Komputer, Matematika, Fisika, Kimia, dan program studi lainnya';
        $known = StudyProgram::query()
            ->orderBy('id')
            ->get(['id', 'study_program'])
            ->filter(fn ($program) => trim((string) $program->study_program) !== '')
            ->values();

        if ($known->isEmpty()) {
            throw ValidationException::withMessages([
                'program_study' => 'Tidak ada data program studi, sehingga impor tidak disimpan. Isi Program Studi terlebih dahulu, '.$example.'.',
            ]);
        }

        $canonical = [];
        $missing = [];

        foreach ($programs as $program) {
            $needle = $this->comparableDegreeText($program);

            if ($needle === '') {
                $missing[] = '(kosong)';

                continue;
            }

            $match = $known->first(
                fn ($row) => $this->comparableDegreeText($row->study_program) === $needle
            );

            if ($match) {
                $canonical[] = (string) $match->study_program;

                continue;
            }

            $text = trim((string) $program);
            $missing[] = $text !== '' ? $text : '(kosong)';
        }

        $missing = array_values(array_unique($missing));

        if ($missing !== []) {
            $shown = array_map(
                fn (string $value) => mb_strlen($value) > 40 ? mb_substr($value, 0, 40).'…' : $value,
                array_slice($missing, 0, 8)
            );
            $more = count($missing) > 8 ? ', dan lainnya' : '';

            throw ValidationException::withMessages([
                'program_study' => 'Program studi pada data impor tidak sesuai dengan program studi yang tersedia, sehingga data tidak disimpan. Yang tidak cocok: '
                    .implode(', ', $shown)
                    .$more
                    .'. Sesuaikan data Program Studi, '.$example.'.',
            ]);
        }

        return $canonical;
    }

    public function importFinalProjects($request): JsonResponse
    {
        $submittedPrograms = $request->input('program_study');

        if (is_array($submittedPrograms) && $submittedPrograms !== []) {
            $request->merge([
                'program_study' => $this->alignImportStudyPrograms($submittedPrograms),
            ]);
        }

        $submittedLevels = $request->input('degree_level');

        if (is_array($submittedLevels) && $submittedLevels !== []) {
            $submittedTypeIds = $request->input('final_project_type_id', []);

            if (! is_array($submittedTypeIds)) {
                $submittedTypeIds = [];
            }

            $aligned = $this->alignImportDegreeLevels($submittedLevels, $submittedTypeIds);
            $request->merge([
                'degree_level' => $aligned['levels'],
                'final_project_type_id' => $aligned['type_ids'],
            ]);
        }

        $nimCount = count($request->input('nim', []));

        $request->validate([
            'nim' => ['required', 'array', 'min:1', 'max:200'],
            'nim.*' => ['required', 'string', 'max:50'],
            'student_name' => ['required', 'array', 'size:'.$nimCount],
            'student_name.*' => ['required', 'string', 'max:255'],
            'final_project_title' => ['required', 'array', 'size:'.$nimCount],
            'final_project_title.*' => ['required', 'string'],
            'program_study' => ['required', 'array', 'size:'.$nimCount],
            'program_study.*' => ['required', 'string', 'max:255', 'exists:study_programs,study_program'],
            'degree_level' => ['required', 'array', 'size:'.$nimCount],
            'degree_level.*' => ['required', 'string', 'max:50', 'exists:final_project_types,level'],
            'final_project_year' => ['required', 'array', 'size:'.$nimCount],
            'final_project_year.*' => ['required', 'integer', 'min:'.FinalProject::YEAR_MIN, 'max:'.FinalProject::YEAR_MAX],
            'submission_date' => ['required', 'array', 'size:'.$nimCount],
            'submission_date.*' => ['required', 'string'],
            'final_project_type_id' => ['nullable', 'array'],
            'final_project_type_id.*' => ['nullable', 'integer', 'exists:final_project_types,id'],
            'final_project_file' => ['nullable', 'array'],
            'final_project_file.*' => ['nullable', 'file', 'mimes:pdf'],
            'approval_sheet' => ['nullable', 'array'],
            'approval_sheet.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png'],
        ], [
            'nim.max' => 'Impor maksimal 200 baris sekali proses.',
            'nim.min' => 'Tidak ada data impor yang dikirim.',
            'program_study.*.required' => 'Program studi harus diisi!',
            'program_study.*.exists' => 'Program studi pada data impor tidak sesuai dengan program studi yang tersedia, sehingga data tidak disimpan. Sesuaikan data Program Studi, misalnya Ilmu Komputer, Matematika, Fisika, Kimia, dan program studi lainnya.',
            'degree_level.*.required' => 'Jenjang harus dipilih!',
            'degree_level.*.exists' => 'Jenjang pada data impor tidak sesuai dengan jenis tugas akhir yang tersedia, sehingga data tidak disimpan. Sesuaikan data Jenis Tugas Akhir, misalnya S1 = skripsi, S2 = tesis, S3 = disertasi, dan jenis lainnya.',
            'final_project_type_id.*.exists' => 'Jenjang tidak valid!',
            'final_project_year.*.min' => 'Tahun tugas akhir tidak valid.',
            'final_project_year.*.max' => 'Tahun tugas akhir tidak valid.',
        ]);

        try {
            $now = now();
            $finalProjectFiles = $request->file('final_project_file') ?? [];
            $approvalSheets = $request->file('approval_sheet') ?? [];
            $nims = $request->input('nim', []);
            $rows = [];

            foreach ($nims as $i => $nim) {
                $submissionDate = $this->toDatabaseDate($request->submission_date[$i] ?? null);

                if (! $this->isDatabaseDate($submissionDate)) {
                    throw ValidationException::withMessages([
                        "submission_date.$i" => 'Tanggal penyerahan pada baris '.($i + 1).' tidak valid.',
                    ]);
                }

                $programStudy = $request->program_study[$i] ?? null;
                $submittedLevel = $request->degree_level[$i] ?? null;
                $submittedTypeId = $request->input("final_project_type_id.$i");
                $typeMeta = $this->resolveFinalProjectTypeMeta($submittedTypeId, $submittedLevel);
                $degreeLevel = $typeMeta['level'] !== '' ? $typeMeta['level'] : $submittedLevel;
                $typeId = filled($submittedTypeId)
                    ? (int) $submittedTypeId
                    : $this->resolveFinalProjectTypeId(is_string($degreeLevel) ? $degreeLevel : null);
                $year = $request->final_project_year[$i] ?? null;
                $studentName = (string) ($request->student_name[$i] ?? '');

                $rows[] = [
                    'nim' => $nim,
                    'student_name' => $request->student_name[$i] ?? null,
                    'final_project_title' => $request->final_project_title[$i] ?? null,
                    'program_study' => $programStudy,
                    'degree_level' => $degreeLevel,
                    'final_project_year' => $year,
                    'submission_date' => $submissionDate,
                    'final_project_file' => isset($finalProjectFiles[$i])
                        ? $this->uploadToPublicStorage(
                            $finalProjectFiles[$i],
                            $this->documentDirectoryFor(
                                FinalProjectStorage::DOCUMENT_FINAL_PROJECT,
                                $degreeLevel,
                                $year,
                                $programStudy,
                                $typeId
                            ),
                            $studentName,
                            (string) $nim
                        )
                        : null,
                    'approval_sheet' => isset($approvalSheets[$i])
                        ? $this->uploadToPublicStorage(
                            $approvalSheets[$i],
                            $this->documentDirectoryFor(
                                FinalProjectStorage::DOCUMENT_APPROVAL_SHEET,
                                $degreeLevel,
                                $year,
                                $programStudy,
                                $typeId
                            ),
                            $studentName,
                            (string) $nim
                        )
                        : null,
                    'final_project_type_id' => $typeId,
                    'study_program_id' => $this->resolveStudyProgramId(is_string($programStudy) ? $programStudy : null),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::transaction(function () use ($rows) {
                foreach (array_chunk($rows, 200) as $chunk) {
                    FinalProject::query()->insert($chunk);
                }
            });

            DataTables::touch();
            CatalogCache::forgetDashboard();
            FinalProjectStorage::removeUnusedLegacyFolders();

            return response()->json('Berhasil');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Gagal mengimpor data tugas akhir',
            ], 500);
        }
    }

    /**
     * Delete a final project along with its files and related records.
     */
    protected function removeFinalProject(int $id): bool
    {
        $finalProjects = $this->findFinalProjectById($id);

        if (count($finalProjects) === 0) {
            return false;
        }

        $finalProjectFile = $finalProjects[0][self::FILE_COLUMN];
        $approvalSheet = $finalProjects[0][self::APPROVAL_SHEET_COLUMN];

        if (! empty($finalProjectFile) && $this->existsInPublicStorage($finalProjectFile)) {
            $this->deleteFromPublicStorage($finalProjectFile);
        }

        if (! empty($approvalSheet) && $this->existsInPublicStorage($approvalSheet)) {
            $this->deleteFromPublicStorage($approvalSheet);
        }

        return $this->deleteFinalProject($id) > 0;
    }

    public function destroyFinalProject($id): JsonResponse
    {
        if (! $this->removeFinalProject((int) $id)) {
            return response()->json('Hapus Gagal', 404);
        }

        return response()->json('Hapus Tugas Akhir Berhasil');
    }

    // --- File size and download ---

    public function showFileSize($request): JsonResponse
    {
        $relativePath = $request->input('files');

        if (! is_string($relativePath) || ! FinalProjectStorage::isSafeRelativePath($relativePath)) {
            return response()->json(['error' => 'Berkas tidak ditemukan'], 404);
        }

        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        if (! $this->existsInPublicStorage($relativePath)) {
            return response()->json(['error' => 'Berkas tidak ditemukan'], 404);
        }

        try {
            $size = $this->sizeInPublicStorage($relativePath);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'Berkas tidak ditemukan'], 404);
        }

        return response()->json([
            'size' => $size,
            'size_mb' => number_format($size / 1048576, 2),
        ]);
    }

    /**
     * Download a file column from a final project row (document or approval sheet).
     */
    private function downloadFinalProjectColumn(int $id, int $column)
    {
        $finalProjects = $this->findFinalProjectById($id);

        if (count($finalProjects) === 0) {
            abort(404, 'Unduh Gagal');
        }

        $path = $finalProjects[0][$column] ?? null;

        if (empty($path) || ! $this->existsInPublicStorage($path)) {
            abort(404, 'Berkas tidak ditemukan');
        }

        return $this->downloadFromPublicStorage(
            $path,
            $this->stripUuidPrefix($path)
        );
    }

    public function downloadFinalProject($id)
    {
        return $this->downloadFinalProjectColumn((int) $id, self::FILE_COLUMN);
    }

    public function downloadApprovalSheet($id)
    {
        return $this->downloadFinalProjectColumn((int) $id, self::APPROVAL_SHEET_COLUMN);
    }
}
