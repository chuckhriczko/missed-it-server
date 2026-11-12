<?php

namespace App\Services;

use FilesystemIterator;
use RuntimeException;
use SQLite3;

class SqliteSnapshot
{
    public function create(string $source, string $destination): void
    {
        if (! is_file($source) || is_link($destination) || file_exists($destination) || file_exists($destination.'.partial')) {
            throw new RuntimeException('Backup source must exist and destination must be new.');
        }

        $partial = $destination.'.partial';
        // Reserve the file privately before SQLite opens it, regardless of process umask.
        $previousMask = umask(0077);
        try {
            $file = fopen($partial, 'x');
            if ($file === false) {
                throw new RuntimeException('Could not reserve the backup file.');
            }
            fclose($file);
        } finally {
            umask($previousMask);
        }

        $input = new SQLite3($source, SQLITE3_OPEN_READONLY);
        $input->enableExceptions(true);
        $input->busyTimeout(10000);
        $output = new SQLite3($partial, SQLITE3_OPEN_READWRITE);
        $output->enableExceptions(true);
        $output->busyTimeout(10000);
        try {
            if (! $input->backup($output)) {
                throw new RuntimeException('SQLite online backup failed.');
            }
            $this->check($output);
        } finally {
            $output->close();
            $input->close();
        }
        $this->verify($partial);
        if (! rename($partial, $destination)) {
            throw new RuntimeException('Could not finalize the SQLite backup.');
        }
    }

    public function verify(string $backup): void
    {
        if (! is_file($backup)) {
            throw new RuntimeException('Backup does not exist.');
        }
        // Exercise restoration into an independent, private temporary database.
        $restore = tempnam(sys_get_temp_dir(), 'garden-restore-');
        if ($restore === false) {
            throw new RuntimeException('Could not create a temporary restore database.');
        }
        $source = $destination = null;
        try {
            $source = new SQLite3($backup, SQLITE3_OPEN_READONLY);
            $source->enableExceptions(true);
            $source->busyTimeout(10000);
            $destination = new SQLite3($restore, SQLITE3_OPEN_READWRITE);
            $destination->enableExceptions(true);
            if (! $source->backup($destination)) {
                throw new RuntimeException('SQLite restore check failed.');
            }
            $this->check($destination);
        } finally {
            $destination?->close();
            $source?->close();
            foreach ([$restore, $restore.'-wal', $restore.'-shm', $restore.'-journal'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    private function check(SQLite3 $database): void
    {
        if ($database->querySingle('PRAGMA quick_check') !== 'ok'
            || $database->query('PRAGMA foreign_key_check')->fetchArray() !== false
            || (int) $database->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table'") === 0) {
            throw new RuntimeException('SQLite integrity, foreign key, or schema check failed.');
        }
    }

    public function prune(string $root, int $keepDays): int
    {
        if ($keepDays < 1) {
            throw new RuntimeException('Backup retention must be at least one day.');
        }
        $removed = 0;
        foreach (new FilesystemIterator($root) as $directory) {
            if ($directory->isLink() || ! $directory->isDir()
                || ! preg_match('/^\d{8}T\d{6}Z-[a-zA-Z0-9-]+$/D', $directory->getFilename())) {
                continue;
            }
            $path = $directory->getPathname();
            $files = iterator_to_array(new FilesystemIterator($path));
            $backup = $path.'/database.sqlite';
            // Retain incomplete backups and unrelated contents for investigation.
            if (count($files) !== 1 || is_link($backup) || ! is_file($backup)
                || filemtime($backup) >= time() - $keepDays * 86400) {
                continue;
            }
            if (! unlink($backup) || ! rmdir($path)) {
                throw new RuntimeException('Could not prune an expired backup.');
            }
            $removed++;
        }

        return $removed;
    }
}
