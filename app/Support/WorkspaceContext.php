<?php

namespace App\Support;

use App\Models\Workspace;

/**
 * Holds the "current workspace" for the active request/job.
 *
 * This is the single source of truth the global BelongsToWorkspace scope reads
 * to constrain every tenant-scoped query. It is bound as a singleton in the
 * container so it lives for exactly one request or queued-job lifecycle.
 *
 * Tenant isolation is enforced here + in the scope, never by ad-hoc where()
 * clauses scattered through the app.
 */
class WorkspaceContext
{
    protected ?int $workspaceId = null;

    protected ?Workspace $workspace = null;

    /**
     * Set the active workspace (by model or id).
     */
    public function set(Workspace|int|null $workspace): void
    {
        if ($workspace instanceof Workspace) {
            $this->workspace = $workspace;
            $this->workspaceId = $workspace->id;

            return;
        }

        $this->workspace = null;
        $this->workspaceId = $workspace;
    }

    /**
     * The active workspace id, or null when unresolved (e.g. guest, console).
     */
    public function id(): ?int
    {
        return $this->workspaceId;
    }

    public function has(): bool
    {
        return $this->workspaceId !== null;
    }

    /**
     * Resolve the full model lazily (only when actually needed).
     */
    public function get(): ?Workspace
    {
        if ($this->workspace === null && $this->workspaceId !== null) {
            $this->workspace = Workspace::withoutGlobalScopes()->find($this->workspaceId);
        }

        return $this->workspace;
    }

    public function clear(): void
    {
        $this->workspaceId = null;
        $this->workspace = null;
    }

    /**
     * Run a callback with a temporarily-overridden workspace, then restore.
     *
     * Used by queued calculation jobs, which must run inside the tenant that
     * owns the run regardless of who is "logged in".
     */
    public function runAs(Workspace|int|null $workspace, callable $callback): mixed
    {
        $previousId = $this->workspaceId;
        $previousModel = $this->workspace;

        $this->set($workspace);

        try {
            return $callback();
        } finally {
            $this->workspace = $previousModel;
            $this->workspaceId = $previousId;
        }
    }
}
