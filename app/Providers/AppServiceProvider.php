<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(config('registration.max_attempts'))
            ->by($request->ip()));

        RateLimiter::for('resource-creation', fn (Request $request) => Limit::perMinute(config('registration.resource_creation_max_attempts'))
            ->by($request->user()?->id ?? $request->ip()));

        Vite::prefetch(concurrency: 3);
    }
}
