<?php

declare(strict_types=1);

namespace Logalyzer\Integrations\Laravel;

use Logalyzer\LogalyzerHandler;

/**
 * Keeps the per-channel handlers built by LogalyzerLogDriver so the service
 * provider can flush every one of them at request/job termination.
 *
 * Each handler owns its own buffer, so a channel that is never flushed holds
 * its entries until process shutdown — long enough to lose them under Octane
 * or a long-running queue worker.
 */
class LogalyzerHandlerRegistry
{
    /** @var array<string, LogalyzerHandler> */
    private array $handlers = [];

    /**
     * Return the handler for this key, building it once via $factory.
     *
     * @param callable():LogalyzerHandler $factory
     */
    public function remember(string $key, callable $factory): LogalyzerHandler
    {
        return $this->handlers[$key] ??= $factory();
    }

    public function flushAll(): void
    {
        foreach ($this->handlers as $handler) {
            $handler->flush();
        }
    }
}
