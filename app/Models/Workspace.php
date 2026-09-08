<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * The top-level tenant. Every business record belongs to exactly one Workspace.
 * The Workspace itself is NOT tenant-scoped (it is the tenant boundary).
 */
class Workspace extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'base_currency',
    ];

    protected static function booted(): void
    {
        static::creating(function (Workspace $workspace) {
            if (empty($workspace->slug)) {
                $workspace->slug = static::uniqueSlug($workspace->name);
            }
        });
    }

    protected static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $i = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    /**
     * Members of the workspace, with their per-workspace role on the pivot.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot(['role'])
            ->withTimestamps();
    }
}
