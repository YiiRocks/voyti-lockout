<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout\Tests;

use Psr\SimpleCache\CacheInterface;
use YiiRocks\Voyti\Lockout\FailedAttemptsStore;

final class FailedAttemptsStoreTest extends TestCase
{
    public function testGetAttemptCount(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnMap([['key', 0, 3]]);

        self::assertSame(3, (new FailedAttemptsStore($cache))->getAttemptCount('key'));
    }

    public function testGetAttemptCountDefaultsToZeroWhenUncached(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnMap([['key', 0, 0]]);

        self::assertSame(0, (new FailedAttemptsStore($cache))->getAttemptCount('key'));
    }

    public function testGetRetryAfterSecondsUsesTheCurrentAttemptCount(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnMap([['key', 0, 6]]);

        self::assertSame(
            32,
            (new FailedAttemptsStore($cache))->getRetryAfterSeconds('key', baseDelaySeconds: 1, maxDelaySeconds: 3600),
        );
    }

    public function testRecordFailureExtendsTtlPastTheWindowOnceTheDelayExceedsIt(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnMap([['key', 0, 13]]);
        $cache->expects(self::once())->method('set')->with('key', 14, 3600);

        (new FailedAttemptsStore($cache))->recordFailure(
            'key',
            minRetentionSeconds: 900,
            baseDelaySeconds: 1,
            maxDelaySeconds: 3600,
        );
    }

    public function testRecordFailureIncrementsCountAndRenewsTtlToTheFullWindowWhileTheDelayStaysBelowIt(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnMap([['key', 0, 2]]);
        $cache->expects(self::once())->method('set')->with('key', 3, 900);

        (new FailedAttemptsStore($cache))->recordFailure(
            'key',
            minRetentionSeconds: 900,
            baseDelaySeconds: 1,
            maxDelaySeconds: 3600,
        );
    }
}
