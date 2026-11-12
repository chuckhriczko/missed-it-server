<?php
namespace App\Logging;
use App\Services\Logalyzer;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Throwable;
// Buffers this server's log records and sends them to Logalyzer in one batch at the end of the request or command.
class LogalyzerHandler extends AbstractProcessingHandler
{
    private array $buffer = [];
    private bool $sending = false;
    protected function write(LogRecord $record): void
    {
        if ($this->sending) return;
        $context = $record->context;
        $entry = ['severity' => strtolower($record->level->getName()), 'message' => mb_substr($record->message, 0, 10000), 'occurred_at' => $record->datetime->format(DATE_ATOM), 'section' => 'Server'];
        if (($e = $context['exception'] ?? null) instanceof Throwable) {
            unset($context['exception']);
            $entry['source_file'] = mb_substr(str_replace(base_path().DIRECTORY_SEPARATOR, '', $e->getFile()), 0, 500);
            $entry['line_number'] = max(1, $e->getLine());
            $context['exception'] = $e::class;
            $context['trace'] = mb_substr($e->getTraceAsString(), 0, 4000);
        }
        if (app()->runningInConsole()) $context['command'] = implode(' ', array_slice($_SERVER['argv'] ?? [], 1, 3));
        elseif (app()->bound('request')) $context['route'] = request()->method().' '.request()->path();
        $entry['context'] = array_map(fn ($v) => is_scalar($v) || $v === null || is_array($v) ? $v : get_debug_type($v), $context);
        $this->buffer[] = $entry;
        if (count($this->buffer) >= 100) $this->flush();
    }
    public function flush(): void
    {
        if ($this->buffer === [] || $this->sending) return;
        [$entries, $this->buffer] = [$this->buffer, []];
        $this->sending = true;
        try { Logalyzer::send(config('services.logalyzer.server_log'), config('services.logalyzer.environment'), 'laravel', $entries); }
        finally { $this->sending = false; }
    }
    public function close(): void { $this->flush(); parent::close(); }
}
