<?php

namespace App\Console\Commands;

use App\Services\SqliteSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'garden:backup {--keep-days= : Override configured retention}';

    protected $description = 'Create and restore-check a private SQLite snapshot, then prune expired snapshots';

    public function handle(SqliteSnapshot $snapshots): int
    {
        $lock = fopen(storage_path('framework/deploy.lock'), 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->warn('A deployment or backup is running; backup skipped.');

            return self::FAILURE;
        }
        try {
            $keepDays = filter_var($this->option('keep-days') ?? config('operations.backup_keep_days'), FILTER_VALIDATE_INT);
            if ($keepDays === false || $keepDays < 1) {
                throw new RuntimeException('Backup retention must be at least one day.');
            }
            $connection = DB::connection();
            if ($connection->getDriverName() !== 'sqlite') {
                throw new RuntimeException('This backup command supports SQLite only.');
            }
            $source = $connection->getDatabaseName();
            $source = realpath($source) ?: realpath(base_path($source));
            if (! $source) {
                throw new RuntimeException('Backups require an existing on-disk SQLite database.');
            }
            $root = storage_path('app/private/deploy-backups');
            $directory = $root.'/'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(6));
            if (! mkdir($directory, 0700, true)) {
                throw new RuntimeException('Could not create the private backup directory.');
            }
            $snapshots->create($source, $directory.'/database.sqlite');
            $removed = $snapshots->prune($root, $keepDays);
            $this->info('Verified backup: '.$directory.'/database.sqlite');
            $this->info("Expired backups removed: {$removed}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Scheduled SQLite backup failed.', ['exception' => $exception]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
