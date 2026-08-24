<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout;

use Psr\Clock\ClockInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Tracks failed-attempt counts per key in a PSR-16 cache. Each {@see self::recordFailure()} call
 * renews the key's TTL to at least `$minRetentionSeconds`, so the count is a sliding window anchored
 * to the most recent failure rather than a fixed window from the first one: an attacker who keeps
 * failing stays tracked, and the count only cools down once attempts actually stop for the configured
 * duration. Once the computed {@see RetryDelayHelper} delay exceeds `$minRetentionSeconds`, the TTL
 * instead tracks that delay, so the count can't reset while the caller is still required to wait.
 *
 * The timestamp of the most recent failure is cached alongside the count (under `$key` suffixed with
 * {@see self::FAILED_AT_KEY_SUFFIX}), so {@see self::getRetryAfterSeconds()} can return the wait time
 * actually remaining - the full computed delay minus time already elapsed since that failure - rather
 * than the same full delay on every check for as long as the count stays cached.
 */
final readonly class FailedAttemptsStore
{
    private const string FAILED_AT_KEY_SUFFIX = '.failedAt';

    public function __construct(
        private CacheInterface $cache,
        private ClockInterface $clock,
    ) {}

    public function getAttemptCount(string $key): int
    {
        return (int) $this->cache->get($key, 0);
    }

    public function getRetryAfterSeconds(string $key, int $baseDelaySeconds, int $maxDelaySeconds): int
    {
        $delay = RetryDelayHelper::forAttempts($this->getAttemptCount($key), $baseDelaySeconds, $maxDelaySeconds);
        if ($delay === 0) {
            return 0;
        }

        $failedAt = (int) $this->cache->get($key . self::FAILED_AT_KEY_SUFFIX, 0);
        $elapsedSeconds = $this->clock->now()->getTimestamp() - $failedAt;

        return max(0, $delay - $elapsedSeconds);
    }

    public function recordFailure(
        string $key,
        int $minRetentionSeconds,
        int $baseDelaySeconds,
        int $maxDelaySeconds,
    ): void {
        $newAttemptCount = $this->getAttemptCount($key) + 1;
        $delay = RetryDelayHelper::forAttempts($newAttemptCount, $baseDelaySeconds, $maxDelaySeconds);
        $ttl = max($minRetentionSeconds, $delay);

        $this->cache->set($key, $newAttemptCount, $ttl);
        $this->cache->set($key . self::FAILED_AT_KEY_SUFFIX, $this->clock->now()->getTimestamp(), $ttl);
    }
}
