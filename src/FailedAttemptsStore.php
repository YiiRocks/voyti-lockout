<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout;

use Psr\SimpleCache\CacheInterface;

/**
 * Tracks failed-attempt counts per key in a PSR-16 cache. Each {@see self::recordFailure()} call
 * renews the key's TTL to at least `$minRetentionSeconds`, so the count is a sliding window anchored
 * to the most recent failure rather than a fixed window from the first one: an attacker who keeps
 * failing stays tracked, and the count only cools down once attempts actually stop for the configured
 * duration. Once the computed {@see RetryDelayHelper} delay exceeds `$minRetentionSeconds`, the TTL
 * instead tracks that delay, so the count can't reset while the caller is still required to wait.
 */
final readonly class FailedAttemptsStore
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function getAttemptCount(string $key): int
    {
        return (int) $this->cache->get($key, 0);
    }

    public function getRetryAfterSeconds(string $key, int $baseDelaySeconds, int $maxDelaySeconds): int
    {
        return RetryDelayHelper::forAttempts($this->getAttemptCount($key), $baseDelaySeconds, $maxDelaySeconds);
    }

    public function recordFailure(
        string $key,
        int $minRetentionSeconds,
        int $baseDelaySeconds,
        int $maxDelaySeconds,
    ): void {
        $newAttemptCount = $this->getAttemptCount($key) + 1;
        $delay = RetryDelayHelper::forAttempts($newAttemptCount, $baseDelaySeconds, $maxDelaySeconds);

        $this->cache->set($key, $newAttemptCount, max($minRetentionSeconds, $delay));
    }
}
