<?php

namespace App\Setup\Crud;

use App\Models\Report;
use App\Support\DataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD Laporan.
 * Anak dari ArchiveCrud. Controller: ReportController.
 */
class ReportCrud extends ArchiveCrud
{
    // --- Baca, simpan, dan hapus ---

    public function listReports(Request $request): JsonResponse
    {
        return DataTables::make(
            $request,
            Report::query()
                ->select(['id', 'description', 'final_project_id', 'user_id', 'created_at', 'updated_at'])
                ->with([
                    'user:id,name',
                    'finalProject' => function ($query) {
                        $query->select([
                            'id', 'nim', 'student_name', 'final_project_title', 'program_study',
                            'degree_level', 'final_project_year', 'submission_date', 'study_program_id',
                        ])->withExists(['bookGrants', 'libraryClearances'])
                            ->with([
                                'studyProgram:id,study_program,cluster',
                                'latestBarcode:id,final_project_id,barcode',
                                'latestLibraryClearance:id,final_project_id,graduation_year,semester_graduated',
                                'latestArchive:id,final_project_id,description',
                            ]);
                    },
                ]),
            function (Report $report, int $number) {
                $project = $report->finalProject;
                $clearance = $project?->latestLibraryClearance;
                [$nim, $studentName, , $programStudy, $degreeLevel, $year, $submissionDate]
                    = $this->mapFinalProjectDisplayFields($project);

                return [
                    $number,
                    $report->id,
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
                    $this->dash($project?->latestBarcode?->barcode),
                    $project?->book_grants_exists ? 'Ya' : 'Tidak',
                    $project?->library_clearances_exists ? 'Ya' : 'Tidak',
                    $this->formatValidityDeadline($year, $this->listingYear()),
                    $this->dash($project?->latestArchive?->description),
                    $this->dash($report->description),
                    $this->dash($report->user?->name),
                    $this->dateLabel($report->created_at),
                    $this->dateLabel($report->updated_at),
                    $report->final_project_id,
                    $report->user_id,
                ];
            },
            $this->finalProjectOrderMap('reports') + [
                'description' => 'description',
            ],
            [],
            fn ($query, string $keyword) => $this->searchOwnAndFinalProject(
                $query,
                $keyword,
                ['description']
            )
        );
    }

    public function saveReport($request): JsonResponse
    {
        $request->validate([
            'reportid' => ['nullable', 'integer', 'exists:reports,id'],
            'description' => ['required', 'string'],
            'final_project_id' => ['required', 'integer', 'exists:final__projects,id'],
        ]);

        $this->updateOrCreateReport(
            $request->reportid,
            $request->description,
            $request->final_project_id,
            $request->user()->id
        );

        return response()->json('Data Laporan Berhasil Disimpan');
    }

    public function destroyReport($id): JsonResponse
    {
        if (! $this->deleteReport((int) $id)) {
            return response()->json('Hapus Gagal', 404);
        }

        return response()->json('Hapus Laporan Berhasil');
    }
}
