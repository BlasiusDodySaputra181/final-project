<?php

namespace App\Support;

use App\Models\FinalProject;
use App\Models\FinalProjectType;
use App\Models\Setting;
use App\Models\StudyProgram;
use FilesystemIterator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Storage for final-project files and approval sheets.
 * Officers set the folder root globally; empty means storage/app/public.
 */
class FinalProjectStorage
{
    public const ARCHIVE_DIRECTORY = 'ARSIP TUGAS AKHIR';

    public const LEGACY_ARCHIVE_DIRECTORY = 'finalproject';

    public const DOCUMENT_FINAL_PROJECT = 'BERKAS TUGAS AKHIR';

    public const DOCUMENT_APPROVAL_SHEET = 'LEMBAR PERSETUJUAN';

    public const RELATIVE_FINAL_PROJECT = 'finalproject/final_project';

    public const RELATIVE_APPROVAL_SHEET = 'finalproject/approval_sheet';

    public const COMPUTER_ROOT = '__computer__';

    /**
     * Default type names when final-project type data is missing.
     *
     * @var array<string, string>
     */
    public const DEFAULT_TYPE_LABELS = [
        'S1' => 'Skripsi',
        'S2' => 'Tesis',
        'S3' => 'Disertasi',
        'D3' => 'Laporan Tugas Akhir',
    ];

    private const DRIVE_TYPE_REMOVABLE = 2;

    private const DRIVE_TYPE_FIXED = 3;

    private const DRIVE_TYPE_NETWORK = 4;

    private const DRIVE_TYPE_CDROM = 5;

    private const MAX_PATH_LENGTH = 500;

    private const BROWSE_LIMIT = 250;

    private static ?Filesystem $disk = null;

    private static ?string $diskRoot = null;

    /**
     * @var array<int, FinalProjectType|null>|null
     */
    private static ?array $typesById = null;

    /**
     * @var array<string, FinalProjectType>|null
     */
    private static ?array $typesByLevel = null;

    /**
     * @var array<int, array{program: string, cluster: string}>|null
     */
    private static ?array $programsById = null;

    /**
     * @var array<string, array{program: string, cluster: string}>|null
     */
    private static ?array $programsByName = null;

    /**
     * Canonical clusters: a key without the RUMPUN prefix maps to the catalog's first original value.
     *
     * @var array<string, string>|null
     */
    private static ?array $clustersByKey = null;

    /**
     * @var (callable(array<string, mixed>): void)|null
     */
    private static $progressCallback = null;

    private static int $progressLastMs = 0;

    private static int $progressLastPercent = -1;

    private static string $progressLastPhase = '';

    private static ?string $progressLastFile = null;

    public static function defaultRoot(): string
    {
        return self::displayPath(storage_path('app/public'));
    }

    public static function configuredRoot(): string
    {
        $custom = Setting::finalProjectStoragePath();

        if ($custom === '') {
            return self::defaultRoot();
        }

        return self::displayPath($custom);
    }

    public static function isUsingDefault(): bool
    {
        return Setting::finalProjectStoragePath() === '';
    }

    public static function forgetCachedDisk(): void
    {
        self::$disk = null;
        self::$diskRoot = null;
        self::$typesById = null;
        self::$typesByLevel = null;
        self::$programsById = null;
        self::$programsByName = null;
        self::$clustersByKey = null;
    }

    public static function disk(): Filesystem
    {
        $root = self::configuredRoot();

        if (self::$disk !== null && self::$diskRoot === $root) {
            return self::$disk;
        }

        self::$diskRoot = $root;

        if (self::isUsingDefault() || self::samePath($root, self::defaultRoot())) {
            return self::$disk = Storage::disk('public');
        }

        return self::$disk = Storage::build([
            'driver' => 'local',
            'root' => $root,
            'throw' => false,
            'report' => false,
        ]);
    }

    public static function fallbackDisk(): Filesystem
    {
        return Storage::disk('public');
    }

    public static function exists(string $path): bool
    {
        return self::locate($path) !== null;
    }

    public static function delete(string $path): bool
    {
        $deleted = false;

        foreach (self::locateAll($path) as $located) {
            if (! $located['disk']->delete($located['path'])) {
                continue;
            }

            $deleted = true;
            self::pruneDeletedParent($located['disk'], $located['path']);
        }

        return $deleted;
    }

    public static function size(string $path): int
    {
        $located = self::locate($path);

        if ($located === null) {
            throw new \RuntimeException('Berkas tidak ditemukan');
        }

        return (int) $located['disk']->size($located['path']);
    }

    public static function download(string $path, string $name): StreamedResponse
    {
        $located = self::locate($path);

        if ($located === null) {
            abort(404, 'Berkas tidak ditemukan');
        }

        return $located['disk']->download($located['path'], $name);
    }

    /**
     * Show the file in a new tab before download, as with membership letters.
     */
    public static function inline(string $path, string $name): StreamedResponse
    {
        $located = self::locate($path);

        if ($located === null) {
            abort(404, 'Berkas tidak ditemukan');
        }

        return $located['disk']->response(
            $located['path'],
            $name,
            [
                'Content-Type' => self::previewMimeType($located['path'], $name),
                'Cache-Control' => 'private, max-age=0, must-revalidate',
            ],
            'inline'
        );
    }

    private static function previewMimeType(string $storedPath, string $name): string
    {
        $extension = strtolower((string) pathinfo($name !== '' ? $name : $storedPath, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
    }

    public static function putFileAs(string $directory, UploadedFile $file, string $fileName): string|false
    {
        self::assertReady();

        return self::disk()->putFileAs($directory, $file, $fileName);
    }

    /**
     * File name: Full Name_NIM.ext
     * Example: Blasius Dody Saputra Jalung_1313623077.pdf
     */
    public static function storedFileName(string $studentName, string $nim, string $extension): string
    {
        $name = self::sanitizeFileNamePart($studentName);
        $identity = self::sanitizeFileNamePart($nim);
        $extension = strtolower(ltrim(trim($extension), '.'));

        if ($extension === '' || preg_match('/^[a-z0-9]+$/', $extension) !== 1) {
            $extension = 'pdf';
        }

        if ($name === '') {
            $name = 'berkas';
        }

        if ($identity === '') {
            $identity = 'tanpa-nim';
        }

        $suffix = '_'.$identity.'.'.$extension;
        $maxName = 200 - mb_strlen($suffix);

        if ($maxName < 1) {
            $maxName = 1;
        }

        if (mb_strlen($name) > $maxName) {
            $name = rtrim(mb_substr($name, 0, $maxName));
        }

        return $name.$suffix;
    }

    /**
     * Document folder: ARSIP TUGAS AKHIR / type / year / cluster / study program / file or sheet.
     * The science cluster comes from study_programs.cluster; identical folder names are merged.
     */
    public static function documentDirectory(
        string $documentKind,
        ?string $type = null,
        ?string $level = null,
        mixed $year = null,
        ?string $programStudy = null,
        ?string $cluster = null
    ): string {
        $documentFolder = self::documentFolderName($documentKind);

        if (! filled($cluster) && filled($programStudy)) {
            $cluster = self::resolveProgramFolderParts(null, $programStudy)['cluster'];
        }

        return implode('/', [
            self::ARCHIVE_DIRECTORY,
            self::typeFolderName($type, $level),
            self::yearFolderName($year),
            self::clusterFolderName($cluster),
            self::studyProgramFolderName($programStudy),
            $documentFolder,
        ]);
    }

    public static function storedRelativePath(
        string $documentKind,
        string $studentName,
        string $nim,
        string $extension,
        ?string $type = null,
        ?string $level = null,
        mixed $year = null,
        ?string $programStudy = null,
        ?string $cluster = null
    ): string {
        return self::documentDirectory($documentKind, $type, $level, $year, $programStudy, $cluster)
            .'/'.self::storedFileName($studentName, $nim, $extension);
    }

    /**
     * Folder from catalog relations: type name (final_project_types),
     * final-project year, science cluster (study_programs.cluster), and study program name.
     */
    public static function documentDirectoryFromCatalog(
        string $documentKind,
        mixed $typeId,
        mixed $degreeLevel,
        mixed $year,
        mixed $programStudy,
        mixed $studyProgramId = null
    ): string {
        $type = self::resolveTypeFolderParts($typeId, $degreeLevel);
        $program = self::resolveProgramFolderParts($studyProgramId, $programStudy);

        return self::documentDirectory(
            $documentKind,
            $type['type'],
            $type['level'],
            $year,
            $program['program'],
            $program['cluster']
        );
    }

    /**
     * Folder from final__projects fields, falling back to catalog relations.
     */
    public static function documentDirectoryFromFields(
        string $documentKind,
        mixed $degreeLevel,
        mixed $year,
        mixed $programStudy,
        mixed $typeId = null,
        mixed $studyProgramId = null
    ): string {
        return self::documentDirectoryFromCatalog(
            $documentKind,
            $typeId,
            $degreeLevel,
            $year,
            $programStudy,
            $studyProgramId
        );
    }

    public static function storedRelativePathFromFields(
        string $documentKind,
        string $studentName,
        string $nim,
        string $extension,
        mixed $degreeLevel,
        mixed $year,
        mixed $programStudy,
        mixed $typeId = null,
        mixed $studyProgramId = null
    ): string {
        return self::documentDirectoryFromCatalog(
            $documentKind,
            $typeId,
            $degreeLevel,
            $year,
            $programStudy,
            $studyProgramId
        ).'/'.self::storedFileName($studentName, $nim, $extension);
    }

    /**
     * @return array{type: string, level: string}
     */
    public static function resolveTypeFolderParts(mixed $typeId, mixed $degreeLevel): array
    {
        $typeId = filled($typeId) ? (int) $typeId : null;
        $degreeLevel = trim((string) $degreeLevel);

        if ($typeId) {
            $row = self::finalProjectTypeById($typeId);

            if ($row) {
                return [
                    'type' => (string) $row->type,
                    'level' => (string) ($row->level ?: $degreeLevel),
                ];
            }
        }

        if ($degreeLevel !== '') {
            $row = self::finalProjectTypeByLevel($degreeLevel);

            if ($row) {
                return [
                    'type' => (string) $row->type,
                    'level' => (string) ($row->level ?: $degreeLevel),
                ];
            }

            return [
                'type' => self::DEFAULT_TYPE_LABELS[mb_strtoupper($degreeLevel, 'UTF-8')] ?? '',
                'level' => $degreeLevel,
            ];
        }

        return ['type' => '', 'level' => ''];
    }

    /**
     * @return array{program: string, cluster: string}
     */
    public static function resolveProgramFolderParts(mixed $studyProgramId, mixed $programStudy): array
    {
        $studyProgramId = filled($studyProgramId) ? (int) $studyProgramId : null;

        if ($studyProgramId) {
            $record = self::programRecordById($studyProgramId);

            if ($record !== null) {
                return $record;
            }
        }

        $programStudy = trim((string) $programStudy);

        if ($programStudy !== '') {
            $record = self::programRecordByLabel($programStudy);

            if ($record !== null) {
                return $record;
            }

            return ['program' => $programStudy, 'cluster' => ''];
        }

        return ['program' => '', 'cluster' => ''];
    }

    public static function resolveProgramFolderName(mixed $studyProgramId, mixed $programStudy): string
    {
        return self::resolveProgramFolderParts($studyProgramId, $programStudy)['program'];
    }

    /**
     * Folder type name follows final_project_types, then the degree level.
     */
    public static function typeNameForDegreeLevel(?string $degreeLevel): string
    {
        return self::resolveTypeFolderParts(null, $degreeLevel)['type'];
    }

    public static function documentFolderName(string $documentKind): string
    {
        $kind = str_replace('\\', '/', trim($documentKind));

        return match ($kind) {
            'approval_sheet', self::RELATIVE_APPROVAL_SHEET, self::DOCUMENT_APPROVAL_SHEET => self::DOCUMENT_APPROVAL_SHEET,
            default => self::DOCUMENT_FINAL_PROJECT,
        };
    }

    public static function typeFolderName(?string $type, ?string $level): string
    {
        $levelKey = mb_strtoupper(trim((string) $level), 'UTF-8');
        $typeName = trim((string) $type);

        if ($typeName === '' && $levelKey !== '') {
            $typeName = self::DEFAULT_TYPE_LABELS[$levelKey] ?? '';
        }

        $typeName = self::folderSegment($typeName);
        $levelName = self::folderSegment((string) $level);

        if ($typeName === '' && $levelName === '') {
            return 'JENIS LAINNYA';
        }

        if ($typeName === '') {
            return $levelName;
        }

        if ($levelName === '' || str_contains($typeName, '('.$levelName.')')) {
            return $typeName;
        }

        return $typeName.' ('.$levelName.')';
    }

    public static function yearFolderName(mixed $year): string
    {
        $year = trim((string) $year);

        if (preg_match('/^\d{4}$/', $year) !== 1) {
            return 'TANPA TAHUN';
        }

        return $year;
    }

    /**
     * Cluster folder name from study_programs.cluster.
     * The RUMPUN prefix is not doubled; matching names are merged, ignoring case.
     */
    public static function clusterFolderName(?string $cluster): string
    {
        $cluster = self::folderSegment((string) $cluster);

        if ($cluster === '') {
            return 'TANPA RUMPUN';
        }

        if (str_starts_with($cluster, 'RUMPUN ')) {
            return $cluster;
        }

        return 'RUMPUN '.$cluster;
    }

    public static function studyProgramFolderName(?string $programStudy): string
    {
        $program = self::folderSegment((string) $programStudy);

        return $program === '' ? 'TANPA PROGRAM STUDI' : $program;
    }

    private static function finalProjectTypeById(int $id): ?FinalProjectType
    {
        self::hydrateTypeCache();

        return self::$typesById[$id] ?? null;
    }

    private static function finalProjectTypeByLevel(string $level): ?FinalProjectType
    {
        self::hydrateTypeCache();
        $key = mb_strtoupper(trim($level), 'UTF-8');

        return self::$typesByLevel[$key] ?? null;
    }

    private static function hydrateTypeCache(): void
    {
        if (self::$typesById !== null) {
            return;
        }

        self::$typesById = [];
        self::$typesByLevel = [];

        try {
            $rows = FinalProjectType::query()->orderBy('id')->get(['id', 'type', 'level']);
        } catch (\Throwable) {
            return;
        }

        foreach ($rows as $row) {
            self::$typesById[(int) $row->id] = $row;
            $levelKey = mb_strtoupper(trim((string) $row->level), 'UTF-8');

            if ($levelKey !== '' && ! isset(self::$typesByLevel[$levelKey])) {
                self::$typesByLevel[$levelKey] = $row;
            }
        }
    }

    /**
     * @return array{program: string, cluster: string}|null
     */
    private static function programRecordById(int $id): ?array
    {
        self::hydrateProgramCache();

        return self::$programsById[$id] ?? null;
    }

    /**
     * @return array{program: string, cluster: string}|null
     */
    private static function programRecordByLabel(string $label): ?array
    {
        self::hydrateProgramCache();
        $key = mb_strtoupper(trim($label), 'UTF-8');

        return self::$programsByName[$key] ?? null;
    }

    private static function clusterKey(string $cluster): string
    {
        $key = mb_strtoupper(trim($cluster), 'UTF-8');
        $key = preg_replace('/^RUMPUN\s+/u', '', $key) ?? $key;

        return trim($key);
    }

    private static function hydrateProgramCache(): void
    {
        if (self::$programsById !== null) {
            return;
        }

        self::$programsById = [];
        self::$programsByName = [];
        self::$clustersByKey = [];

        try {
            $rows = StudyProgram::query()->orderBy('id')->get(['id', 'study_program', 'cluster']);
        } catch (\Throwable) {
            return;
        }

        foreach ($rows as $row) {
            $name = trim((string) $row->study_program);
            $cluster = trim((string) $row->cluster);
            $clusterKey = self::clusterKey($cluster);

            if ($clusterKey !== '') {
                if (isset(self::$clustersByKey[$clusterKey])) {
                    $cluster = self::$clustersByKey[$clusterKey];
                } else {
                    self::$clustersByKey[$clusterKey] = $cluster;
                }
            }

            $record = ['program' => $name, 'cluster' => $cluster];
            self::$programsById[(int) $row->id] = $record;
            $nameKey = mb_strtoupper($name, 'UTF-8');

            if ($nameKey !== '' && ! isset(self::$programsByName[$nameKey])) {
                self::$programsByName[$nameKey] = $record;
            }
        }
    }

    /**
     * Move the file into the new folder layout. Null or empty paths are left as-is.
     */
    public static function alignStoredFile(?string $path, string $directory, string $studentName, string $nim): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        $source = self::existingRelativePath($path);
        $from = $source ?? $path;
        $extension = pathinfo(str_replace('\\', '/', $from), PATHINFO_EXTENSION) ?: 'pdf';
        $target = $directory.'/'.self::storedFileName($studentName, $nim, $extension);

        if ($from === $target) {
            return $target;
        }

        if ($source !== null && self::moveRelative($source, $target)) {
            return $target;
        }

        return $path;
    }

    public static function existingRelativePath(string $path): ?string
    {
        return self::locate($path)['path'] ?? null;
    }

    public static function moveRelative(string $from, string $to): bool
    {
        if (! self::isSafeRelativePath($from) || ! self::isSafeRelativePath($to)) {
            return false;
        }

        $from = str_replace('\\', '/', $from);
        $to = str_replace('\\', '/', $to);

        if ($from === $to) {
            return true;
        }

        $located = self::locate($from);

        if ($located === null) {
            return false;
        }

        $directory = dirname($to);

        if ($located['disk'] === self::disk()) {
            self::disk()->makeDirectory($directory);

            if (self::disk()->exists($to)) {
                self::disk()->delete($to);
            }

            if (! self::disk()->move($located['path'], $to)) {
                return false;
            }

            self::pruneDeletedParent($located['disk'], $located['path']);

            return true;
        }

        $contents = $located['disk']->get($located['path']);

        if ($contents === null) {
            return false;
        }

        self::disk()->makeDirectory($directory);

        if (self::disk()->put($to, $contents) === false) {
            return false;
        }

        $located['disk']->delete($located['path']);
        self::pruneDeletedParent($located['disk'], $located['path']);

        return true;
    }

    public static function isReady(): bool
    {
        $root = self::configuredRoot();

        return is_dir($root) && self::directoryIsWritable($root);
    }

    /**
     * Folder location used at installation, without moving existing files.
     * Mode new creates ARSIP TUGAS AKHIR; mode existing uses that folder
     * or a parent that already contains it.
     *
     * @return array{stored: string, root: string, message: string}
     */
    public static function resolveInstallFolder(string $mode, string $path): array
    {
        $mode = $mode === 'existing' ? 'existing' : 'new';
        $path = trim($path);

        if ($mode === 'new' && $path === '') {
            $root = self::defaultRoot();
            self::ensureArchiveDirectory($root);

            return [
                'stored' => '',
                'root' => $root,
                'message' => 'Folder ARSIP TUGAS AKHIR baru memakai lokasi bawaan aplikasi.',
            ];
        }

        $resolved = self::validateAndResolve($path, $mode === 'new');

        if (self::isArchiveFolderName(basename($resolved))) {
            $parent = self::parentDirectory($resolved);

            if ($parent === null || self::isComputerRoot($parent)) {
                throw ValidationException::withMessages([
                    'archive_path' => 'Pilih folder induk ARSIP TUGAS AKHIR, bukan akar drive.',
                ]);
            }

            $root = self::validateAndResolve($parent, false);
            self::ensureArchiveDirectory($root);

            return self::installFolderResult($root, $mode === 'new');
        }

        if ($mode === 'existing' && ! self::containsArchiveDirectory($resolved)) {
            throw ValidationException::withMessages([
                'archive_path' => 'Folder ini belum berisi ARSIP TUGAS AKHIR. Pilih folder arsipnya, atau buat folder baru.',
            ]);
        }

        if ($mode === 'new') {
            self::ensureArchiveDirectory($resolved);
        }

        return self::installFolderResult($resolved, $mode === 'new');
    }

    /**
     * Save the folder location; an empty string restores the application default.
     * The ARSIP TUGAS AKHIR parent (or legacy finalproject) is moved intact,
     * then files are reorganized by type, year, science cluster, and study program.
     *
     * @param  (callable(array<string, mixed>): void)|null  $onProgress
     * @return array{path: string, moved: int}
     */
    public static function persistPath(?string $path, ?callable $onProgress = null): array
    {
        self::$progressCallback = $onProgress;
        self::$progressLastMs = 0;
        self::$progressLastPercent = -1;
        self::$progressLastPhase = '';
        self::$progressLastFile = null;

        try {
            $path = trim((string) $path);
            $previousRoot = self::configuredRoot();

            self::reportProgress(1, 'prepare', 'Menyiapkan pemindahan', 'Memeriksa folder sumber dan tujuan…');

            if (self::isComputerRoot($path)) {
                throw ValidationException::withMessages([
                    'storage_path' => 'Pilih folder di salah satu drive yang terhubung, misalnya D:\\ArsipTA.',
                ]);
            }

            if ($path === '') {
                return self::persistToRoot($previousRoot, self::defaultRoot(), '');
            }

            $resolved = self::validateAndResolve($path, true);
            self::assertNotArchiveLocation($resolved, $previousRoot);

            if (self::samePath($resolved, self::defaultRoot())) {
                return self::persistToRoot($previousRoot, self::defaultRoot(), '');
            }

            return self::persistToRoot($previousRoot, $resolved, $resolved);
        } finally {
            self::$progressCallback = null;
        }
    }

    /**
     * @return array{path: string, moved: int}
     */
    private static function persistToRoot(string $previousRoot, string $newRoot, string $storedPath): array
    {
        self::reportProgress(4, 'move', 'Memindah arsip', 'Memindah folder ARSIP TUGAS AKHIR ke lokasi baru…');
        $moved = self::relocateArchive($previousRoot, $newRoot);

        self::reportProgress(72, 'save', 'Menyimpan pengaturan', 'Menyimpan lokasi folder…');
        Setting::setFinalProjectStoragePath($storedPath);
        self::forgetCachedDisk();

        self::reportProgress(74, 'reorganize', 'Menyusun berkas', 'Menyusun ulang berkas menurut jenis, tahun, rumpun ilmu, dan program studi…');
        $moved += self::reorganizeStoredFiles();

        self::reportProgress(88, 'sync', 'Menyinkronkan folder', 'Menggabungkan folder bernama sama dan menghapus folder kosong…');
        self::syncFoldersFromDatabase($newRoot);

        self::reportProgress(96, 'cleanup', 'Membersihkan folder lama', 'Menghapus folder yang tidak dipakai…');
        self::removeUnusedLegacyFolders($newRoot);
        self::removeUnusedLegacyFolders($previousRoot);

        if ($storedPath !== '') {
            self::removeUnusedLegacyFolders(self::defaultRoot());
        }

        self::reportProgress(100, 'done', 'Selesai', 'Pemindahan data selesai.');

        return ['path' => $storedPath, 'moved' => $moved];
    }

    /**
     * @param  (callable(array<string, mixed>): void)|null  $onProgress
     */
    private static function reportProgress(
        int $percent,
        string $phase,
        string $label,
        string $status,
        int $current = 0,
        int $total = 0,
        ?string $file = null,
        bool $force = false
    ): void {
        if (self::$progressCallback === null) {
            return;
        }

        $percent = max(0, min(100, $percent));
        $now = (int) floor(microtime(true) * 1000);
        $phaseChanged = $phase !== self::$progressLastPhase;
        $percentChanged = $percent !== self::$progressLastPercent;
        $fileChanged = $file !== self::$progressLastFile;

        if (
            ! $force
            && ! $phaseChanged
            && ! $percentChanged
            && ! $fileChanged
            && ($now - self::$progressLastMs) < 120
            && $percent < 100
        ) {
            return;
        }

        if (
            ! $force
            && ! $phaseChanged
            && $fileChanged
            && ! $percentChanged
            && ($now - self::$progressLastMs) < 40
            && $percent < 100
        ) {
            return;
        }

        self::$progressLastMs = $now;
        self::$progressLastPercent = $percent;
        self::$progressLastPhase = $phase;
        self::$progressLastFile = $file;

        (self::$progressCallback)([
            'percent' => $percent,
            'phase' => $phase,
            'label' => $label,
            'status' => $status,
            'current' => max(0, $current),
            'total' => max(0, $total),
            'file' => $file,
        ]);
    }

    private static function reportRange(
        int $from,
        int $to,
        int $current,
        int $total,
        string $phase,
        string $label,
        string $status,
        ?string $file = null,
        bool $force = false
    ): void {
        if ($total <= 0) {
            self::reportProgress($to, $phase, $label, $status, $current, $total, $file, $force);

            return;
        }

        $span = max(0, $to - $from);
        $percent = $from + (int) floor($span * min(1, $current / $total));
        self::reportProgress($percent, $phase, $label, $status, $current, $total, $file, $force);
    }

    /**
     * Server folder list for the location picker.
     *
     * @return array{
     *     path: string,
     *     parent: string|null,
     *     writable: bool,
     *     computer: bool,
     *     entries: list<array{name: string, path: string, kind?: string}>,
     *     shortcuts: list<array{label: string, path: string, kind: string}>
     * }
     */
    public static function browse(?string $path, bool $includeDatabaseFiles = false): array
    {
        $target = trim((string) $path);

        if (self::isComputerRoot($target)) {
            return self::browseComputer();
        }

        if ($target === '') {
            $target = self::configuredRoot();
        }

        $resolved = self::validateAndResolve($target, false);

        $entries = [];
        $names = @scandir($resolved);

        if (is_array($names)) {
            foreach ($names as $name) {
                if ($name === '.' || $name === '..' || self::isSkippedDirectoryName($name)) {
                    continue;
                }

                $full = $resolved.DIRECTORY_SEPARATOR.$name;
                $isDirectory = is_dir($full);

                if (! $isDirectory && ! ($includeDatabaseFiles && is_file($full) && self::isDatabaseFileName($name))) {
                    continue;
                }

                if ($isDirectory) {
                    $realChild = realpath($full);

                    if ($realChild === false || self::isBlockedPath($realChild)) {
                        continue;
                    }
                }

                $entries[] = [
                    'name' => $name,
                    'path' => self::displayPath($full),
                    'kind' => $isDirectory ? 'folder' : 'database',
                ];

                if (count($entries) >= self::BROWSE_LIMIT) {
                    break;
                }
            }

            usort(
                $entries,
                static function (array $left, array $right): int {
                    $byKind = ($left['kind'] === 'folder' ? 0 : 1) <=> ($right['kind'] === 'folder' ? 0 : 1);

                    return $byKind !== 0 ? $byKind : strcasecmp($left['name'], $right['name']);
                }
            );
        }

        $parent = self::parentDirectory($resolved);

        return [
            'path' => self::displayPath($resolved),
            'parent' => $parent,
            'writable' => self::directoryIsWritable($resolved),
            'computer' => false,
            'entries' => $entries,
            'shortcuts' => self::shortcuts(),
        ];
    }

    public static function isComputerRoot(?string $path): bool
    {
        $path = trim((string) $path);

        return $path === self::COMPUTER_ROOT
            || strcasecmp($path, 'Komputer') === 0;
    }

    /**
     * @return list<array{label: string, path: string, kind: string}>
     */
    public static function shortcuts(): array
    {
        $items = [
            [
                'label' => 'Komputer',
                'path' => self::COMPUTER_ROOT,
                'kind' => 'computer',
            ],
            [
                'label' => 'Penyimpanan aplikasi (default)',
                'path' => self::defaultRoot(),
                'kind' => 'default',
            ],
        ];

        foreach (self::connectedVolumes() as $volume) {
            $items[] = [
                'label' => $volume['label'],
                'path' => $volume['path'],
                'kind' => $volume['kind'],
            ];
        }

        foreach (self::candidateShortcuts() as $item) {
            if (! is_dir($item['path'])) {
                continue;
            }

            $items[] = [
                'label' => $item['label'],
                'path' => self::displayPath($item['path']),
                'kind' => $item['kind'],
            ];
        }

        return $items;
    }

    public static function displayPath(string $path): string
    {
        $path = self::normalizeSeparators(trim($path));

        if (PHP_OS_FAMILY === 'Windows' && preg_match('/^[A-Za-z]:$/', $path) === 1) {
            return $path.'\\';
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $path = preg_replace('#\\\\{2,}#', '\\', $path) ?? $path;

            if (preg_match('/^[A-Za-z]:\\\\$/', $path) === 1) {
                return $path;
            }

            return rtrim($path, '\\');
        }

        if ($path === '') {
            return '/';
        }

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private static function sanitizeFileNamePart(string $value): string
    {
        $value = trim(str_replace("\0", '', $value));
        $value = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($value, ' .');
    }

    private static function folderSegment(string $value): string
    {
        return self::sanitizeFileNamePart(mb_strtoupper(trim($value), 'UTF-8'));
    }

    /**
     * @return array{disk: Filesystem, path: string}|null
     */
    private static function locate(string $path): ?array
    {
        if (! self::isSafeRelativePath($path)) {
            return null;
        }

        $disks = [self::disk()];

        if (! self::isUsingDefault()) {
            $disks[] = self::fallbackDisk();
        }

        foreach ($disks as $disk) {
            foreach (self::pathCandidates($path) as $candidate) {
                if ($disk->exists($candidate)) {
                    return ['disk' => $disk, 'path' => $candidate];
                }
            }
        }

        return null;
    }

    /**
     * @return list<array{disk: Filesystem, path: string}>
     */
    private static function locateAll(string $path): array
    {
        if (! self::isSafeRelativePath($path)) {
            return [];
        }

        $found = [];
        $seen = [];
        $disks = [self::disk()];

        if (! self::isUsingDefault()) {
            $disks[] = self::fallbackDisk();
        }

        foreach ($disks as $disk) {
            foreach (self::pathCandidates($path) as $candidate) {
                $key = spl_object_id($disk).'|'.$candidate;

                if (isset($seen[$key]) || ! $disk->exists($candidate)) {
                    continue;
                }

                $seen[$key] = true;
                $found[] = ['disk' => $disk, 'path' => $candidate];
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private static function pathCandidates(string $path): array
    {
        $normalized = str_replace('\\', '/', trim($path));
        $candidates = [$normalized];
        $legacyPrefix = self::LEGACY_ARCHIVE_DIRECTORY.'/';
        $archivePrefix = self::ARCHIVE_DIRECTORY.'/';

        if (str_starts_with($normalized, $legacyPrefix)) {
            $candidates[] = self::ARCHIVE_DIRECTORY.'/'.substr($normalized, strlen($legacyPrefix));
        } elseif (str_starts_with($normalized, $archivePrefix)) {
            $candidates[] = self::LEGACY_ARCHIVE_DIRECTORY.'/'.substr($normalized, strlen($archivePrefix));
        }

        return array_values(array_unique($candidates));
    }

    public static function isSafeRelativePath(string $path): bool
    {
        $normalized = str_replace('\\', '/', trim($path));

        if ($normalized === '' || str_contains($normalized, '..')) {
            return false;
        }

        if (str_starts_with($normalized, '/') || preg_match('#^[a-zA-Z]:#', $normalized) === 1) {
            return false;
        }

        return true;
    }

    private static function assertReady(): void
    {
        $root = self::configuredRoot();

        if (! is_dir($root)) {
            throw ValidationException::withMessages([
                'file' => 'Folder penyimpanan tugas akhir tidak ditemukan. Atur ulang lokasi di Pengaturan.',
            ]);
        }

        if (! self::directoryIsWritable($root)) {
            throw ValidationException::withMessages([
                'file' => 'Folder penyimpanan tugas akhir tidak dapat ditulisi. Periksa izin folder, atau pilih lokasi lain di Pengaturan.',
            ]);
        }
    }

    private static function validateAndResolve(string $path, bool $createIfMissing): string
    {
        $path = self::normalizeSeparators(trim($path));

        if ($path === '' || strlen($path) > self::MAX_PATH_LENGTH) {
            throw ValidationException::withMessages([
                'storage_path' => 'Lokasi folder tidak valid.',
            ]);
        }

        if (str_contains($path, "\0")) {
            throw ValidationException::withMessages([
                'storage_path' => 'Lokasi folder tidak valid.',
            ]);
        }

        if (! is_dir($path)) {
            if (! $createIfMissing) {
                throw ValidationException::withMessages([
                    'storage_path' => 'Folder tidak ditemukan. Pilih folder yang sudah ada.',
                ]);
            }

            $parent = self::parentDirectory($path);

            if ($parent === null || ! is_dir($parent)) {
                throw ValidationException::withMessages([
                    'storage_path' => 'Folder tidak ditemukan. Buat folder di komputer, atau pilih folder induk yang sudah ada.',
                ]);
            }

            $parentReal = realpath($parent);

            if ($parentReal === false || self::isBlockedPath($parentReal)) {
                throw ValidationException::withMessages([
                    'storage_path' => 'Folder ini tidak dapat digunakan sebagai lokasi penyimpanan.',
                ]);
            }

            if (! self::directoryIsWritable($parentReal)) {
                throw ValidationException::withMessages([
                    'storage_path' => 'Folder induk tidak dapat ditulisi. Pilih lokasi lain.',
                ]);
            }

            if (! @mkdir($path, 0775, false) && ! is_dir($path)) {
                throw ValidationException::withMessages([
                    'storage_path' => 'Gagal membuat folder. Periksa izin, lalu coba lagi.',
                ]);
            }
        }

        $real = realpath($path);

        if ($real === false || ! is_dir($real)) {
            throw ValidationException::withMessages([
                'storage_path' => 'Folder tidak ditemukan. Pilih folder yang sudah ada.',
            ]);
        }

        if (self::isBlockedPath($real)) {
            throw ValidationException::withMessages([
                'storage_path' => 'Folder sistem atau folder aplikasi tidak dapat digunakan. Pilih lokasi lain, misalnya Documents.',
            ]);
        }

        if ($createIfMissing && ! self::directoryIsWritable($real)) {
            throw ValidationException::withMessages([
                'storage_path' => 'Folder tidak dapat ditulisi. Pilih lokasi lain atau ubah izin folder.',
            ]);
        }

        return self::displayPath($real);
    }

    private static function isDatabaseFileName(string $name): bool
    {
        $lower = strtolower($name);

        return str_ends_with($lower, '.sqlite')
            || str_ends_with($lower, '.sqlite3')
            || str_ends_with($lower, '.db');
    }

    private static function isArchiveFolderName(string $name): bool
    {
        foreach (self::archiveDirectoryNames() as $archive) {
            if (strcasecmp($name, $archive) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function containsArchiveDirectory(string $root): bool
    {
        foreach (self::archiveDirectoryNames() as $name) {
            if (is_dir(rtrim($root, '\\/').DIRECTORY_SEPARATOR.$name)) {
                return true;
            }
        }

        return false;
    }

    private static function ensureArchiveDirectory(string $root): void
    {
        $archive = rtrim($root, '\\/').DIRECTORY_SEPARATOR.self::ARCHIVE_DIRECTORY;

        if (! is_dir($archive) && ! @mkdir($archive, 0775, true) && ! is_dir($archive)) {
            throw ValidationException::withMessages([
                'archive_path' => 'Gagal membuat folder ARSIP TUGAS AKHIR.',
            ]);
        }
    }

    /**
     * @return array{stored: string, root: string, message: string}
     */
    private static function installFolderResult(string $root, bool $created): array
    {
        $stored = self::samePath($root, self::defaultRoot()) ? '' : $root;

        return [
            'stored' => $stored,
            'root' => $root,
            'message' => $created
                ? 'Folder ARSIP TUGAS AKHIR baru siap di lokasi yang dipilih.'
                : 'Aplikasi memakai folder ARSIP TUGAS AKHIR yang sudah ada.',
        ];
    }

    private static function parentDirectory(string $path): ?string
    {
        $path = self::displayPath($path);

        if (PHP_OS_FAMILY === 'Windows') {
            if (preg_match('/^[A-Za-z]:\\\\?$/', $path) === 1) {
                return self::COMPUTER_ROOT;
            }

            $parent = dirname($path);

            if ($parent === $path || $parent === '.' || $parent === '\\') {
                return null;
            }

            if (preg_match('/^[A-Za-z]:$/', $parent) === 1) {
                return $parent.'\\';
            }

            return $parent;
        }

        if ($path === '/') {
            return self::COMPUTER_ROOT;
        }

        $parent = dirname($path);

        return $parent === '' ? '/' : $parent;
    }

    private static function isBlockedPath(string $realpath): bool
    {
        $normalized = self::compareKey($realpath);
        $app = self::compareKey(base_path());
        $storageApp = self::compareKey(storage_path('app'));

        if (str_starts_with($normalized, $app.DIRECTORY_SEPARATOR) || $normalized === $app) {
            if (! str_starts_with($normalized, $storageApp.DIRECTORY_SEPARATOR) && $normalized !== $storageApp) {
                return true;
            }
        }

        foreach (self::blockedPrefixes() as $prefix) {
            $blocked = self::compareKey($prefix);

            if ($normalized === $blocked || str_starts_with($normalized, $blocked.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function blockedPrefixes(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $prefixes = [];

            foreach (range('A', 'Z') as $letter) {
                $prefixes[] = $letter.':\\Windows';
                $prefixes[] = $letter.':\\Program Files';
                $prefixes[] = $letter.':\\Program Files (x86)';
                $prefixes[] = $letter.':\\ProgramData';
            }

            return $prefixes;
        }

        return ['/etc', '/bin', '/sbin', '/usr', '/boot', '/proc', '/sys', '/dev', '/root', '/lib', '/lib64'];
    }

    /**
     * @return list<array{label: string, path: string, kind: string}>
     */
    private static function candidateShortcuts(): array
    {
        $items = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $public = getenv('PUBLIC') ?: 'C:\\Users\\Public';
            $profile = getenv('USERPROFILE') ?: '';

            $items[] = [
                'label' => 'Documents publik',
                'path' => $public.DIRECTORY_SEPARATOR.'Documents',
                'kind' => 'public',
            ];

            if ($profile !== '') {
                $items[] = [
                    'label' => 'Documents',
                    'path' => $profile.DIRECTORY_SEPARATOR.'Documents',
                    'kind' => 'user',
                ];
                $items[] = [
                    'label' => 'Desktop',
                    'path' => $profile.DIRECTORY_SEPARATOR.'Desktop',
                    'kind' => 'user',
                ];
            }

            return $items;
        }

        $home = getenv('HOME') ?: '/home';

        $items[] = [
            'label' => 'Documents',
            'path' => $home.'/Documents',
            'kind' => 'user',
        ];
        $items[] = [
            'label' => 'Desktop',
            'path' => $home.'/Desktop',
            'kind' => 'user',
        ];

        return $items;
    }

    /**
     * @return array{
     *     path: string,
     *     parent: null,
     *     writable: false,
     *     computer: true,
     *     entries: list<array{name: string, path: string, kind: string}>,
     *     shortcuts: list<array{label: string, path: string, kind: string}>
     * }
     */
    private static function browseComputer(): array
    {
        $entries = [];

        foreach (self::connectedVolumes() as $volume) {
            $entries[] = [
                'name' => $volume['label'],
                'path' => $volume['path'],
                'kind' => $volume['kind'],
            ];
        }

        return [
            'path' => self::COMPUTER_ROOT,
            'parent' => null,
            'writable' => false,
            'computer' => true,
            'entries' => $entries,
            'shortcuts' => self::shortcuts(),
        ];
    }

    /**
     * Connected internal, external, and network drives.
     *
     * @return list<array{label: string, path: string, kind: string}>
     */
    private static function connectedVolumes(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return self::windowsConnectedVolumes();
        }

        $volumes = [
            [
                'label' => 'Akar sistem',
                'path' => '/',
                'kind' => 'drive',
            ],
        ];

        foreach (['/media', '/mnt', '/run/media'] as $mountRoot) {
            if (! is_dir($mountRoot)) {
                continue;
            }

            $names = @scandir($mountRoot) ?: [];

            foreach ($names as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }

                $full = $mountRoot.'/'.$name;

                if (! is_dir($full)) {
                    continue;
                }

                $volumes[] = [
                    'label' => $name,
                    'path' => $full,
                    'kind' => 'removable',
                ];
            }
        }

        return $volumes;
    }

    /**
     * @return list<array{label: string, path: string, kind: string}>
     */
    private static function windowsConnectedVolumes(): array
    {
        $volumes = [];
        $meta = self::windowsLogicalDisks();
        $letters = $meta !== [] ? array_keys($meta) : self::windowsFallbackDriveLetters();

        foreach ($letters as $letter) {
            $letter = strtoupper((string) $letter);
            $root = $letter.':\\';

            if (! self::isVolumeReady($root)) {
                continue;
            }

            $type = (int) ($meta[$letter]['type'] ?? self::DRIVE_TYPE_FIXED);

            if ($type === self::DRIVE_TYPE_CDROM) {
                continue;
            }

            $kind = match ($type) {
                self::DRIVE_TYPE_REMOVABLE => 'removable',
                self::DRIVE_TYPE_NETWORK => 'network',
                default => 'drive',
            };

            $volumes[] = [
                'label' => self::windowsVolumeLabel($letter, $type, (string) ($meta[$letter]['name'] ?? '')),
                'path' => $root,
                'kind' => $kind,
            ];
        }

        return $volumes;
    }

    /**
     * @return array<string, array{type: int, name: string}>
     */
    private static function windowsLogicalDisks(): array
    {
        $lines = [];
        @exec('wmic logicaldisk get DeviceID,DriveType,VolumeName /format:csv 2>NUL', $lines);

        $disks = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);

            if ($line === '' || str_starts_with($line, 'Node,')) {
                continue;
            }

            $parts = str_getcsv($line);

            if (count($parts) < 3) {
                continue;
            }

            $device = (string) ($parts[1] ?? '');

            if (preg_match('/^([A-Za-z]):$/', $device, $matches) !== 1) {
                continue;
            }

            $disks[strtoupper($matches[1])] = [
                'type' => (int) ($parts[2] ?? self::DRIVE_TYPE_FIXED),
                'name' => trim((string) ($parts[3] ?? '')),
            ];
        }

        return $disks;
    }

    /**
     * @return list<string>
     */
    private static function windowsFallbackDriveLetters(): array
    {
        $letters = [];

        foreach (range('C', 'Z') as $letter) {
            if (self::isVolumeReady($letter.':\\')) {
                $letters[] = $letter;
            }
        }

        return $letters;
    }

    private static function isVolumeReady(string $root): bool
    {
        clearstatcache(true, $root);

        if (@is_dir($root)) {
            return true;
        }

        return @disk_free_space($root) !== false;
    }

    private static function windowsVolumeLabel(string $letter, int $type, string $volumeName): string
    {
        $suffix = $letter.':';

        if ($volumeName !== '') {
            return $volumeName.' ('.$suffix.')';
        }

        return match ($type) {
            self::DRIVE_TYPE_REMOVABLE => 'Disk eksternal '.$suffix,
            self::DRIVE_TYPE_NETWORK => 'Drive jaringan '.$suffix,
            default => 'Drive '.$suffix,
        };
    }

    private static function isSkippedDirectoryName(string $name): bool
    {
        $lower = strtolower($name);

        return in_array($lower, [
            '$recycle.bin',
            'system volume information',
            'recovery',
            'config.msi',
            'documents and settings',
        ], true);
    }

    /**
     * Move the archive parent intact, including subfolders and files.
     * The legacy name finalproject is renamed to ARSIP TUGAS AKHIR.
     */
    private static function relocateArchive(string $fromRoot, string $toRoot): int
    {
        if (! is_dir($toRoot) && ! @mkdir($toRoot, 0775, true) && ! is_dir($toRoot)) {
            throw ValidationException::withMessages([
                'storage_path' => 'Gagal menyiapkan folder tujuan. Periksa izin, lalu coba lagi.',
            ]);
        }

        set_time_limit(0);

        $destination = rtrim($toRoot, '\\/').DIRECTORY_SEPARATOR.self::ARCHIVE_DIRECTORY;
        $sources = self::archiveSources($fromRoot, $toRoot);

        if ($sources === []) {
            self::reportProgress(70, 'move', 'Memindah arsip', 'Tidak ada folder arsip yang perlu dipindah.', 0, 0, null, true);

            return 0;
        }

        $moved = 0;
        $sourceCount = count($sources);

        foreach ($sources as $index => $source) {
            $from = 8 + (int) floor(($index / $sourceCount) * 62);
            $to = 8 + (int) floor((($index + 1) / $sourceCount) * 62);
            self::assertRelocationSafe($source, $destination);
            self::reportProgress(
                $from,
                'move',
                'Memindah arsip',
                'Memindah folder '.basename($source).'…',
                $index + 1,
                $sourceCount,
                basename($source),
                true
            );
            $moved += self::moveArchiveFolder($source, $destination, $from, $to);
        }

        self::reportProgress(70, 'move', 'Memindah arsip', 'Folder arsip sudah berada di lokasi baru.', $sourceCount, $sourceCount, null, true);

        return $moved;
    }

    /**
     * @return list<string>
     */
    private static function archiveDirectoryNames(): array
    {
        return [
            self::ARCHIVE_DIRECTORY,
            self::LEGACY_ARCHIVE_DIRECTORY,
        ];
    }

    /**
     * @return list<string>
     */
    private static function archiveSources(string $previousRoot, string $newRoot): array
    {
        $sources = [];
        $seen = [];
        $destination = rtrim($newRoot, '\\/').DIRECTORY_SEPARATOR.self::ARCHIVE_DIRECTORY;
        $roots = [$previousRoot, self::defaultRoot(), $newRoot];

        foreach ($roots as $root) {
            foreach (self::archiveDirectoryNames() as $name) {
                $source = rtrim($root, '\\/').DIRECTORY_SEPARATOR.$name;
                $key = self::compareKey($source);

                if (isset($seen[$key]) || ! is_dir($source) || self::samePath($source, $destination)) {
                    continue;
                }

                $seen[$key] = true;
                $sources[] = $source;
            }
        }

        return $sources;
    }

    /**
     * Reorganize registered files into type / year / cluster / study program folders.
     */
    public static function reorganizeStoredFiles(): int
    {
        $moved = 0;
        $query = FinalProject::query()
            ->where(function ($inner) {
                $inner->where(function ($file) {
                    $file->whereNotNull('final_project_file')->where('final_project_file', '!=', '');
                })->orWhere(function ($sheet) {
                    $sheet->whereNotNull('approval_sheet')->where('approval_sheet', '!=', '');
                });
            });

        $total = (clone $query)->count();

        if ($total === 0) {
            self::reportProgress(86, 'reorganize', 'Menyusun berkas', 'Tidak ada berkas terdaftar yang perlu disusun ulang.', 0, 0, null, true);

            return 0;
        }

        $done = 0;

        $query->with(['finalProjectType', 'studyProgram'])
            ->chunkById(100, function ($projects) use (&$moved, &$done, $total) {
                foreach ($projects as $project) {
                    $moved += self::reorganizeProjectFiles($project);
                    $done++;
                    $name = trim((string) $project->student_name);
                    self::reportRange(
                        74,
                        86,
                        $done,
                        $total,
                        'reorganize',
                        'Menyusun berkas',
                        $name !== ''
                            ? 'Menyusun berkas '.$name.' ('.$done.' dari '.$total.')…'
                            : 'Menyusun berkas '.$done.' dari '.$total.'…',
                        $name !== '' ? $name : null
                    );
                }
            });

        self::reportProgress(86, 'reorganize', 'Menyusun berkas', 'Penyusunan berkas selesai.', $total, $total, null, true);

        return $moved;
    }

    private static function reorganizeProjectFiles(FinalProject $project): int
    {
        $moved = 0;
        $updates = [];

        $projectFile = self::alignStoredFile(
            $project->final_project_file,
            self::directoryForProject($project, self::DOCUMENT_FINAL_PROJECT),
            (string) $project->student_name,
            (string) $project->nim
        );

        if ($projectFile !== $project->final_project_file) {
            $updates['final_project_file'] = $projectFile;
            $moved++;
        }

        $approvalSheet = self::alignStoredFile(
            $project->approval_sheet,
            self::directoryForProject($project, self::DOCUMENT_APPROVAL_SHEET),
            (string) $project->student_name,
            (string) $project->nim
        );

        if ($approvalSheet !== $project->approval_sheet) {
            $updates['approval_sheet'] = $approvalSheet;
            $moved++;
        }

        if ($updates !== []) {
            $project->forceFill($updates)->save();
        }

        return $moved;
    }

    public static function directoryForProject(FinalProject $project, string $documentKind): string
    {
        $program = (string) ($project->studyProgram?->study_program ?: $project->program_study);

        return self::documentDirectoryFromCatalog(
            $documentKind,
            $project->final_project_type_id,
            $project->degree_level,
            $project->final_project_year,
            $program,
            $project->study_program_id
        );
    }

    /**
     * ARSIP TUGAS AKHIR keeps only combinations that contain files.
     * Empty folders (no final-project file or approval sheet) are removed.
     */
    public static function syncFoldersFromDatabase(?string $root = null): int
    {
        $root ??= self::configuredRoot();
        $archive = rtrim($root, '\\/').DIRECTORY_SEPARATOR.self::ARCHIVE_DIRECTORY;

        if (! is_dir($archive) && ! @mkdir($archive, 0775, true) && ! is_dir($archive)) {
            return 0;
        }

        self::reportProgress(
            92,
            'sync',
            'Menyinkronkan folder',
            'Menggabungkan folder bernama sama dan menghapus folder kosong…',
            0,
            0,
            null,
            true
        );
        self::removeUnusedLegacyFolders($root);
        self::mergeDuplicateNamedFolders($root);
        self::pruneEmptyArchiveFolders($root);
        self::reportProgress(
            96,
            'sync',
            'Menyinkronkan folder',
            'Folder kosong sudah dibersihkan.',
            0,
            0,
            null,
            true
        );

        return 0;
    }

    /**
     * Document folders are created only when a file is uploaded.
     * This method removes empty folders after final-project data is saved.
     */
    public static function ensureProjectFieldFolders(
        mixed $degreeLevel,
        mixed $year,
        mixed $programStudy,
        ?string $root = null
    ): int {
        return self::ensureProjectFolders($degreeLevel, $year, $programStudy, $root);
    }

    public static function ensureProjectFolders(
        mixed $degreeLevel,
        mixed $year,
        mixed $programStudy,
        ?string $root = null,
        mixed $typeId = null,
        mixed $studyProgramId = null,
        bool $pruneLegacy = true
    ): int {
        $root ??= self::configuredRoot();

        if ($pruneLegacy) {
            self::removeUnusedLegacyFolders($root);
        }

        foreach ([self::DOCUMENT_FINAL_PROJECT, self::DOCUMENT_APPROVAL_SHEET] as $kind) {
            self::pruneEmptyRelativeBranch($root, self::documentDirectoryFromCatalog(
                $kind,
                $typeId,
                $degreeLevel,
                $year,
                $programStudy,
                $studyProgramId
            ));
        }

        return 0;
    }

    /**
     * Remove unused legacy final_project and approval_sheet folders.
     */
    public static function removeUnusedLegacyFolders(?string $root = null): void
    {
        $root ??= self::configuredRoot();

        foreach ([self::ARCHIVE_DIRECTORY, self::LEGACY_ARCHIVE_DIRECTORY] as $archiveName) {
            $parent = rtrim($root, '\\/').DIRECTORY_SEPARATOR.$archiveName;

            foreach (['final_project', 'approval_sheet'] as $legacy) {
                $directory = $parent.DIRECTORY_SEPARATOR.$legacy;

                if (is_dir($directory)) {
                    self::removeDirectoryTree($directory);
                }
            }

            if ($archiveName === self::LEGACY_ARCHIVE_DIRECTORY && is_dir($parent) && self::directoryIsEmpty($parent)) {
                @rmdir($parent);
            }
        }
    }

    /**
     * Merge sibling folders that match after normalization
     * (case, spacing, and the RUMPUN prefix on cluster folders).
     */
    private static function mergeDuplicateNamedFolders(?string $root = null): void
    {
        $root ??= self::configuredRoot();
        $archive = rtrim($root, '\\/').DIRECTORY_SEPARATOR.self::ARCHIVE_DIRECTORY;

        if (! is_dir($archive)) {
            return;
        }

        try {
            $directories = [$archive];
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($archive, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    $directories[] = $item->getPathname();
                }
            }

            foreach ($directories as $directory) {
                if (is_dir($directory)) {
                    self::mergeDuplicateChildren($directory);
                }
            }
        } catch (\Throwable) {
            return;
        }
    }

    private static function mergeDuplicateChildren(string $parent): void
    {
        $items = @scandir($parent);

        if (! is_array($items)) {
            return;
        }

        /** @var array<string, list<string>> $groups */
        $groups = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $parent.DIRECTORY_SEPARATOR.$item;

            if (! is_dir($path)) {
                continue;
            }

            $groups[self::duplicateFolderKey($item, $parent)][] = $path;
        }

        foreach ($groups as $canonicalName => $paths) {
            if ($canonicalName === '' || count($paths) < 2) {
                continue;
            }

            $destination = $parent.DIRECTORY_SEPARATOR.$canonicalName;
            $chosen = null;

            foreach ($paths as $path) {
                if (self::samePath($path, $destination)) {
                    $chosen = $path;
                    break;
                }
            }

            if ($chosen === null) {
                $first = $paths[0];

                if (! is_dir($destination) && @rename($first, $destination) && is_dir($destination)) {
                    $chosen = $destination;
                } elseif (is_dir($destination)) {
                    $chosen = $destination;
                    if (! self::samePath($first, $destination)) {
                        try {
                            self::mergeArchiveFolder($first, $chosen);
                        } catch (\Throwable) {
                            $chosen = $first;
                        }
                    }
                } else {
                    $chosen = $first;
                    $destination = $first;
                }
            }

            foreach ($paths as $path) {
                if (! is_dir($path) || self::samePath($path, $chosen)) {
                    continue;
                }

                try {
                    self::mergeArchiveFolder($path, $chosen);
                } catch (\Throwable) {
                    continue;
                }
            }
        }
    }

    /**
     * Merge key: the normalized folder name.
     * Under a year folder, the same cluster is unified with or without the RUMPUN prefix.
     */
    private static function duplicateFolderKey(string $name, string $parent): string
    {
        $canonical = self::folderSegment($name);

        if ($canonical === '') {
            return mb_strtoupper(trim($name), 'UTF-8');
        }

        if (self::parentLooksLikeYearFolder($parent) && self::looksLikeClusterFolder($canonical)) {
            return self::clusterFolderName($canonical);
        }

        return $canonical;
    }

    private static function parentLooksLikeYearFolder(string $parent): bool
    {
        $name = basename($parent);

        return preg_match('/^\d{4}$/', $name) === 1 || $name === 'TANPA TAHUN';
    }

    private static function looksLikeClusterFolder(string $canonicalName): bool
    {
        if (str_starts_with($canonicalName, 'RUMPUN ')) {
            return true;
        }

        self::hydrateProgramCache();
        $key = self::clusterKey($canonicalName);

        return $key !== '' && isset(self::$clustersByKey[$key]);
    }

    /**
     * Prune empty parent folders of a just-deleted file, stopping at the archive root.
     */
    private static function pruneDeletedParent(Filesystem $disk, string $relativePath): void
    {
        if (! method_exists($disk, 'path')) {
            return;
        }

        try {
            $absolute = $disk->path($relativePath);
        } catch (\Throwable) {
            return;
        }

        self::pruneEmptyAbsoluteBranch(dirname($absolute));
    }

    /**
     * Remove the empty-folder chain on one document path, not the whole archive.
     */
    private static function pruneEmptyRelativeBranch(string $root, string $relative): void
    {
        $relative = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);

        if ($relative === '') {
            return;
        }

        self::pruneEmptyAbsoluteBranch(rtrim($root, '\\/').DIRECTORY_SEPARATOR.$relative);
    }

    private static function pruneEmptyAbsoluteBranch(string $directory): void
    {
        $directory = self::normalizeSeparators($directory);
        $archiveRoots = [];

        foreach (array_unique(array_filter([self::configuredRoot(), self::defaultRoot()])) as $root) {
            foreach (self::archiveDirectoryNames() as $name) {
                $archiveRoots[] = rtrim($root, '\\/').DIRECTORY_SEPARATOR.$name;
            }
        }

        $current = $directory;

        for ($guard = 0; $guard < 12 && is_dir($current); $guard++) {
            $inside = false;

            foreach ($archiveRoots as $archive) {
                if (self::samePath($current, $archive)) {
                    return;
                }

                if (self::pathIsInside($current, $archive)) {
                    $inside = true;
                    break;
                }
            }

            if (! $inside || ! self::directoryIsEmpty($current)) {
                return;
            }

            $parent = dirname($current);

            if (! @rmdir($current)) {
                return;
            }

            $current = $parent;
        }
    }

    /**
     * Remove empty folders inside ARSIP TUGAS AKHIR, including
     * empty BERKAS TUGAS AKHIR or LEMBAR PERSETUJUAN folders.
     */
    private static function pruneEmptyArchiveFolders(?string $root = null): void
    {
        $root ??= self::configuredRoot();
        $archive = rtrim($root, '\\/').DIRECTORY_SEPARATOR.self::ARCHIVE_DIRECTORY;

        if (! is_dir($archive)) {
            return;
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($archive, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                if (! $item->isDir()) {
                    continue;
                }

                $path = $item->getPathname();

                if (self::directoryIsEmpty($path)) {
                    @rmdir($path);
                }
            }
        } catch (\Throwable) {
            return;
        }
    }

    private static function directoryIsEmpty(string $directory): bool
    {
        $items = @scandir($directory);

        if (! is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..') {
                return false;
            }
        }

        return true;
    }

    private static function assertNotArchiveLocation(string $resolved, string $previousRoot): void
    {
        foreach ([$previousRoot, self::defaultRoot()] as $root) {
            foreach (self::archiveDirectoryNames() as $name) {
                $archive = rtrim($root, '\\/').DIRECTORY_SEPARATOR.$name;

                if (! self::samePath($resolved, $archive) && ! (is_dir($archive) && self::pathIsInside($resolved, $archive))) {
                    continue;
                }

                throw ValidationException::withMessages([
                    'storage_path' => 'Folder tujuan tidak boleh berada di dalam folder berkas tugas akhir yang sedang dipindah.',
                ]);
            }
        }
    }

    private static function assertRelocationSafe(string $source, string $destination): void
    {
        if (self::samePath($source, $destination)) {
            return;
        }

        if (self::pathIsInside($destination, $source)) {
            throw ValidationException::withMessages([
                'storage_path' => 'Folder tujuan tidak boleh berada di dalam folder berkas tugas akhir yang sedang dipindah.',
            ]);
        }
    }

    /**
     * Move the archive parent folder and all of its contents.
     * Prefer a whole-folder rename; across drives, copy then delete the source.
     */
    private static function moveArchiveFolder(string $source, string $destination, int $percentFrom = 8, int $percentTo = 70): int
    {
        if (! is_dir($source) || self::samePath($source, $destination)) {
            return 0;
        }

        self::reportProgress(
            $percentFrom,
            'move',
            'Memindah arsip',
            'Menghitung isi folder ARSIP TUGAS AKHIR…',
            0,
            0,
            basename($source),
            true
        );
        $itemCount = self::countArchiveItems($source);

        if (! is_dir($destination)) {
            $parent = dirname($destination);

            if (! is_dir($parent) && ! @mkdir($parent, 0775, true) && ! is_dir($parent)) {
                throw ValidationException::withMessages([
                    'storage_path' => 'Gagal membuat folder tujuan untuk ARSIP TUGAS AKHIR.',
                ]);
            }

            if (@rename($source, $destination) && is_dir($destination)) {
                self::reportProgress(
                    $percentTo,
                    'move',
                    'Memindah arsip',
                    'Folder ARSIP TUGAS AKHIR dipindah secara utuh.',
                    $itemCount,
                    $itemCount,
                    basename($source),
                    true
                );

                return $itemCount;
            }

            if (! self::copyDirectoryTree($source, $destination, $percentFrom, $percentTo, $itemCount)) {
                throw ValidationException::withMessages([
                    'storage_path' => 'Gagal memindah folder ARSIP TUGAS AKHIR ke lokasi baru. Periksa ruang dan izin disk.',
                ]);
            }

            self::reportProgress(
                $percentTo,
                'move',
                'Memindah arsip',
                'Menyalin selesai. Membersihkan folder sumber…',
                $itemCount,
                $itemCount,
                basename($source),
                true
            );
            self::removeDirectoryTree($source);

            return $itemCount;
        }

        return self::mergeArchiveFolder($source, $destination, $percentFrom, $percentTo, $itemCount);
    }

    private static function mergeArchiveFolder(
        string $source,
        string $destination,
        int $percentFrom = 8,
        int $percentTo = 70,
        int $itemCount = 0
    ): int {
        $moved = 0;
        $failed = 0;
        $processed = 0;
        $sourcePrefix = rtrim($source, '\\/').DIRECTORY_SEPARATOR;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($sourcePrefix));
            $target = $destination.DIRECTORY_SEPARATOR.$relative;
            $processed++;

            if ($item->isDir()) {
                if (! is_dir($target) && ! @mkdir($target, 0775, true) && ! is_dir($target)) {
                    $failed++;
                } else {
                    $moved++;
                }

                self::reportCopyProgress($percentFrom, $percentTo, $processed, $itemCount, $item->getFilename());

                continue;
            }

            if (is_file($target)) {
                @unlink($item->getPathname());
                $moved++;
                self::reportCopyProgress($percentFrom, $percentTo, $processed, $itemCount, $item->getFilename());

                continue;
            }

            if (self::moveFile($item->getPathname(), $target)) {
                $moved++;
            } else {
                $failed++;
            }

            self::reportCopyProgress($percentFrom, $percentTo, $processed, $itemCount, $item->getFilename());
        }

        self::removeDirectoryTree($source);

        if ($failed > 0) {
            throw ValidationException::withMessages([
                'storage_path' => 'Sebagian folder atau berkas gagal dipindah ('.$failed.'). Periksa izin folder tujuan, lalu simpan lagi.',
            ]);
        }

        return $moved;
    }

    private static function copyDirectoryTree(
        string $source,
        string $destination,
        int $percentFrom = 8,
        int $percentTo = 70,
        int $itemCount = 0
    ): bool {
        if (! is_dir($destination) && ! @mkdir($destination, 0775, true) && ! is_dir($destination)) {
            return false;
        }

        $processed = 0;
        $sourcePrefix = rtrim($source, '\\/').DIRECTORY_SEPARATOR;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $destination.DIRECTORY_SEPARATOR.substr($item->getPathname(), strlen($sourcePrefix));
            $processed++;

            if ($item->isDir()) {
                if (! is_dir($target) && ! @mkdir($target, 0775, true) && ! is_dir($target)) {
                    return false;
                }

                self::reportCopyProgress($percentFrom, $percentTo, $processed, $itemCount, $item->getFilename());

                continue;
            }

            $directory = dirname($target);

            if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
                return false;
            }

            if (! @copy($item->getPathname(), $target) || ! is_file($target)) {
                return false;
            }

            self::reportCopyProgress($percentFrom, $percentTo, $processed, $itemCount, $item->getFilename());
        }

        return true;
    }

    private static function reportCopyProgress(int $from, int $to, int $current, int $total, string $name): void
    {
        $total = max($total, $current);
        self::reportRange(
            $from,
            $to,
            $current,
            $total,
            'move',
            'Memindah arsip',
            'Memindah '.$name.' ('.$current.' dari '.$total.')…',
            $name
        );
    }

    private static function countArchiveItems(string $directory): int
    {
        if (! is_dir($directory)) {
            return 0;
        }

        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $count++;
        }

        return $count;
    }

    private static function removeDirectoryTree(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($directory);
    }

    private static function moveFile(string $from, string $to): bool
    {
        $directory = dirname($to);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return false;
        }

        if (@rename($from, $to)) {
            return true;
        }

        if (@copy($from, $to) && is_file($to)) {
            @unlink($from);

            return true;
        }

        @unlink($to);

        return false;
    }

    private static function pathIsInside(string $inner, string $outer): bool
    {
        $innerKey = rtrim(self::compareKey($inner), '\\/').DIRECTORY_SEPARATOR;
        $outerKey = rtrim(self::compareKey($outer), '\\/').DIRECTORY_SEPARATOR;

        return str_starts_with($innerKey, $outerKey);
    }

    /**
     * Real write test. On Windows, is_writable() is often wrong
     * for Desktop/Documents folders marked with the Read-only attribute.
     */
    private static function directoryIsWritable(string $path): bool
    {
        if (! is_dir($path)) {
            return false;
        }

        $probe = $path.DIRECTORY_SEPARATOR.'arsip-ta-write-'.bin2hex(random_bytes(4)).'.tmp';
        $written = @file_put_contents($probe, 'ok');

        if ($written === false) {
            return false;
        }

        @unlink($probe);

        return true;
    }

    private static function samePath(string $left, string $right): bool
    {
        return self::compareKey($left) === self::compareKey($right);
    }

    private static function compareKey(string $path): string
    {
        $normalized = self::displayPath($path);

        if (PHP_OS_FAMILY === 'Windows') {
            return strtolower($normalized);
        }

        return $normalized;
    }

    private static function normalizeSeparators(string $path): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $path = str_replace('/', '\\', $path);

            if (preg_match('/^[A-Za-z]:$/', $path) === 1) {
                $path .= '\\';
            }

            return $path;
        }

        return str_replace('\\', '/', $path);
    }
}
