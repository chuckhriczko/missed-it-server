<?php

namespace App\Logging;

use GuzzleHttp\Client;
use Logalyzer\LogalyzerClient;

class LogalyzerClientFactory
{
    public function __invoke(): LogalyzerClient
    {
        $config = config('logalyzer');
        $timeout = max(0.1, min(5.0, (float) $config['timeout']));
        $client = new LogalyzerClient($config['url'], $config['token'], new Client([
            'base_uri' => rtrim($config['url'], '/'),
            'connect_timeout' => $timeout,
            'timeout' => $timeout,
            'allow_redirects' => false,
            'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$config['token']],
        ]));
        $client->timeout = $timeout;
        $client->retries = 1;

        return $client;
    }
}
