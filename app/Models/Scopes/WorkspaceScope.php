<?php

namespace App\Models\Scopes;

use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that constrains every query on a tenant-scoped model to the
 * current workspace. This is the ORM-level enforcement point for multi-tenant
 * isolation required by the spec — nothing should read cross-tenant data
 * without explicitly calling withoutGlobalScope(WorkspaceScope::class).
 */
class WorkspaceScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(WorkspaceContext::class);

        // When no workspace is resolved (guest request, console before context
        // is set), constrain to an impossible id so a tenant model can never
        // leak all rows by accident. Callers that legitimately need global
        // access must opt out with withoutGlobalScope().
        if (! $context->has()) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where(
            $model->qualifyColumn('workspace_id'),
            $context->id()
        );
    }
}
