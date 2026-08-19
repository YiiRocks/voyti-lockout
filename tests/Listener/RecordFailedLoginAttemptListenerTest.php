<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout\Tests\Listener;

use YiiRocks\Voyti\Event\Auth\FailedLoginEvent;
use YiiRocks\Voyti\Lockout\FailedAttemptsStore;
use YiiRocks\Voyti\Lockout\Listener\RecordFailedLoginAttemptListener;
use YiiRocks\Voyti\Lockout\LockoutConfig;
use YiiRocks\Voyti\Lockout\LockoutKeyHelper;
use YiiRocks\Voyti\Lockout\Tests\TestCase;
use Yiisoft\Cache\ArrayCache;

final class RecordFailedLoginAttemptListenerTest extends TestCase
{
    public function testFallsBackToLocalhostWhenRemoteAddrIsMissing(): void
    {
        $store = new FailedAttemptsStore(new ArrayCache());
        $config = new LockoutConfig(
            loginMaxAttempts: 5,
            loginWindowSeconds: 900,
            registrationMaxAttempts: 10,
            registrationWindowSeconds: 60,
        );
        $listener = new RecordFailedLoginAttemptListener($store, $config);

        $listener->onFailedLogin(new FailedLoginEvent('user@example.com', 'invalid_password'));

        self::assertSame(1, $store->getAttemptCount(LockoutKeyHelper::login('127.0.0.1')));
    }

    public function testRecordsFailureAgainstTheRequestIp(): void
    {
        $store = new FailedAttemptsStore(new ArrayCache());
        $config = new LockoutConfig(
            loginMaxAttempts: 5,
            loginWindowSeconds: 900,
            registrationMaxAttempts: 10,
            registrationWindowSeconds: 60,
        );
        $listener = new RecordFailedLoginAttemptListener($store, $config);

        $listener->onFailedLogin(
            new FailedLoginEvent('user@example.com', 'invalid_password', ['REMOTE_ADDR' => '203.0.113.1']),
        );
        $listener->onFailedLogin(
            new FailedLoginEvent('user@example.com', 'invalid_password', ['REMOTE_ADDR' => '203.0.113.1']),
        );

        self::assertSame(2, $store->getAttemptCount(LockoutKeyHelper::login('203.0.113.1')));
    }
}
