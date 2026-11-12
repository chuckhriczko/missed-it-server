<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\Logalyzer\LogalyzerClient::class, fn () => (new \App\Logging\LogalyzerClientFactory)());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api-login', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('api-public', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));
        RateLimiter::for('api-auth', fn (Request $r) => Limit::perMinute(60)->by($r->user()->id));
        RateLimiter::for('puzzle-uploads', fn (Request $r) => self::noted([Limit::perMinute(10)->by('user:'.$r->user()->id), Limit::perHour(30)->by('ip:'.$r->ip())], 'Puzzle uploads rate-limited'));
        // Name-only players from the game: a few per connection, so one person can't flood the Garden with accounts.
        RateLimiter::for('player-create', fn (Request $r) => self::noted([Limit::perMinute(3)->by('m:'.$r->ip()), Limit::perDay(10)->by('d:'.$r->ip())], 'New players rate-limited'));
        // Game error reports, forwarded to Logalyzer (/api/v1/reports).
        RateLimiter::for('game-reports', fn (Request $r) => self::noted([Limit::perMinute(10)->by('m:'.$r->ip()), Limit::perDay(200)->by('d:'.$r->ip())], 'Game error reports rate-limited'));
        // A clear counts once per address per puzzle a day, and at most 60 clears an hour per address.
        RateLimiter::for('puzzle-clears', function (Request $r) {
            $puzzle = $r->route('puzzle');
            return self::noted([Limit::perDay(1)->by('c:'.$r->ip().':'.(is_object($puzzle) ? $puzzle->slug : (string) $puzzle)), Limit::perHour(60)->by('h:'.$r->ip())], 'Puzzle clears rate-limited');
        });
        RateLimiter::for('token-create', fn (Request $r) => Limit::perMinute(5)->by($r->user()->id));
    }

    // Hitting a limit is logged as a notice (so it reaches Logalyzer) with the route, not the caller's address.
    private static function noted(array $limits, string $what): array
    {
        return array_map(fn (Limit $limit) => $limit->response(function (Request $request, array $headers) use ($what) {
            Log::notice($what, ['route' => $request->method().' '.$request->path()]);
            return response()->json(['message' => 'Too Many Attempts.'], 429, $headers);
        }), $limits);
    }
}
