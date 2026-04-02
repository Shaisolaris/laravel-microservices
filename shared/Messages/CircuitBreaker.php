<?php

declare(strict_types=1);

namespace Shared\Messages;

use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    public function __construct(
        private readonly string $service,
        private readonly int $failureThreshold = 5,
        private readonly int $recoveryTimeout = 30, // seconds
        private readonly int $successThreshold = 3,
    ) {}

    public function call(callable $action, callable $fallback = null): mixed
    {
        $state = $this->getState();

        if ($state === self::STATE_OPEN) {
            if ($this->shouldAttemptRecovery()) {
                $this->setState(self::STATE_HALF_OPEN);
            } else {
                return $fallback ? $fallback() : throw new \RuntimeException("Circuit breaker is open for service: {$this->service}");
            }
        }

        try {
            $result = $action();
            $this->onSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->onFailure();

            if ($this->getFailureCount() >= $this->failureThreshold) {
                $this->setState(self::STATE_OPEN);
            }

            if ($fallback) {
                return $fallback();
            }

            throw $e;
        }
    }

    private function getState(): string
    {
        return Cache::get("circuit_breaker:{$this->service}:state", self::STATE_CLOSED);
    }

    private function setState(string $state): void
    {
        Cache::put("circuit_breaker:{$this->service}:state", $state, 300);

        if ($state === self::STATE_OPEN) {
            Cache::put("circuit_breaker:{$this->service}:opened_at", time(), 300);
        }
    }

    private function getFailureCount(): int
    {
        return (int) Cache::get("circuit_breaker:{$this->service}:failures", 0);
    }

    private function onSuccess(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            $successes = (int) Cache::increment("circuit_breaker:{$this->service}:successes");

            if ($successes >= $this->successThreshold) {
                $this->reset();
            }
        } else {
            Cache::put("circuit_breaker:{$this->service}:failures", 0, 300);
        }
    }

    private function onFailure(): void
    {
        Cache::increment("circuit_breaker:{$this->service}:failures");
        Cache::put("circuit_breaker:{$this->service}:successes", 0, 300);
    }

    private function shouldAttemptRecovery(): bool
    {
        $openedAt = Cache::get("circuit_breaker:{$this->service}:opened_at", 0);
        return (time() - $openedAt) >= $this->recoveryTimeout;
    }

    private function reset(): void
    {
        Cache::put("circuit_breaker:{$this->service}:state", self::STATE_CLOSED, 300);
        Cache::put("circuit_breaker:{$this->service}:failures", 0, 300);
        Cache::put("circuit_breaker:{$this->service}:successes", 0, 300);
    }
}
