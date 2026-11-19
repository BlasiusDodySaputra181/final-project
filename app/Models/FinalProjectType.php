<?php

namespace App\Models;

use App\Setup\ProjectType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinalProjectType extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'level',
        'type',
    ];

    public function finalProjects(): HasMany
    {
        return $this->hasMany(FinalProject::class);
    }

    /**
     * URL slug generated from the type name.
     */
    public function getSlugAttribute(): string
    {
        return ProjectType::slug($this);
    }

    /**
     * Find a final-project type by its URL slug. Stops at the first match.
     */
    public static function findBySlug(string $slug): ?self
    {
        return ProjectType::findBySlug($slug);
    }
}
