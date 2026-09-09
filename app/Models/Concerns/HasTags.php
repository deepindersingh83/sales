<?php

namespace App\Models\Concerns;

use App\Models\Tag;
use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Adds polymorphic tagging to a workspace-scoped model. Tags are resolved (or
 * created) within the current workspace so tagging never crosses tenants.
 */
trait HasTags
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * Sync this record's tags from a list of tag names, creating any that are
     * missing in the current workspace.
     *
     * @param  array<int, string>  $names
     */
    public function syncTagNames(array $names): void
    {
        $workspaceId = app(WorkspaceContext::class)->id();

        $ids = collect($names)
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->map(fn (string $name) => Tag::firstOrCreate(
                ['workspace_id' => $workspaceId, 'name' => $name],
            )->id);

        $this->tags()->sync($ids->all());
    }
}
