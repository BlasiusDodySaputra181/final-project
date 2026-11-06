<?php

namespace App\Setup\Crud;

use App\Models\FinalProject;
use App\Support\DataTables;
use App\Support\KeywordSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Halaman cari tugas akhir untuk mahasiswa.
 * Anak dari SubmissionCrud. Controller: SearchController.
 */
class SearchCrud extends SubmissionCrud
{
    // --- Halaman cari dan filter ---

    /**
     * Kolom final__projects yang dicari di modul Cari (tanpa foreign key).
     *
     * @return list<string>
     */
    private function publicSearchColumns(): array
    {
        return [
            'nim',
            'student_name',
            'final_project_title',
            'program_study',
            'degree_level',
            'final_project_year',
        ];
    }

    /**
     * Modul cari: shell halaman (data diisi via DataTables AJAX).
     */
    public function showPublicSearch(): View
    {
        return view('student.search', [
            'student' => Auth::guard('student')->user(),
            'searchIndexUrl' => route('index-public-search'),
        ]);
    }

    /**
     * DataTables server-side: daftar / filter tugas akhir untuk modul Cari.
     * Tanpa kata kunci, seluruh tugas akhir ditampilkan; filter mulai 2 karakter.
     */
    public function listPublicSearch(Request $request): JsonResponse
    {
        $searchColumns = $this->publicSearchColumns();
        $keyword = trim((string) $request->input('q', ''));

        if (mb_strlen($keyword) < 2) {
            $keyword = '';
        }

        $query = FinalProject::query()
            ->select(array_merge(['id'], $searchColumns))
            ->with('latestBarcode:id,final_project_id,barcode');

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
                    $project->latestBarcode?->barcode ?: '-',
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
                'call_number' => fn ($builder, string $dir) => DataTables::orderByLatestRelated(
                    $builder,
                    'barcodes',
                    'barcode',
                    'final__projects.id',
                    'final_project_id',
                    $dir
                ),
            ],
            'id',
            'desc',
            null,
            [],
            $keyword === '' ? null : function ($builder, string $kw) {
                $builder->where(function ($inner) use ($kw) {
                    KeywordSearch::finalProjects($inner, $kw);
                    KeywordSearch::orBarcode($inner, $kw);
                });
            },
            $keyword
        );
    }
}
