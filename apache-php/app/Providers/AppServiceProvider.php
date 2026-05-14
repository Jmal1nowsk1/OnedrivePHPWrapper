<?php

namespace App\Providers;

use App\Services\OneDriveService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OneDriveService::class);
    }
    // ...existing code...

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
