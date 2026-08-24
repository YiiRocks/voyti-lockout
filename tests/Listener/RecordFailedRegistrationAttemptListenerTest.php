<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout\Tests\Listener;

use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;
use YiiRocks\Voyti\Event\Auth\RegisterFormValidationFailedEvent;
use YiiRocks\Voyti\Lockout\FailedAttemptsStore;
use YiiRocks\Voyti\Lockout\Listener\RecordFailedRegistrationAttemptListener;
use YiiRocks\Voyti\Lockout\LockoutConfig;
use YiiRocks\Voyti\Lockout\LockoutKeyHelper;
use YiiRocks\Voyti\Lockout\Tests\Support\FixedClock;
use YiiRocks\Voyti\Lockout\Tests\TestCase;
use Yiisoft\Cache\ArrayCache;

final class RecordFailedRegistrationAttemptListenerTest extends TestCase
{
    public function testFallsBackToLocalhostWhenRemoteAddrIsMissing(): void
    {
        $store = new FailedAttemptsStore(new ArrayCache(), new FixedClock(new DateTimeImmutable()));
        $listener = new RecordFailedRegistrationAttemptListener($store, self::createConfig());

        $listener->onRegisterFormValidationFailed(new RegisterFormValidationFailedEvent([], ['email' => 'invalid']));

        self::assertSame(1, $store->getAttemptCount(LockoutKeyHelper::registration('127.0.0.1')));
    }

    public function testPassesTheRegistrationDelayConfigurationThroughToTheStoreTtl(): void
    {
        // 10 prior failures recorded; the 11th pushes the delay (1 * 2^10 = 1024s) past the 600s
        // cap, which is in turn past the 60s window - proving the registration-specific config
        // values (not the login ones) are what drives the TTL.
        $now = new DateTimeImmutable();
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnMap([[LockoutKeyHelper::registration('203.0.113.1'), 0, 10]]);
        $cache->expects(self::exactly(2))->method('set')->willReturnMap([
            [LockoutKeyHelper::registration('203.0.113.1'), 11, 600, true],
            [LockoutKeyHelper::registration('203.0.113.1') . '.failedAt', $now->getTimestamp(), 600, true],
        ]);

        $store = new FailedAttemptsStore($cache, new FixedClock($now));
        $config = new LockoutConfig(
            loginMinRetentionSeconds: 99,
            loginBaseDelaySeconds: 99,
            loginMaxDelaySeconds: 99,
            registrationMinRetentionSeconds: 60,
            registrationBaseDelaySeconds: 1,
            registrationMaxDelaySeconds: 600,
        );
        $listener = new RecordFailedRegistrationAttemptListener($store, $config);

        $listener->onRegisterFormValidationFailed(
            new RegisterFormValidationFailedEvent([], ['email' => 'invalid'], ['REMOTE_ADDR' => '203.0.113.1']),
        );
    }

    public function testRecordsFailureAgainstTheRequestIp(): void
    {
        $store = new FailedAttemptsStore(new ArrayCache(), new FixedClock(new DateTimeImmutable()));
        $listener = new RecordFailedRegistrationAttemptListener($store, self::createConfig());

        $listener->onRegisterFormValidationFailed(
            new RegisterFormValidationFailedEvent([], ['email' => 'invalid'], ['REMOTE_ADDR' => '203.0.113.1']),
        );

        self::assertSame(1, $store->getAttemptCount(LockoutKeyHelper::registration('203.0.113.1')));
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
