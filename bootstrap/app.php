<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureWorkspaceAdmin;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Resolve the current workspace on every web request, after auth.
        $middleware->web(append: [
            ResolveWorkspace::class,
        ]);

        // ...and before route-model binding, which queries tenant-scoped
        // models: with no workspace resolved yet the scope matches nothing
        // and every {plan}/{calcRun}/{dispute} URL would 404.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveWorkspace::class,
        );

        // Role-based route guard, used as e.g. ->middleware('role:full_admin,plan_admin').
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'workspace.admin' => EnsureWorkspaceAdmin::class,
            'auth.api' => AuthenticateApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
