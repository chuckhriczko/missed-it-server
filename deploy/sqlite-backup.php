<?php

// Deployment helper: read fresh configuration without booting application providers.
declare(strict_types=1);

use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Support\ConfigurationUrlParser;

require __DIR__.'/../vendor/autoload.php';

try {
    $argument = $argv[1] ?? '';
    if (count($argv) !== 2 || $argument === '') {
        throw new RuntimeException('Usage: php deploy/sqlite-backup.php --check|DESTINATION');
    }

    // Ignore an old config cache when resolving .env and the database path.
    $uncachedPath = sys_get_temp_dir().'/garden-deploy-config-'.bin2hex(random_bytes(16));
    putenv('APP_CONFIG_CACHE='.$uncachedPath);
    $_ENV['APP_CONFIG_CACHE'] = $_SERVER['APP_CONFIG_CACHE'] = $uncachedPath;
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->bootstrapWith([LoadEnvironmentVariables::class, LoadConfiguration::class]);
    $config = $app->make('config');
    if (! $config->get('app.key')) {
        throw new RuntimeException('APP_KEY is missing; preserve the existing production key.');
    }
    if ($config->get('app.maintenance.driver') !== 'file') {
        throw new RuntimeException('This deployment script requires file-based maintenance mode.');
    }
    $connection = $config->get('database.connections.'.$config->get('database.default'));
    $connection = (new ConfigurationUrlParser)->parseConfiguration($connection ?? []);
    if (($connection['driver'] ?? '') !== 'sqlite' || ! extension_loaded('sqlite3')) {
        throw new RuntimeException('This deployment script requires SQLite and the PHP sqlite3 extension.');
    }
    $database = $connection['database'] ?? '';
    if (! is_file($database) || ! is_readable($database) || ! is_writable($database) || ! is_writable(dirname($database))) {
        throw new RuntimeException('Configured SQLite database must exist and be readable/writable, with a writable parent directory.');
    }
    $url = rtrim((string) $config->get('app.url'), '/');
    $parts = parse_url($url);
    if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
        throw new RuntimeException('APP_URL must be a valid HTTP(S) URL without credentials, query, or fragment.');
    }
    if ($argument === '--check') {
        echo $url.'/up'.PHP_EOL;
        exit(0);
    }
    (new App\Services\SqliteSnapshot)->create($database, $argument);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Deployment preflight/backup failed: '.$exception->getMessage().PHP_EOL);
    exit(1);
}
