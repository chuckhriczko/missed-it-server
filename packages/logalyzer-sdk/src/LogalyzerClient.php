<?php

declare(strict_types=1);

namespace Logalyzer;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;

/**
 * Low-level HTTP client for the Logalyzer batch ingest API.
 */
class LogalyzerClient
{
    private Client $http;

    /** Maximum number of entries per single HTTP request. */
    public int $maxBatchSize = 500;

    /** HTTP connect + read timeout in seconds. */
    public float $timeout = 5.0;

    /** Number of retries on network/5xx failures. */
    public int $retries = 3;

    /** Base delay in seconds between retries (doubles each attempt, with ±20% jitter). */
    public float $retryDelay = 0.5;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiToken,
        ?Client $httpClient = null,
    ) {
        $this->http = $httpClient ?? new Client([
            'base_uri'              => rtrim($baseUrl, '/'),
            RequestOptions::TIMEOUT => $this->timeout,
            RequestOptions::HEADERS => [
                'Accept'        => 'application/json',
                'Authorization' => "Bearer {$this->apiToken}",
            ],
        ]);
    }

    /**
     * Send a batch of structured entries to the Logalyzer ingest endpoint.
     *
     * @param  array<int, array<string, mixed>> $entries
     * @param  array<string, mixed>             $meta
     * @return array{log_id: int, entries_accepted: int, status: string}[]
     */
    public function sendEntries(string $logName, array $entries, array $meta = []): array
    {
        if (empty($entries) || $this->baseUrl === '') {
            return [];
        }

        $responses = [];
        foreach (array_chunk($entries, $this->maxBatchSize) as $chunk) {
            $responses[] = $this->postWithRetry('/api/ingest/entries', array_merge($meta, [
                'log_name' => $logName,
                'entries'  => $chunk,
            ]));
        }

        return $responses;
    }

    /**
     * POST to the given path with automatic retry/backoff.
     *
     * @param  array<string, mixed> $payload
     * @return array<string, mixed>
     * @throws \RuntimeException on non-retryable failures
     */
    public function postWithRetry(string $path, array $payload): array
    {
        $attempt = 0;
        $delay   = $this->retryDelay;

        while (true) {
            try {
                $response = $this->http->post($path, [
                    RequestOptions::JSON    => $payload,
                    RequestOptions::TIMEOUT => $this->timeout,
                ]);

                return json_decode((string) $response->getBody(), true) ?? [];
            } catch (ConnectException $e) {
                $this->maybeRetry(++$attempt, $delay, $e);
            } catch (RequestException $e) {
                $statusCode = $e->getResponse()?->getStatusCode();

                if ($statusCode !== null && $statusCode < 500 && $statusCode !== 429) {
                    throw new \RuntimeException(
                        "Logalyzer ingest failed (HTTP {$statusCode}): " . $e->getMessage(),
                        $statusCode,
                        $e
                    );
                }

                $this->maybeRetry(++$attempt, $delay, $e);
            }

            $delay *= 2;
        }
    }

    private function maybeRetry(int $attempt, float $delaySecs, \Throwable $e): void
    {
        if ($attempt >= $this->retries) {
            throw new \RuntimeException(
                "Logalyzer ingest failed after {$attempt} retries: " . $e->getMessage(),
                0,
                $e
            );
        }

        $jitter = $delaySecs * (mt_rand(0, 200) / 1_000);
        usleep((int) (($delaySecs + $jitter) * 1_000_000));
    }
}
