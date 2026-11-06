<?php

namespace App\Setup\Crud;

use App\Models\Barcode;
use App\Models\FinalProject;
use App\Models\Setting;
use App\Setup\BarcodePdf;
use App\Setup\Code128Barcode;
use App\Support\DataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRUD Kode Batang, cetak, dan pemindaian publik.
 * Anak dari LibraryClearanceCrud. Controller: BarcodeController.
 */
class BarcodeCrud extends LibraryClearanceCrud
{
    // --- Daftar admin, cetak, dan pemindaian publik ---

    public function listBarcodes(Request $request): JsonResponse
    {
        $query = Barcode::query()
            ->select(['id', 'label', 'barcode', 'final_project_id', 'created_at', 'updated_at'])
            ->with(FinalProject::eagerCard('finalProject', [
                'latestLibraryClearance:id,final_project_id,graduation_year,semester_graduated',
            ]));

        $scan = trim((string) $request->input('scan', ''));

        if ($scan !== '') {
            $query->where('barcode', $scan);
        }

        return DataTables::make(
            $request,
            $query,
            function (Barcode $barcode, int $number) {
                $project = $barcode->finalProject;
                $clearance = $project?->latestLibraryClearance;
                [$nim, $studentName, , $programStudy, $degreeLevel, $year, $submissionDate]
                    = $this->mapFinalProjectDisplayFields($project);

                return [
                    $number,
                    $barcode->id,
                    $nim,
                    $studentName,
                    $project?->final_project_title ?? '',
                    $programStudy,
                    $degreeLevel,
                    $this->dash($year),
                    $this->dash($clearance?->graduation_year),
                    $this->dash($clearance?->semester_graduated),
                    $this->dateLabel($submissionDate),
                    $this->validityLabel($year),
                    $barcode->label,
                    $barcode->barcode,
                    $this->dateLabel($barcode->created_at),
                    $this->dateLabel($barcode->updated_at),
                    $barcode->final_project_id,
                ];
            },
            $this->finalProjectOrderMap('barcodes') + [
                'label' => 'label',
                'barcode' => 'barcode',
            ],
            [],
            fn ($builder, string $keyword) => $this->searchOwnAndFinalProject(
                $builder,
                $keyword,
                ['label', 'barcode']
            )
        );
    }

    public function saveBarcode($request): JsonResponse
    {
        $barcodeId = $request->filled('barcodeid')
            ? $request->integer('barcodeid')
            : null;

        $validated = $request->validate([
            'barcodeid' => ['nullable', 'integer', 'exists:barcodes,id'],
            'label' => ['required', 'string', 'max:255'],
            'barcode' => [
                'required',
                'string',
                'max:255',
                Rule::unique('barcodes', 'barcode')->ignore($barcodeId),
            ],
            'final_project_id' => ['required', 'integer', 'exists:final__projects,id'],
        ], [
            'barcode.required' => 'Kode batang wajib diisi.',
            'barcode.unique' => 'Kode batang sudah digunakan. Tidak dapat menyimpan data dengan kode yang sama.',
            'label.required' => 'Label barcode wajib diisi.',
            'final_project_id.required' => 'Tugas akhir harus dipilih.',
            'final_project_id.exists' => 'Tugas akhir tidak ditemukan.',
        ]);

        $this->updateOrCreateBarcode(
            $validated['barcodeid'] ?? null,
            $validated['label'],
            $validated['barcode'],
            $validated['final_project_id']
        );

        return response()->json('Data Kode Batang Berhasil Disimpan');
    }

    public function destroyBarcode($id): JsonResponse
    {
        if (! $this->deleteBarcode((int) $id)) {
            return response()->json('Hapus Gagal', 404);
        }

        return response()->json('Hapus Kode Batang Berhasil');
    }

    /**
     * Pratinjau cetak stiker barcode — A4 landscape, 15 stiker per lembar (3 kolom × 5 baris).
     */
    public function exportBarcodesPdf(): View
    {
        if (function_exists('ini_set')) {
            ini_set('memory_limit', '512M');
        }

        if (function_exists('set_time_limit')) {
            set_time_limit(180);
        }

        $encoder = new Code128Barcode;
        $maxStickers = 450;
        $items = [];

        $cursor = Barcode::query()
            ->whereNotNull('barcode')
            ->where('barcode', '!=', '')
            ->orderBy('barcode')
            ->select(['label', 'barcode'])
            ->cursor();

        foreach ($cursor as $row) {
            $code = (string) $row->barcode;

            if ($code === '') {
                continue;
            }

            $items[] = [
                'label' => (string) ($row->label ?? ''),
                'barcode' => $code,
                'svg' => $encoder->toSvg($code, 10),
            ];

            if (count($items) >= $maxStickers) {
                break;
            }
        }

        $perPage = BarcodePdf::cellsPerPage();
        $pages = collect($items)->chunk($perPage)->values();

        return view('module.print.barcodes', [
            'pages' => $pages,
            'columns' => BarcodePdf::COLUMNS,
            'rows' => BarcodePdf::ROWS,
        ]);
    }

    /**
     * Halaman pindai kode batang publik (mahasiswa, tanpa login).
     */
    public function showPublicBarcodeScan(): View
    {
        if (! Setting::isBarcodeAccessOpen()) {
            return view('student.access', [
                'errorMessage' => 'Akses pemindaian kode batang sedang ditutup oleh petugas.',
            ]);
        }

        return view('student.barcode');
    }

    /**
     * Lookup barcode publik → redirect ke metadata atau kembali dengan error.
     */
    public function lookupPublicBarcode(Request $request): JsonResponse|RedirectResponse
    {
        $wantsJson = $request->expectsJson() || $request->ajax();

        if (! Setting::isBarcodeAccessOpen()) {
            $message = 'Akses pemindaian kode batang sedang ditutup oleh petugas.';

            if ($wantsJson) {
                return response()->json([
                    'ok' => false,
                    'message' => $message,
                ], 403);
            }

            return redirect()
                ->route('page-public-barcode')
                ->with('error', $message);
        }

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:255'],
        ], [
            'barcode.required' => 'Kode batang wajib diisi.',
            'barcode.max' => 'Kode batang maksimal 255 karakter.',
        ]);

        $code = trim($validated['barcode']);
        $barcode = $this->findBarcodeByCode($code);

        if (! $barcode || ! $barcode->finalProject) {
            $errorMessage = 'Kode batang tidak ditemukan atau belum terhubung ke tugas akhir.';

            if ($wantsJson) {
                return response()->json([
                    'ok' => false,
                    'message' => $errorMessage,
                ], 404);
            }

            return redirect()
                ->route('page-public-barcode')
                ->with('error', $errorMessage);
        }

        $successMessage = 'Kode batang berhasil dipindai. Data tugas akhir ditemukan.';

        if ($wantsJson) {
            session()->flash('success', $successMessage);

            return response()->json([
                'ok' => true,
                'message' => $successMessage,
                'barcode' => $barcode->barcode,
                'redirect' => route('page-public-barcode-metadata', [
                    'barcode' => $barcode->barcode,
                ]),
            ]);
        }

        return redirect()
            ->route('page-public-barcode-metadata', [
                'barcode' => $barcode->barcode,
            ])
            ->with('success', $successMessage);
    }

    /**
     * Halaman metadata hasil pindai kode batang.
     */
    public function showPublicBarcodeMetadata(string $barcode): View|RedirectResponse
    {
        if (! Setting::isBarcodeAccessOpen()) {
            return view('student.access', [
                'errorMessage' => 'Akses pemindaian kode batang sedang ditutup oleh petugas.',
            ]);
        }

        $code = trim($barcode);
        $record = $this->findBarcodeByCode($code);

        if (! $record || ! $record->finalProject) {
            return redirect()
                ->route('page-public-barcode')
                ->with('error', 'Kode batang tidak ditemukan atau belum terhubung ke tugas akhir.');
        }

        $finalProject = $record->finalProject;
        $libraryClearance = $finalProject->latestLibraryClearance;
        $studyProgram = $finalProject->studyProgram;
        $hasFile = filled($finalProject->final_project_file);

        return view('student.metadata', [
            'barcode' => $record,
            'finalProject' => $finalProject,
            'libraryClearance' => $libraryClearance,
            'studyProgram' => $studyProgram,
            'documentAvailable' => $hasFile,
            'documentStatus' => $hasFile ? 'Tersedia' : 'Tidak Tersedia',
            'approvalSheetName' => $finalProject->approval_sheet
                ? basename($finalProject->approval_sheet)
                : null,
            'typeLabel' => $finalProject->finalProjectType?->type ?? 'Tugas Akhir',
        ]);
    }

    /**
     * Pra-unduh lembar persetujuan dari hasil pindai kode batang (inline di tab baru).
     */
    public function downloadPublicApprovalSheet(string $barcode): View|RedirectResponse|StreamedResponse
    {
        if (! Setting::isBarcodeAccessOpen()) {
            return view('student.access', [
                'errorMessage' => 'Akses pemindaian kode batang sedang ditutup oleh petugas.',
            ]);
        }

        $record = $this->findBarcodeByCode(trim($barcode));

        if (! $record || ! $record->finalProject) {
            return redirect()
                ->route('page-public-barcode')
                ->with('error', 'Kode batang tidak ditemukan atau belum terhubung ke tugas akhir.');
        }

        $path = $record->finalProject->approval_sheet;

        if (empty($path) || ! $this->existsInPublicStorage($path)) {
            abort(404, 'Berkas tidak ditemukan');
        }

        return $this->inlineFromPublicStorage(
            $path,
            $this->stripUuidPrefix($path)
        );
    }

    /**
     * Status akses pemindaian kode batang publik (untuk UI petugas).
     */
    public function barcodeAccessStatus(): JsonResponse
    {
        return $this->accessStatusResponse([Setting::class, 'isBarcodeAccessOpen']);
    }

    /**
     * Buka / tutup akses pemindaian kode batang publik.
     */
    public function toggleBarcodeAccess(Request $request): JsonResponse
    {
        return $this->toggleAccessResponse(
            $request,
            [Setting::class, 'setBarcodeAccessOpen'],
            [Setting::class, 'isBarcodeAccessOpen'],
            'Akses pemindaian kode batang dibuka.',
            'Akses pemindaian kode batang ditutup.'
        );
    }
}
