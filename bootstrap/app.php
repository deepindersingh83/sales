<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Resolve the current workspace on every web request, after auth.
        $middleware->web(append: [
            \App\Http\Middleware\ResolveWorkspace::class,
        ]);

        // Role-based route guard, used as e.g. ->middleware('role:full_admin,plan_admin').
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'workspace.admin' => \App\Http\Middleware\EnsureWorkspaceAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
