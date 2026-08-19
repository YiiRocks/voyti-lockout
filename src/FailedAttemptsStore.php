<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout;

use Psr\SimpleCache\CacheInterface;

/**
 * Tracks failed-attempt counts per key in a PSR-16 cache. Each {@see self::recordFailure()} call
 * renews the key's TTL to the full window, so the count is a sliding window anchored to the most
 * recent failure rather than a fixed window from the first one: an attacker who keeps failing stays
 * blocked, and the block only cools down once attempts actually stop for the configured duration.
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

    public function recordFailure(string $key, int $windowSeconds): void
    {
        $this->cache->set($key, $this->getAttemptCount($key) + 1, $windowSeconds);
    }
}
