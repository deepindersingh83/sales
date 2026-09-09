<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard for the admin area: any workspace role except Participant.
 * Participants are confined to their own personal dashboard.
 */
class EnsureWorkspaceAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $role = $request->user()?->currentRole();

        if ($role === null || ! $role->isAdmin()) {
            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}
