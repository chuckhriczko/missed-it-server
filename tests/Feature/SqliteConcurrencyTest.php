<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SqliteConcurrencyTest extends TestCase
{
    public function test_readers_continue_and_a_waiting_writer_succeeds_after_commit(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'garden-concurrency-');
        $config = config('database.connections.sqlite');
        $config['database'] = $path;
        $config['url'] = null;
        $writer = $reader = $process = null;
        $pipes = [];
        try {
            $writer = DB::build($config);
            $reader = DB::build($config);
            $writer->statement('CREATE TABLE evidence (id INTEGER PRIMARY KEY, value INTEGER)');
            $writer->statement('INSERT INTO evidence VALUES (1, 0)');
            $this->assertSame('wal', $writer->selectOne('PRAGMA journal_mode')->journal_mode);
            $this->assertSame(5000, $writer->selectOne('PRAGMA busy_timeout')->timeout);
            $writer->beginTransaction();
            $writer->update('UPDATE evidence SET value = 1 WHERE id = 1');
            $code = <<<'PHP'
require $argv[1];
$config = json_decode($argv[2], true);
$connection = (new Illuminate\Database\Connectors\SQLiteConnector)->connect($config);
echo "ready\n";
flush();
$connection->exec('UPDATE evidence SET value = value + 1 WHERE id = 1');
echo "done\n";
PHP;
            $process = proc_open([PHP_BINARY, '-r', $code, base_path('vendor/autoload.php'), json_encode($config)],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $this->assertIsResource($process);
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 10);
            $this->assertSame("ready\n", fgets($pipes[1]));
            usleep(100000);
            $this->assertTrue(proc_get_status($process)['running']);
            $this->assertSame(0, $reader->selectOne('SELECT value FROM evidence')->value);
            $writer->commit();
            $this->assertSame("done\n", fgets($pipes[1]), stream_get_contents($pipes[2]));
            $this->assertSame(0, proc_close($process));
            $process = null;
            $this->assertSame(2, $reader->selectOne('SELECT value FROM evidence')->value);
        } finally {
            if ($writer?->transactionLevel()) {
                $writer->rollBack();
            }
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            $writer?->disconnect();
            $reader?->disconnect();
            foreach ([$path, $path.'-wal', $path.'-shm'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
