<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
        RateLimiter::for('api-login', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('api-public', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));
        RateLimiter::for('api-auth', fn (Request $r) => Limit::perMinute(60)->by($r->user()->id));
        RateLimiter::for('puzzle-uploads', fn (Request $r) => Limit::perMinute(10)->by($r->user()->id));
        RateLimiter::for('token-create', fn (Request $r) => Limit::perMinute(5)->by($r->user()->id));
    }
}
