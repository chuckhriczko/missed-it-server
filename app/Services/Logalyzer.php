<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use Throwable;
// Sends structured entries to Logalyzer (POST /api/ingest/entries). The token stays on this server:
// the game reports through /api/v1/reports instead of holding a Logalyzer token, which can read and delete logs.
// Sending never throws, so logging can't break a request.
class Logalyzer
{
    public static function configured(): bool { return filled(config('services.logalyzer.url')) && filled(config('services.logalyzer.token')); }
    public static function send(string $logName, string $environment, string $logType, array $entries): bool
    {
        if (!self::configured() || $entries === []) return false;
        try {
            $body = ['log_name' => $logName, 'environment' => $environment, 'log_type' => $logType, 'source_type' => 'sdk', 'entries' => array_values($entries)];
            if (filled(config('services.logalyzer.project_id'))) $body['project_id'] = (int) config('services.logalyzer.project_id');
            return Http::withToken(config('services.logalyzer.token'))->acceptJson()
                ->timeout(config('services.logalyzer.timeout'))->connectTimeout(config('services.logalyzer.timeout'))
                ->withOptions(['allow_redirects' => false])
                ->post(rtrim(config('services.logalyzer.url'), '/').'/api/ingest/entries', $body)->successful();
        } catch (Throwable) {
            return false;
        }
    }
}
