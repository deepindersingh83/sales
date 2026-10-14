<?php

namespace App\Http\Middleware;

use App\Support\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the "current workspace" for the authenticated user and loads it into
 * the WorkspaceContext singleton, which the global BelongsToWorkspace scope
 * reads. Selection is remembered in the session and always re-validated against
 * the user's actual memberships so a stale/forged session id can never point at
 * a workspace the user does not belong to.
 */
class ResolveWorkspace
{
    public function __construct(protected WorkspaceContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $selectedId = $request->session()->get('current_workspace_id');

            // Re-validate membership every request (authorization, not just convenience).
            $membership = $selectedId
                ? $user->workspaces()->where('workspaces.id', $selectedId)->first()
                : null;

            // Fall back to the user's first workspace if none selected / invalid.
            if (! $membership) {
                $membership = $user->workspaces()->orderBy('workspaces.id')->first();
            }

            if ($membership) {
                $this->context->set($membership);
                $request->session()->put('current_workspace_id', $membership->id);
            }
        }

        return $next($request);
    }
}
