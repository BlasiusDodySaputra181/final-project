<?php

namespace App\Setup\Crud;

use App\Models\Archive;
use App\Models\FinalProject;
use App\Support\DataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD Arsip.
 * Anak dari DestructionCrud. Controller: ArchiveController.
 */
class ArchiveCrud extends DestructionCrud
{
    // --- Baca, simpan, dan hapus ---

    public function listArchives(Request $request): JsonResponse
    {
        return DataTables::make(
            $request,
            Archive::query()
                ->select(['id', 'description', 'final_project_id', 'created_at', 'updated_at'])
                ->with(FinalProject::eagerCard()),
            function (Archive $archive, int $number) {
                $project = $archive->finalProject;
                [$nim, $studentName, , $programStudy, $degreeLevel, $year, $submissionDate]
                    = $this->mapFinalProjectDisplayFields($project);

                return [
                    $number,
                    $archive->id,
                    $nim,
                    $studentName,
                    $project?->final_project_title ?? '',
                    $programStudy,
                    $degreeLevel,
                    $this->dash($year),
                    $this->dateLabel($submissionDate),
                    $this->validityLabel($year),
                    $this->formatValidityDeadline($year, $this->listingYear()),
                    $this->dash($archive->description),
                    $this->dateLabel($archive->created_at),
                    $this->dateLabel($archive->updated_at),
                    $archive->final_project_id,
                ];
            },
            $this->finalProjectOrderMap('archives') + [
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

    public function saveArchive($request): JsonResponse
    {
        $request->validate([
            'archiveid' => ['nullable', 'integer', 'exists:archives,id'],
            'description' => ['required', 'string'],
            'final_project_id' => ['required', 'integer', 'exists:final__projects,id'],
        ]);

        $this->updateOrCreateArchive(
            $request->archiveid,
            $request->description,
            $request->final_project_id
        );

        return response()->json('Data Arsip Berhasil Disimpan');
    }

    public function destroyArchive($id): JsonResponse
    {
        if (! $this->deleteArchive((int) $id)) {
            return response()->json('Hapus Gagal', 404);
        }

        return response()->json('Hapus Arsip Berhasil');
    }
}
