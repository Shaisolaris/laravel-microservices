<?php

declare(strict_types=1);

namespace Shared\Messages;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class ServiceClient
{
    private Client $http;
    private CircuitBreaker $circuitBreaker;

    public function __construct(
        private readonly string $serviceName,
        private readonly string $baseUrl,
        private readonly int $timeout = 10,
    ) {
        $this->http = new Client([
            'base_uri' => $baseUrl,
            'timeout' => $timeout,
            'connect_timeout' => 5,
        ]);
        $this->circuitBreaker = new CircuitBreaker($serviceName);
    }

    public function get(string $path, array $query = [], array $headers = []): array
    {
        return $this->request('GET', $path, ['query' => $query, 'headers' => $this->buildHeaders($headers)]);
    }

    public function post(string $path, array $data = [], array $headers = []): array
    {
        return $this->request('POST', $path, ['json' => $data, 'headers' => $this->buildHeaders($headers)]);
    }

    public function put(string $path, array $data = [], array $headers = []): array
    {
        return $this->request('PUT', $path, ['json' => $data, 'headers' => $this->buildHeaders($headers)]);
    }

    public function delete(string $path, array $headers = []): array
    {
        return $this->request('DELETE', $path, ['headers' => $this->buildHeaders($headers)]);
    }

    private function request(string $method, string $path, array $options): array
    {
        $traceId = $options['headers']['X-Trace-Id'] ?? bin2hex(random_bytes(16));

        return $this->circuitBreaker->call(
            action: function () use ($method, $path, $options, $traceId) {
                $startTime = microtime(true);

                try {
                    $response = $this->http->request($method, $path, $options);
                    $duration = round((microtime(true) - $startTime) * 1000, 2);

                    Log::info("[ServiceClient] {$this->serviceName}", [
                        'method' => $method,
                        'path' => $path,
                        'status' => $response->getStatusCode(),
                        'duration_ms' => $duration,
                        'trace_id' => $traceId,
                    ]);

                    return json_decode($response->getBody()->getContents(), true) ?? [];
                } catch (GuzzleException $e) {
                    $duration = round((microtime(true) - $startTime) * 1000, 2);

                    Log::error("[ServiceClient] {$this->serviceName} FAILED", [
                        'method' => $method,
                        'path' => $path,
                        'error' => $e->getMessage(),
                        'duration_ms' => $duration,
                        'trace_id' => $traceId,
                    ]);

                    throw $e;
                }
            },
            fallback: function () use ($method, $path) {
                Log::warning("[ServiceClient] Circuit breaker open for {$this->serviceName}", [
                    'method' => $method,
                    'path' => $path,
                ]);
                return ['error' => "Service {$this->serviceName} is temporarily unavailable", 'circuit_breaker' => 'open'];
            },
        );
    }

    private function buildHeaders(array $extra): array
    {
        return array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Service-Name' => 'gateway',
            'X-Trace-Id' => bin2hex(random_bytes(16)),
        ], $extra);
    }
}
