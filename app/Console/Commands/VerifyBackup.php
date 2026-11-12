<?php

namespace App\Console\Commands;

use App\Services\SqliteSnapshot;
use Illuminate\Console\Command;
use Throwable;

class VerifyBackup extends Command
{
    protected $signature = 'garden:backup-verify {path : Path to a SQLite backup}';

    protected $description = 'Restore a backup to a temporary database and verify its integrity';

    public function handle(SqliteSnapshot $snapshots): int
    {
        try {
            $snapshots->verify($this->argument('path'));
            $this->info('Temporary restore and SQLite integrity checks passed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
