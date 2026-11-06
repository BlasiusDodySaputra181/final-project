<?php

namespace App\Setup\Crud;

use App\Models\Destruction;
use App\Models\FinalProject;
use App\Support\DataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD Pemusnahan dan persetujuan.
 * Anak dari SearchCrud. Controller: DestructionController.
 */
class DestructionCrud extends SearchCrud
{
    // --- Daftar, simpan, setujui, dan hapus ---

    public function listDestructions(Request $request): JsonResponse
    {
        return DataTables::make(
            $request,
            Destruction::query()
                ->select([
                    'id', 'description', 'agreement', 'final_project_id', 'user_id',
                    'created_at', 'updated_at',
                ])
                ->with(array_merge(FinalProject::eagerCard(), [
                    'user:id,name',
                ])),
            function (Destruction $destruction, int $number) {
                $project = $destruction->finalProject;
                [$nim, $studentName, , $programStudy, $degreeLevel, $year, $submissionDate]
                    = $this->mapFinalProjectDisplayFields($project);

                return [
                    $number,
                    $destruction->id,
                    $nim,
                    $studentName,
                    $project?->final_project_title ?? '',
                    $programStudy,
                    $degreeLevel,
                    $year,
                    $this->dateLabel($submissionDate),
                    $this->validityLabel($year) ?: 'Tidak Berlaku',
                    $this->dash($destruction->description),
                    $destruction->agreement,
                    $this->dash($destruction->user?->name),
                    $this->dateLabel($destruction->created_at),
                    $this->dateLabel($destruction->updated_at),
                    $destruction->final_project_id,
                    $destruction->user_id,
                ];
            },
            $this->finalProjectOrderMap('destructions') + [
                'agreement' => 'agreement',
                'description' => 'description',
            ],
            [],
            fn ($query, string $keyword) => $this->searchOwnAndFinalProject(
                $query,
                $keyword,
                ['description', 'agreement']
            )
        );
    }

    public function saveDestruction($request): JsonResponse
    {
        $authUser = $request->user();

        if (! $authUser?->isOfficer()) {
            return response()->json('Hanya Petugas yang dapat menambah atau mengubah data pemusnahan.', 403);
        }

        $currentYear = $this->currentDate()->year;

        $request->validate([
            'destructionid' => ['nullable', 'integer', 'exists:destructions,id'],
            'description' => ['required', 'string'],
            'final_project_id' => [
                'required',
                'integer',
                Rule::exists('final__projects', 'id')->where(function ($query) use ($currentYear) {
                    [$minYear] = FinalProject::validityYearBounds($currentYear);
                    $query->whereNotNull('final_project_year')
                        ->where('final_project_year', '<', $minYear);
                }),
            ],
        ], [
            'final_project_id.exists' => 'Pemusnahan hanya untuk tugas akhir yang sudah melewati masa berlaku 10 tahun (status Tidak Berlaku).',
        ]);

        // Petugas hanya mencatat; status selalu Belum Disetujui (tanpa penyetuju).
        $this->updateOrCreateDestruction(
            $request->destructionid,
            $request->description,
            'Belum Disetujui',
            $request->final_project_id,
            null
        );

        return response()->json('Data Pemusnahan Berhasil Disimpan');
    }

    /**
     * Toggle persetujuan pemusnahan (checkbox datatable) — hanya Koordinator.
     */
    public function approveDestructions($request): JsonResponse
    {
        $authUser = $request->user();

        if (! $authUser?->isCoordinator()) {
            return response()->json('Hanya Koordinator yang dapat menyetujui pemusnahan.', 403);
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:destructions,id'],
            'approved' => ['required', 'boolean'],
        ]);

        $approved = (bool) $validated['approved'];
        $agreement = $approved ? 'Disetujui' : 'Belum Disetujui';
        $userId = $approved ? $authUser->id : null;

        Destruction::query()
            ->whereIn('id', $validated['ids'])
            ->update([
                'agreement' => $agreement,
                'user_id' => $userId,
            ]);

        DataTables::touch();

        return response()->json(
            $approved
                ? 'Pemusnahan berhasil disetujui'
                : 'Persetujuan pemusnahan dibatalkan'
        );
    }

    public function destroyDestruction($id): JsonResponse
    {
        if (! $this->deleteDestruction((int) $id)) {
            return response()->json('Hapus Gagal', 404);
        }

        return response()->json('Hapus Pemusnahan Berhasil');
    }
}
