<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard: aborts with 403 unless the user's role in the current workspace
 * is one of the given roles. Usage: ->middleware('role:full_admin,plan_admin').
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $current = $request->user()?->currentRole();

        if ($current === null) {
            abort(403, 'No workspace role.');
        }

        $allowed = array_map(fn (string $r) => Role::from($r), $roles);

        if (! in_array($current, $allowed, true)) {
            abort(403, 'Insufficient role.');
        }

        return $next($request);
    }
}
