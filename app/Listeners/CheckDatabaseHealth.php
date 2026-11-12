<?php

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class CheckDatabaseHealth
{
    public function handle(DiagnosingHealth $event): void
    {
        try {
            // Verify application tables too: SELECT 1 would pass on an empty database.
            DB::table('puzzles')->select('id')->limit(1)->get();
            DB::table('users')->select('id')->limit(1)->get();
            DB::table('personal_access_tokens')->select('id')->limit(1)->get();
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->select('id')->limit(1)->get();
            }
            if (config('cache.default') === 'database') {
                DB::connection(config('cache.stores.database.connection'))->table(config('cache.stores.database.table'))->select('key')->limit(1)->get();
            }
        } catch (Throwable) {
            // /up renders the exception message in HTML; never expose SQL or paths.
            throw new RuntimeException('Application database is unavailable.');
        }
    }
}
