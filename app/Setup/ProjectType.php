<?php

namespace App\Setup;

use App\Models\FinalProjectType;
use App\Support\CatalogCache;
use Illuminate\Support\Str;

/**
 * Slug URL jenis tugas akhir.
 * Dipanggil dari model FinalProjectType.
 */
class ProjectType
{
    public static function slug(FinalProjectType $type): string
    {
        return Str::slug($type->type);
    }

    /**
     * Cari jenis tugas akhir dari slug URL. Berhenti pada kecocokan pertama.
     */
    public static function findBySlug(string $slug): ?FinalProjectType
    {
        $slug = Str::slug($slug);

        $match = CatalogCache::finalProjectTypes()
            ->first(function ($item) use ($slug): bool {
                return Str::slug((string) ($item->type ?? '')) === $slug;
            });

        return $match instanceof FinalProjectType ? $match : null;
    }
}
