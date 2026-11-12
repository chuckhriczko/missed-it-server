<?php
namespace App\Logging;
use App\Services\Logalyzer;
use Monolog\Handler\NullHandler;
use Monolog\Level;
use Monolog\Logger;
// The "logalyzer" log channel (config/logging.php). Without LOGALYZER_URL and LOGALYZER_TOKEN it discards records.
class CreateLogalyzerLogger
{
    public function __invoke(array $config): Logger
    {
        if (!Logalyzer::configured()) return new Logger('logalyzer', [new NullHandler()]);
        $handler = new LogalyzerHandler(Level::fromName($config['level'] ?? 'info'));
        app()->terminating(fn () => $handler->flush());
        return new Logger('logalyzer', [$handler]);
    }
}
