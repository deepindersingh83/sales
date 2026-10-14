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
        // BI tools (Power BI, Excel OData feeds) can only send Basic auth, so
        // the token is also accepted as the Basic-auth password.
        $token = $request->bearerToken() ?: $request->header('X-Api-Token') ?: $request->getPassword();

        if (! $token) {
            return $this->unauthorized('Missing API token.');
        }

        $workspace = Workspace::findByApiToken($token);

        if (! $workspace) {
            return $this->unauthorized('Invalid API token.');
        }

        $this->context->set($workspace);
        $request->attributes->set('workspace', $workspace);

        return $next($request);
    }

    protected function unauthorized(string $message): Response
    {
        return response()->json(['message' => $message], 401, ['WWW-Authenticate' => 'Basic realm="API", charset="UTF-8"']);
    }
}
