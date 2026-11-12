<?php

declare(strict_types=1);

namespace Logalyzer\Transports;

use Logalyzer\LogalyzerClient;

/**
 * In-process buffer that accumulates log entries and flushes them to
 * Logalyzer either when a size threshold is reached or at shutdown.
 */
class AsyncBufferedTransport
{
    /** @var array<int, array<string, mixed>> */
    private array $buffer = [];

    /** Maximum entries to buffer before the oldest are dropped. */
    public int $flushThreshold = 100;

    /**
     * Optional callback invoked with (entries, throwable) when a flush fails.
     * Must not throw.
     *
     * @var callable(array<int,array<string,mixed>>, \Throwable): void|null
     */
    public $onDropError = null;

    private bool $shutdownRegistered = false;

    public function __construct(
        private readonly LogalyzerClient $client,
        private readonly string          $logName,
        private readonly array           $meta = [],
    ) {
        $this->registerShutdown();
    }

    /** @param array<string, mixed> $entry */
    public function push(array $entry): void
    {
        // When the buffer is full, drop the oldest entry rather than flushing
        // synchronously — a blocking HTTP call mid-request would add latency.
        // The buffer is drained at request termination or process shutdown.
        if (count($this->buffer) >= $this->flushThreshold) {
            array_shift($this->buffer);
        }

        $this->buffer[] = $entry;
    }

    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        $toSend       = $this->buffer;
        $this->buffer = [];

        try {
            $this->client->sendEntries($this->logName, $toSend, $this->meta);
        } catch (\Throwable $e) {
            if ($this->onDropError !== null) {
                try {
                    ($this->onDropError)($toSend, $e);
                } catch (\Throwable) {
                }
            }
        }
    }

    public function getBufferSize(): int
    {
        return count($this->buffer);
    }

    private function registerShutdown(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }

        $self = $this;
        register_shutdown_function(static function () use ($self): void {
            $self->flush();
        });

        $this->shutdownRegistered = true;
    }
}
