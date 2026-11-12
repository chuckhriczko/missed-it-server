<?php

declare(strict_types=1);

namespace Logalyzer\Integrations\Laravel;

use Illuminate\Support\Facades\Facade;
use Logalyzer\LogalyzerClient;

/**
 * @method static array sendEntries(string $logName, array $entries, array $meta = [])
 *
 * @see \Logalyzer\LogalyzerClient
 */
class LogalyzerFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LogalyzerClient::class;
    }
}
