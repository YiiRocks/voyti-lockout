<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\SimpleCache\CacheInterface;
use YiiRocks\Voyti\Lockout\FailedAttemptsStore;
use YiiRocks\Voyti\Lockout\Tests\Support\FixedClock;
use Yiisoft\Cache\ArrayCache;

final class FailedAttemptsStoreTest extends TestCase
{
    public static function elapsedTimeProvider(): array
    {
        return [
            'partway through the delay, the wait keeps decreasing' => [
                'priorFailures' => 6,
                'elapsedInterval' => '+5 seconds',
                'expectedRetryAfterSeconds' => 27,
            ],
            'delay fully elapsed, clamped at zero rather than going negative' => [
                'priorFailures' => 1,
                'elapsedInterval' => '+1 hour',
                'expectedRetryAfterSeconds' => 0,
            ],
        ];
    }

    public static function recordFailureProvider(): array
    {
        return [
            'delay stays below the window, ttl renews to the full window' => [
                'priorCount' => 2,
                'expectedNewCount' => 3,
                'expectedTtl' => 900,
            ],
            'delay exceeds the window, ttl extends past it' => [
                'priorCount' => 13,
                'expectedNewCount' => 14,
                'expectedTtl' => 3600,
            ],
        ];
    }

    public function testGetAttemptCount(): void
    {
        $clock = new FixedClock(new DateTimeImmutable());

        // Cached count is returned as-is.
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnMap([['key', 0, 3]]);
        self::assertSame(3, (new FailedAttemptsStore($cache, $clock))->getAttemptCount('key'));

        // Uncached key defaults to zero.
        $uncachedCache = $this->createStub(CacheInterface::class);
        $uncachedCache->method('get')->willReturnMap([['key', 0, 0]]);
        self::assertSame(0, (new FailedAttemptsStore($uncachedCache, $clock))->getAttemptCount('key'));
    }

    #[DataProvider('elapsedTimeProvider')]
    public function testGetRetryAfterSecondsCountsDownAsTimeElapsesSinceTheFailure(
        int $priorFailures,
        string $elapsedInterval,
        int $expectedRetryAfterSeconds,
    ): void {
        $cache = new ArrayCache();
        $failedAt = new DateTimeImmutable('2026-01-01 00:00:00');
        for ($i = 0; $i < $priorFailures; $i++) {
            (new FailedAttemptsStore($cache, new FixedClock($failedAt)))->recordFailure(
                'key',
                minRetentionSeconds: 900,
                baseDelaySeconds: 1,
                maxDelaySeconds: 3600,
            );
        }

        $later = $failedAt->modify($elapsedInterval);
        self::assertSame(
            $expectedRetryAfterSeconds,
            (new FailedAttemptsStore($cache, new FixedClock($later)))
                ->getRetryAfterSeconds('key', baseDelaySeconds: 1, maxDelaySeconds: 3600),
        );
    }

    public function testGetRetryAfterSecondsHandlesEdgeCasesInTheStoredFailedAtValue(): void
    {
        // A fractional string ("3.9") stands in for a cache backend that doesn't preserve the stored
        // int type. Without the (int) cast, "3.9" coerces to a float in the subtraction below, and the
        // resulting float would violate this method's `: int` return type under strict_types.
        $nonIntCache = $this->createStub(CacheInterface::class);
        $nonIntCache->method('get')->willReturnMap([
            ['key', 0, 1],
            ['key.failedAt', 0, '3.9'],
        ]);
        self::assertSame(
            3,
            (new FailedAttemptsStore($nonIntCache, new FixedClock(new DateTimeImmutable('@100'))))
                ->getRetryAfterSeconds('key', baseDelaySeconds: 100, maxDelaySeconds: 1000),
        );

        // A count is cached but its paired failedAt entry is missing (e.g. it expired independently) -
        // treated as having failed at the epoch, so the elapsed time swamps the delay.
        $missingFailedAtCache = $this->createStub(CacheInterface::class);
        $missingFailedAtCache->method('get')->willReturnCallback(
            static fn(string $key, mixed $default = null): mixed => $key === 'key' ? 1 : $default,
        );
        self::assertSame(
            50,
            (new FailedAttemptsStore($missingFailedAtCache, new FixedClock(new DateTimeImmutable('@50'))))
                ->getRetryAfterSeconds('key', baseDelaySeconds: 100, maxDelaySeconds: 1000),
        );
    }

    public function testGetRetryAfterSecondsSkipsTheFailedAtLookupWithNoPriorFailures(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->with('key', 0)->willReturn(0);

        self::assertSame(
            0,
            (new FailedAttemptsStore($cache, new FixedClock(new DateTimeImmutable())))
                ->getRetryAfterSeconds('key', baseDelaySeconds: 1, maxDelaySeconds: 3600),
        );
    }

    #[DataProvider('recordFailureProvider')]
    public function testRecordFailureIncrementsCountAndSetsTtlToTheLargerOfWindowOrDelay(
        int $priorCount,
        int $expectedNewCount,
        int $expectedTtl,
    ): void {
        $now = new DateTimeImmutable('2025-01-01 00:00:00', new DateTimeZone('UTC'));
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnMap([['key', 0, $priorCount]]);
        $cache->expects(self::exactly(2))->method('set')->willReturnMap([
            ['key', $expectedNewCount, $expectedTtl, true],
            ['key.failedAt', $now->getTimestamp(), $expectedTtl, true],
        ]);

        (new FailedAttemptsStore($cache, new FixedClock($now)))->recordFailure(
            'key',
            minRetentionSeconds: 900,
            baseDelaySeconds: 1,
            maxDelaySeconds: 3600,
        );
    }
}
