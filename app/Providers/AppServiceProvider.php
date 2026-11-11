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
        RateLimiter::for('puzzle-uploads', fn (Request $r) => [Limit::perMinute(10)->by('user:'.$r->user()->id), Limit::perHour(30)->by('ip:'.$r->ip())]);
        // Name-only players from the game: a few per connection, so one person can't flood the Garden with accounts.
        RateLimiter::for('player-create', fn (Request $r) => [Limit::perMinute(3)->by('m:'.$r->ip()), Limit::perDay(10)->by('d:'.$r->ip())]);
        RateLimiter::for('token-create', fn (Request $r) => Limit::perMinute(5)->by($r->user()->id));
    }
}
