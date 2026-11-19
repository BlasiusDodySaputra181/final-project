<?php

namespace App\Setup;

use App\Models\FinalProjectType;
use App\Support\CatalogCache;
use Illuminate\Support\Str;

/**
 * URL slugs for final-project types.
 * Called from the FinalProjectType model.
 */
class ProjectType
{
    public static function slug(FinalProjectType $type): string
    {
        return Str::slug($type->type);
    }

    /**
     * Find a final-project type from a URL slug. Stop at the first match.
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
