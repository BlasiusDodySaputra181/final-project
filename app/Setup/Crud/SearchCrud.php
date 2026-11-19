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
 * Student page for searching final projects.
 * Extends SubmissionCrud. Controller: SearchController.
 */
class SearchCrud extends SubmissionCrud
{
    // --- Search page and filters ---

    /**
     * Columns of final__projects searched by the Search module (no foreign keys).
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
     * Search page shell. Rows are loaded through DataTables AJAX.
     */
    public function showPublicSearch(): View
    {
        return view('student.search', [
            'student' => Auth::guard('student')->user(),
            'searchIndexUrl' => route('index-public-search'),
        ]);
    }

    /**
     * Server-side DataTables list and filter of final projects for the Search module.
     * Without a keyword, every final project is shown. Filtering starts at 2 characters.
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
