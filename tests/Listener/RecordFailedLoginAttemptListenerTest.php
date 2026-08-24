<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout\Tests\Listener;

use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;
use YiiRocks\Voyti\Event\Auth\FailedLoginEvent;
use YiiRocks\Voyti\Lockout\FailedAttemptsStore;
use YiiRocks\Voyti\Lockout\Listener\RecordFailedLoginAttemptListener;
use YiiRocks\Voyti\Lockout\LockoutConfig;
use YiiRocks\Voyti\Lockout\LockoutKeyHelper;
use YiiRocks\Voyti\Lockout\Tests\Support\FixedClock;
use YiiRocks\Voyti\Lockout\Tests\TestCase;
use Yiisoft\Cache\ArrayCache;

final class RecordFailedLoginAttemptListenerTest extends TestCase
{
    public function testFallsBackToLocalhostWhenRemoteAddrIsMissing(): void
    {
        $store = new FailedAttemptsStore(new ArrayCache(), new FixedClock(new DateTimeImmutable()));
        $listener = new RecordFailedLoginAttemptListener($store, self::createConfig());

        $listener->onFailedLogin(new FailedLoginEvent('user@example.com', 'invalid_password'));

        self::assertSame(1, $store->getAttemptCount(LockoutKeyHelper::login('127.0.0.1')));
    }

    public function testPassesTheLoginDelayConfigurationThroughToTheStoreTtl(): void
    {
        // 12 prior failures recorded; the 13th pushes the delay (1 * 2^12 = 4096s) past the
        // 3600s cap, which is in turn past the 900s window - proving the login-specific config
        // values (not the registration ones) are what drives the TTL.
        $now = new DateTimeImmutable();
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnMap([[LockoutKeyHelper::login('203.0.113.1'), 0, 12]]);
        $cache->expects(self::exactly(2))->method('set')->willReturnMap([
            [LockoutKeyHelper::login('203.0.113.1'), 13, 3600, true],
            [LockoutKeyHelper::login('203.0.113.1') . '.failedAt', $now->getTimestamp(), 3600, true],
        ]);

        $store = new FailedAttemptsStore($cache, new FixedClock($now));
        $config = new LockoutConfig(
            loginMinRetentionSeconds: 900,
            loginBaseDelaySeconds: 1,
            loginMaxDelaySeconds: 3600,
            registrationMinRetentionSeconds: 99,
            registrationBaseDelaySeconds: 99,
            registrationMaxDelaySeconds: 99,
        );
        $listener = new RecordFailedLoginAttemptListener($store, $config);

        $listener->onFailedLogin(
            new FailedLoginEvent('user@example.com', 'invalid_password', ['REMOTE_ADDR' => '203.0.113.1']),
        );
    }

    public function testRecordsFailureAgainstTheRequestIp(): void
    {
        $store = new FailedAttemptsStore(new ArrayCache(), new FixedClock(new DateTimeImmutable()));
        $listener = new RecordFailedLoginAttemptListener($store, self::createConfig());

        $listener->onFailedLogin(
            new FailedLoginEvent('user@example.com', 'invalid_password', ['REMOTE_ADDR' => '203.0.113.1']),
        );
        $listener->onFailedLogin(
            new FailedLoginEvent('user@example.com', 'invalid_password', ['REMOTE_ADDR' => '203.0.113.1']),
        );

        self::assertSame(2, $store->getAttemptCount(LockoutKeyHelper::login('203.0.113.1')));
    }

    private static function createConfig(): LockoutConfig
    {
        return new LockoutConfig(
            loginMinRetentionSeconds: 900,
            loginBaseDelaySeconds: 1,
            loginMaxDelaySeconds: 3600,
            registrationMinRetentionSeconds: 60,
            registrationBaseDelaySeconds: 1,
            registrationMaxDelaySeconds: 600,
        );
    }
}
