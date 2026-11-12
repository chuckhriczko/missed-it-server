<?php

namespace Tests\Unit;

use App\Services\SqliteSnapshot;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SQLite3;

class SqliteSnapshotTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/garden-snapshot-test-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function test_backup_restores_committed_wal_data_and_is_private(): void
    {
        $source = new SQLite3($this->directory.'/source.sqlite');
        $source->exec('PRAGMA journal_mode=WAL');
        $source->exec('CREATE TABLE evidence (value TEXT)');
        $source->exec("INSERT INTO evidence VALUES ('committed WAL data')");
        $backup = $this->directory.'/backup.sqlite';
        $snapshots = new SqliteSnapshot;
        $snapshots->create($this->directory.'/source.sqlite', $backup);
        $restored = new SQLite3($backup, SQLITE3_OPEN_READONLY);
        $this->assertSame('committed WAL data', $restored->querySingle('SELECT value FROM evidence'));
        $this->assertSame(0600, fileperms($backup) & 0777);
        $snapshots->verify($backup);
        $restored->close();
        $source->close();
        $this->expectException(RuntimeException::class);
        $snapshots->create($backup, $backup);
    }

    public function test_pruning_keeps_recent_incomplete_and_unrelated_files(): void
    {
        foreach (['20260101T000000Z-old', '20260102T000000Z-partial', '20260103T000000Z-recent', 'unrelated'] as $folder) {
            mkdir($this->directory.'/'.$folder);
            $file = $this->directory.'/'.$folder.'/database.sqlite';
            file_put_contents($file, 'fixture');
            touch($file, time() - 60 * 86400);
        }
        file_put_contents($this->directory.'/20260102T000000Z-partial/database.sqlite.partial', 'incomplete');
        touch($this->directory.'/20260103T000000Z-recent/database.sqlite');
        $this->assertSame(1, (new SqliteSnapshot)->prune($this->directory, 30));
        $this->assertDirectoryDoesNotExist($this->directory.'/20260101T000000Z-old');
        $this->assertDirectoryExists($this->directory.'/20260102T000000Z-partial');
        $this->assertDirectoryExists($this->directory.'/20260103T000000Z-recent');
        $this->assertDirectoryExists($this->directory.'/unrelated');
    }

    public function test_an_empty_database_is_not_a_verified_backup(): void
    {
        $source = new SQLite3($this->directory.'/empty.sqlite');
        $source->close();
        $this->expectException(RuntimeException::class);
        (new SqliteSnapshot)->create($this->directory.'/empty.sqlite', $this->directory.'/backup.sqlite');
    }
}
