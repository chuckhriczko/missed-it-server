<?php

declare(strict_types=1);

namespace Logalyzer\Integrations\Laravel;

use Illuminate\Support\ServiceProvider;
use Logalyzer\LogalyzerClient;
use Logalyzer\LogalyzerHandler;

class LogalyzerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/logalyzer.php', 'logalyzer');

        $this->app->singleton(LogalyzerClient::class, function ($app) {
            $config = $app['config']['logalyzer'];

            return new LogalyzerClient(
                baseUrl:  $config['url'],
                apiToken: $config['token'],
            );
        });

        $this->app->singleton(LogalyzerHandlerRegistry::class);

        $this->app->singleton(LogalyzerHandler::class, function ($app) {
            $config = $app['config']['logalyzer'];

            return new LogalyzerHandler(
                url:         $config['url'],
                token:       $config['token'],
                logName:     $config['log_name'] ?? $app['config']['app.name'] ?? 'Laravel App',
                environment: $config['environment'] ?? $app['config']['app.env'],
                logType:     $config['log_type'] ?? 'laravel',
                level:       $config['level'] ?? \Monolog\Level::Debug,
                client:      $app->make(LogalyzerClient::class),
                projectId:   isset($config['project_id']) ? (int) $config['project_id'] : null,
            );
        });
    }

    public function boot(): void
    {
        // Flush buffered entries after each request/job so Octane workers and
        // long-running queue workers don't hold entries until process shutdown.
        // resolved() rather than bound(): the singleton is always bound, and
        // make()ing it here would build a handler for an app that never logged.
        $this->app->terminating(function () {
            if ($this->app->resolved(LogalyzerHandler::class)) {
                $this->app->make(LogalyzerHandler::class)->flush();
            }

            // Channels with their own log_name/level get their own handler and
            // their own buffer, so flushing the shared one is not enough.
            if ($this->app->resolved(LogalyzerHandlerRegistry::class)) {
                $this->app->make(LogalyzerHandlerRegistry::class)->flushAll();
            }
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/logalyzer.php' => $this->app->configPath('logalyzer.php'),
            ], 'logalyzer-config');
        }
    }
}
