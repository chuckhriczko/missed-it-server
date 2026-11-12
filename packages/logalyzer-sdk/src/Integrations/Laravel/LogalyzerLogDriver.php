<?php

declare(strict_types=1);

namespace Logalyzer\Integrations\Laravel;

use Illuminate\Contracts\Container\Container;
use Logalyzer\LogalyzerClient;
use Logalyzer\LogalyzerHandler;
use Monolog\Logger as MonologLogger;

/**
 * Custom Laravel log driver factory.
 *
 * Register in config/logging.php:
 *
 *   'channels' => [
 *       'logalyzer' => [
 *           'driver' => 'custom',
 *           'via'    => \Logalyzer\Integrations\Laravel\LogalyzerLogDriver::class,
 *       ],
 *   ],
 *
 * Laravel does not apply a channel's `level` to a custom driver — it hands the
 * whole channel config to this factory and uses whatever logger comes back. So
 * `level`, `log_name` and `name` are read here rather than being silently
 * ignored, which lets one app stream several channels to different Logalyzer
 * log groups at different severities:
 *
 *   'feedback' => [
 *       'driver'   => 'custom',
 *       'via'      => \Logalyzer\Integrations\Laravel\LogalyzerLogDriver::class,
 *       'name'     => 'feedback',
 *       'log_name' => 'Chain Reaction — Feedback',
 *       'level'    => 'info',
 *   ],
 */
class LogalyzerLogDriver
{
    public function __construct(
        private readonly Container $container,
        private readonly LogalyzerHandlerRegistry $registry,
    ) {}

    /** @param array<string, mixed> $config */
    public function __invoke(array $config): MonologLogger
    {
        return new MonologLogger(
            (string) ($config['name'] ?? 'logalyzer'),
            [$this->resolveHandler($config)],
        );
    }

    /** @param array<string, mixed> $config */
    private function resolveHandler(array $config): LogalyzerHandler
    {
        $logName = $config['log_name'] ?? null;
        $level   = $config['level'] ?? null;

        // With no per-channel override, share the app-wide handler so every
        // channel batches into one buffer and one ingest request.
        if ($logName === null && $level === null) {
            return $this->container->make(LogalyzerHandler::class);
        }

        $base = $this->container['config']['logalyzer'];

        return $this->registry->remember(
            $logName . '@' . (is_string($level) ? $level : var_export($level, true)),
            fn () => new LogalyzerHandler(
                url:         $base['url'],
                token:       $base['token'],
                logName:     $logName ?? $base['log_name'] ?? 'Laravel App',
                environment: $base['environment'] ?? null,
                logType:     $base['log_type'] ?? 'laravel',
                level:       $level ?? $base['level'] ?? \Monolog\Level::Debug,
                client:      $this->container->make(LogalyzerClient::class),
                projectId:   isset($base['project_id']) ? (int) $base['project_id'] : null,
            ),
        );
    }
}
