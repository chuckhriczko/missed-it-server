<?php

namespace App\Logging;

use Logalyzer\Integrations\Laravel\LogalyzerHandlerRegistry;
use Logalyzer\LogalyzerClient;
use Logalyzer\LogalyzerHandler;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

class LogalyzerDriver
{
    public function __invoke(array $channel): Logger
    {
        $config = config('logalyzer');
        if (! $config['url'] || ! $config['token']) {
            return new Logger('garden', [new NullHandler]);
        }

        $handler = app(LogalyzerHandlerRegistry::class)->remember('garden', fn () => new LogalyzerHandler(
            url: $config['url'],
            token: $config['token'],
            logName: $config['log_name'],
            environment: $config['environment'],
            logType: $config['log_type'],
            level: $channel['level'] ?? $config['level'],
            client: app(LogalyzerClient::class),
            projectId: $config['project_id'],
        ));

        // The SDK buffers and flushes at termination; transport failures are contained.
        return new Logger('garden', [$handler]);
    }
}
