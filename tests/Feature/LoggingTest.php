<?php

namespace Tests\Feature;

use App\Logging\LogalyzerDriver;
use Illuminate\Support\Facades\Http;
use Logalyzer\Integrations\Laravel\LogalyzerHandlerRegistry;
use Logalyzer\LogalyzerClient;
use Mockery;
use Monolog\Handler\NullHandler;
use Tests\TestCase;

class LoggingTest extends TestCase
{
    public function test_unconfigured_logalyzer_does_not_attempt_delivery(): void
    {
        config(['logalyzer.url' => '', 'logalyzer.token' => '']);
        $logger = (new LogalyzerDriver)([]);
        $this->assertInstanceOf(NullHandler::class, $logger->getHandlers()[0]);
        $logger->error('A local error');
    }

    public function test_logalyzer_filters_severity_and_flushes_to_the_separate_log_group(): void
    {
        config(['logalyzer.url' => 'https://logs.example.test', 'logalyzer.token' => 'test-token']);
        $client = Mockery::mock(LogalyzerClient::class);
        $client->shouldReceive('sendEntries')->once()->withArgs(function ($name, $entries, $meta) {
            $this->assertSame('Puzzle Garden — Server', $name);
            $this->assertCount(1, $entries);
            $this->assertSame('warning', $entries[0]['severity']);
            $this->assertSame(['action' => 'backup'], $entries[0]['context']);
            $this->assertSame('laravel', $meta['log_type']);

            return true;
        })->andReturn([]);
        $this->app->instance(LogalyzerClient::class, $client);
        $logger = (new LogalyzerDriver)(['level' => 'warning']);
        $logger->info('Ignored');
        $logger->warning('A warning', ['action' => 'backup']);
        $this->app->make(LogalyzerHandlerRegistry::class)->flushAll();
    }

    public function test_failed_remote_delivery_preserves_the_local_daily_log(): void
    {
        config(['services.logalyzer.url' => 'https://logs.example.test', 'services.logalyzer.token' => 'test-token']);
        Http::fake(['logs.example.test/*' => Http::response('Unavailable', 503)]);
        $path = sys_get_temp_dir().'/garden-log-test-'.bin2hex(random_bytes(6)).'.log';
        config(['logging.channels.daily.path' => $path]);
        try {
            $logger = $this->app->make('log')->stack(['daily', 'logalyzer']);
            $logger->error('Preserved during remote outage');
            $this->app->make('log')->channel('logalyzer')->getLogger()->getHandlers()[0]->flush();
            Http::assertSentCount(1);
            $logs = glob(substr($path, 0, -4).'-*.log');
            $this->assertCount(1, $logs);
            $this->assertStringContainsString('Preserved during remote outage', file_get_contents($logs[0]));
        } finally {
            foreach (glob(substr($path, 0, -4).'-*.log') as $file) {
                unlink($file);
            }
        }
    }
}
