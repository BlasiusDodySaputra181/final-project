<?php

namespace App\Setup\Crud;

use App\Models\FinalProjectType;
use App\Support\CatalogCache;
use App\Support\DataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD Jenis Tugas Akhir.
 * Anak dari UserCrud. Controller: FinalProjectTypeController.
 */
class FinalProjectTypeCrud extends UserCrud
{
    // --- Baca, simpan, dan hapus ---

    /**
     * Opsi jenis tugas akhir untuk select form lain.
     */
    public function listFinalProjectTypeOptions(): JsonResponse
    {
        return response()->json(
            CatalogCache::finalProjectTypes()
                ->map(static fn (FinalProjectType $type): array => [
                    'id' => $type->id,
                    'level' => $type->level,
                    'type' => $type->type,
                ])
                ->values()
        );
    }

    public function listFinalProjectTypes(Request $request): JsonResponse
    {
        return DataTables::make(
            $request,
            FinalProjectType::query()->select(['id', 'level', 'type', 'created_at', 'updated_at']),
            fn (FinalProjectType $type, int $number) => [
                $number,
                $type->id,
                $type->level,
                $type->type,
                $this->dateLabel($type->created_at),
                $this->dateLabel($type->updated_at),
            ],
            [
                'id' => 'id',
                'level' => 'level',
                'type' => 'type',
            ],
            ['level', 'type']
        );
    }

    public function saveFinalProjectType($request): JsonResponse
    {
        $request->validate([
            'finalprojecttypeid' => ['nullable', 'integer', 'exists:final_project_types,id'],
            'level' => ['required', 'string', 'max:50'],
            'type' => ['required', 'string', 'max:255'],
        ]);

        $this->updateOrCreateFinalProjectType(
            $request->finalprojecttypeid,
            $request->level,
            $request->type
        );

        $this->finalProjectTypeIdByLevel = null;
        $this->finalProjectTypeById = null;

        return response()->json('Data Jenis Tugas Akhir Berhasil Disimpan');
    }

    public function destroyFinalProjectType($id): JsonResponse
    {
        if ($this->deleteFinalProjectType((int) $id) === 0) {
            return response()->json('Hapus Gagal', 404);
        }

        $this->finalProjectTypeIdByLevel = null;
        $this->finalProjectTypeById = null;

        return response()->json('Hapus Jenis Tugas Akhir Berhasil');
    }
}
