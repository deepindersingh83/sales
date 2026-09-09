<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates API requests by a per-workspace bearer token and pins the
 * WorkspaceContext to that workspace, so the global tenant scope applies to API
 * reads and writes exactly as it does for web requests.
 */
class AuthenticateApiToken
{
    public function __construct(protected WorkspaceContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: $request->header('X-Api-Token');

        if (! $token) {
            return response()->json(['message' => 'Missing API token.'], 401);
        }

        $workspace = Workspace::where('api_token', $token)->first();

        if (! $workspace) {
            return response()->json(['message' => 'Invalid API token.'], 401);
        }

        $this->context->set($workspace);
        $request->attributes->set('workspace', $workspace);

        return $next($request);
    }
}
