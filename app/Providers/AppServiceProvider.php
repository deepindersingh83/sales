<?php

namespace App\Providers;

use App\Support\WorkspaceContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One workspace context per request/job lifecycle.
        $this->app->singleton(WorkspaceContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
