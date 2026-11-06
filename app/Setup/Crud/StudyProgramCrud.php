<?php

namespace App\Setup\Crud;

use App\Models\StudyProgram;
use App\Support\CatalogCache;
use App\Support\DataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * CRUD Program Studi.
 * Anak dari FinalProjectCrud. Controller: StudyProgramController.
 */
class StudyProgramCrud extends FinalProjectCrud
{
    // --- Baca, simpan, dan hapus ---

    /**
     * Opsi program studi untuk select form lain.
     */
    public function listStudyProgramOptions(): JsonResponse
    {
        return response()->json(
            Cache::remember(
                CatalogCache::STUDY_PROGRAMS,
                300,
                static fn () => StudyProgram::query()
                    ->orderBy('study_program')
                    ->get(['id', 'study_program', 'cluster'])
                    ->toArray()
            )
        );
    }

    public function listStudyPrograms(Request $request): JsonResponse
    {
        return DataTables::make(
            $request,
            StudyProgram::query()->select(['id', 'study_program', 'cluster', 'created_at', 'updated_at']),
            fn (StudyProgram $program, int $number) => [
                $number,
                $program->id,
                $program->study_program,
                $program->cluster,
                $this->dateLabel($program->created_at),
                $this->dateLabel($program->updated_at),
            ],
            [
                'id' => 'id',
                'study_program' => 'study_program',
                'cluster' => 'cluster',
            ],
            ['study_program', 'cluster']
        );
    }

    public function saveStudyProgram($request): JsonResponse
    {
        $request->validate([
            'studyprogramid' => ['nullable', 'integer', 'exists:study_programs,id'],
            'study_program' => ['required', 'string', 'max:255'],
            'cluster' => ['required', 'string', 'max:255'],
        ]);

        $this->updateOrCreateStudyProgram(
            $request->studyprogramid,
            $request->study_program,
            $request->cluster
        );

        $this->studyProgramIdByName = null;

        return response()->json('Data Program Studi Berhasil Disimpan');
    }

    public function destroyStudyProgram($id): JsonResponse
    {
        if ($this->deleteStudyProgram((int) $id) === 0) {
            return response()->json('Hapus Gagal', 404);
        }

        $this->studyProgramIdByName = null;

        return response()->json('Hapus Program Studi Berhasil');
    }
}
