<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as Sent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;
class LogalyzerTest extends TestCase
{
    use RefreshDatabase;
    private function configure(): void
    {
        config(['services.logalyzer.url' => 'https://logalyzer.test', 'services.logalyzer.token' => 'tok_test', 'services.logalyzer.environment' => 'production']);
        Http::fake(['logalyzer.test/*' => Http::response(['status' => 'accepted'], 202)]);
    }
    private function report(array $overrides = []): array
    {
        return array_replace_recursive([
            'environment' => 'production',
            'context' => ['version' => '0.151.0', 'platform' => 'Android', 'os_version' => '15', 'device' => 'Pixel 7', 'engine' => '4.7.2', 'screen' => '1080x2400', 'scene' => 'Room 01.05', 'ip' => '203.0.113.9'],
            'entries' => [['severity' => 'error', 'message' => 'NullReferenceException: Board.Shoot', 'source_file' => 'Board.cs', 'line_number' => 12, 'count' => 3, 'trace' => 'at Board.Shoot()']],
        ], $overrides);
    }
    public function test_game_reports_are_forwarded_with_the_server_token(): void
    {
        $this->configure();
        $this->withoutDefer();
        $this->postJson('/api/v1/reports', $this->report())->assertAccepted()->assertJson(['accepted' => 1, 'forwarded' => true]);
        Http::assertSent(function (Sent $r) {
            $e = $r['entries'][0];
            return $r->url() === 'https://logalyzer.test/api/ingest/entries' && $r->hasHeader('Authorization', 'Bearer tok_test')
                && $r['log_name'] === 'Missed It · Game' && $r['environment'] === 'production' && $r['log_type'] === 'godot'
                && $e['severity'] === 'error' && $e['section'] === 'Game' && $e['source_file'] === 'Board.cs' && $e['line_number'] === 12
                && $e['context']['device'] === 'Pixel 7' && $e['context']['count'] === 3 && $e['context']['trace'] === 'at Board.Shoot()'
                && !array_key_exists('ip', $e['context']) && isset($e['occurred_at']);
        });
    }
    public function test_reports_are_accepted_but_not_sent_without_logalyzer(): void
    {
        Http::fake();
        $this->withoutDefer();
        $this->postJson('/api/v1/reports', $this->report())->assertAccepted()->assertJson(['forwarded' => false]);
        Http::assertNothingSent();
    }
    public function test_reports_only_take_warnings_and_errors_in_small_batches(): void
    {
        $this->configure();
        $this->postJson('/api/v1/reports', $this->report(['entries' => [['severity' => 'info', 'message' => 'Hello']]]))->assertUnprocessable();
        $many = array_fill(0, 21, ['severity' => 'error', 'message' => 'Boom']);
        $this->postJson('/api/v1/reports', ['environment' => 'production', 'entries' => $many])->assertUnprocessable();
        $this->postJson('/api/v1/reports', $this->report(['environment' => 'staging']))->assertUnprocessable();
        Http::assertNothingSent();
    }
    public function test_reports_are_rate_limited(): void
    {
        $this->configure();
        for ($i = 0; $i < 10; $i++) $this->postJson('/api/v1/reports', $this->report())->assertAccepted();
        $this->postJson('/api/v1/reports', $this->report())->assertTooManyRequests();
    }
    public function test_server_errors_reach_logalyzer_in_one_batch(): void
    {
        $this->configure();
        $channel = Log::channel('logalyzer');
        $channel->info('Puzzle shared', ['title' => 'Around the bumper']);
        $channel->error('Upload failed', ['exception' => new RuntimeException('Disk full')]);
        Http::assertNothingSent();
        $channel->getLogger()->getHandlers()[0]->flush();
        Http::assertSentCount(1);
        Http::assertSent(function (Sent $r) {
            [$info, $error] = $r['entries'];
            return $r['log_name'] === 'Missed It · Puzzle Garden server' && $r['log_type'] === 'laravel'
                && $info['severity'] === 'info' && $info['context']['title'] === 'Around the bumper'
                && $error['severity'] === 'error' && $error['context']['exception'] === RuntimeException::class
                && str_ends_with($error['source_file'], 'LogalyzerTest.php') && $error['line_number'] > 1 && $error['section'] === 'Server';
        });
    }
    public function test_logalyzer_failures_never_break_the_app(): void
    {
        config(['services.logalyzer.url' => 'https://logalyzer.test', 'services.logalyzer.token' => 'tok_test']);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));
        $this->withoutDefer();
        $this->postJson('/api/v1/reports', $this->report())->assertAccepted();
        $channel = Log::channel('logalyzer');
        $channel->error('Still fine');
        $channel->getLogger()->getHandlers()[0]->flush();
        $this->assertTrue(true);
    }
    public function test_sharing_events_are_logged(): void
    {
        Log::spy();
        $token = $this->postJson('/api/v1/players', ['name' => 'Fern'])->json('token');
        $raw = file_get_contents(base_path('tests/Fixtures/pull.json'));
        $this->postJson('/api/v1/puzzles', ['puzzle_json' => $raw, 'difficulty' => 'gentle'], ['Authorization' => 'Bearer '.$token])->assertCreated();
        $this->postJson('/api/v1/puzzles', ['puzzle_json' => '{"Version":1}', 'difficulty' => 'gentle'], ['Authorization' => 'Bearer '.$token])->assertUnprocessable();
        Log::shouldHaveReceived('info')->with('Player created', ['player' => 'Fern']);
        Log::shouldHaveReceived('info')->with('Puzzle shared', Mockery::on(fn ($c) => $c['creator'] === 'Fern' && $c['difficulty'] === 'gentle'));
        Log::shouldHaveReceived('notice')->with('Puzzle refused', Mockery::on(fn ($c) => $c['creator'] === 'Fern' && filled($c['reason'])));
    }
    public function test_rate_limit_hits_are_logged_without_the_address(): void
    {
        Log::spy();
        for ($i = 0; $i < 3; $i++) $this->postJson('/api/v1/players', ['name' => "P$i"])->assertCreated();
        $this->postJson('/api/v1/players', ['name' => 'P3'])->assertTooManyRequests()->assertHeader('Retry-After');
        Log::shouldHaveReceived('notice')->with('New players rate-limited', ['route' => 'POST api/v1/players']);
    }
}
