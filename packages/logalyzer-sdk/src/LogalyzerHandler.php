<?php

declare(strict_types=1);

namespace Logalyzer;

use Logalyzer\Transports\AsyncBufferedTransport;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Monolog handler that ships log records to Logalyzer.
 *
 * Laravel usage (config/logging.php):
 *
 *   'logalyzer' => [
 *       'driver'  => 'custom',
 *       'via'     => \Logalyzer\Integrations\Laravel\LogalyzerLogDriver::class,
 *       'level'   => 'debug',
 *   ],
 */
class LogalyzerHandler extends AbstractProcessingHandler
{
    private AsyncBufferedTransport $transport;

    public function __construct(
        string           $url,
        string           $token,
        string           $logName,
        ?string          $environment = null,
        ?string          $logType = null,
        int|string|Level $level = Level::Debug,
        bool             $bubble = true,
        ?LogalyzerClient $client = null,
        ?callable        $onDropError = null,
        ?int             $projectId = null,
    ) {
        parent::__construct($level, $bubble);

        $resolvedClient = $client ?? new LogalyzerClient($url, $token);

        $meta = array_filter([
            'environment' => $environment,
            'log_type'    => $logType ?? 'php',
            'source_type' => 'sdk',
            'project_id'  => $projectId,
        ], fn ($v) => $v !== null);

        $this->transport              = new AsyncBufferedTransport($resolvedClient, $logName, $meta);
        $this->transport->onDropError = $onDropError;
    }

    protected function write(LogRecord $record): void
    {
        $entry = [
            'severity'    => strtolower($record->level->name),
            'message'     => $record->message,
            'occurred_at' => $record->datetime->format(\DateTimeInterface::ATOM),
            'section'     => $record->channel,
            'context'     => $this->buildContext($record),
        ];

        if (isset($record->extra['file'])) {
            $entry['source_file'] = $record->extra['file'];
        }
        if (isset($record->extra['line'])) {
            $entry['line_number'] = (int) $record->extra['line'];
        }

        $this->transport->push($entry);
    }

    public function flush(): void
    {
        $this->transport->flush();
    }

    public function close(): void
    {
        $this->flush();
        parent::close();
    }

    private function buildContext(LogRecord $record): array
    {
        $context = $record->context;

        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $context['exception'] = $this->buildExceptionContext($context['exception']);
        }

        $extras = array_filter(
            $record->extra,
            fn ($k) => !in_array($k, ['file', 'line'], true),
            ARRAY_FILTER_USE_KEY
        );

        return array_merge($extras, $context);
    }

    private function buildExceptionContext(\Throwable $e): array
    {
        $result = [
            'class'   => get_class($e),
            'message' => $e->getMessage(),
            'code'    => $e->getCode(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => array_slice(
                array_map(
                    fn ($f) => ($f['file'] ?? '') . ':' . ($f['line'] ?? '') . ' '
                        . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? ''),
                    $e->getTrace()
                ),
                0,
                20
            ),
        ];

        if ($e->getPrevious() !== null) {
            $result['previous'] = $this->buildExceptionContext($e->getPrevious());
        }

        return $result;
    }
}
