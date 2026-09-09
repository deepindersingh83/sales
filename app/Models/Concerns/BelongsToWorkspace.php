<?php

namespace App\Models\Concerns;

use App\Models\Scopes\WorkspaceScope;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every tenant-scoped model. Does two things:
 *
 *  1. Registers the global WorkspaceScope so reads are constrained to the
 *     current workspace automatically.
 *  2. Stamps workspace_id on creation from the current WorkspaceContext, so
 *     application code never has to remember to set it.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function ($model) {
            if ($model->getAttribute('workspace_id') === null) {
                $context = app(WorkspaceContext::class);

                if ($context->has()) {
                    $model->setAttribute('workspace_id', $context->id());
                }
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Escape hatch: a query builder with the workspace scope removed.
     * Intended only for trusted, cross-tenant admin/console operations.
     */
    public static function acrossAllWorkspaces(): Builder
    {
        return static::query()->withoutGlobalScope(WorkspaceScope::class);
    }
}
