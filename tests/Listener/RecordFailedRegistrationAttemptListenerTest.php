<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout\Tests\Listener;

use YiiRocks\Voyti\Event\Auth\RegisterFormValidationFailedEvent;
use YiiRocks\Voyti\Lockout\FailedAttemptsStore;
use YiiRocks\Voyti\Lockout\Listener\RecordFailedRegistrationAttemptListener;
use YiiRocks\Voyti\Lockout\LockoutConfig;
use YiiRocks\Voyti\Lockout\LockoutKeyHelper;
use YiiRocks\Voyti\Lockout\Tests\TestCase;
use Yiisoft\Cache\ArrayCache;

final class RecordFailedRegistrationAttemptListenerTest extends TestCase
{
    public function testFallsBackToLocalhostWhenRemoteAddrIsMissing(): void
    {
        $store = new FailedAttemptsStore(new ArrayCache());
        $listener = new RecordFailedRegistrationAttemptListener($store, new LockoutConfig(5, 900, 10, 60));

        $listener->onRegisterFormValidationFailed(new RegisterFormValidationFailedEvent([], ['email' => 'invalid']));

        self::assertSame(1, $store->getAttemptCount(LockoutKeyHelper::registration('127.0.0.1')));
    }

    public function testRecordsFailureAgainstTheRequestIp(): void
    {
        $store = new FailedAttemptsStore(new ArrayCache());
        $listener = new RecordFailedRegistrationAttemptListener($store, new LockoutConfig(5, 900, 10, 60));

        $listener->onRegisterFormValidationFailed(
            new RegisterFormValidationFailedEvent([], ['email' => 'invalid'], ['REMOTE_ADDR' => '203.0.113.1']),
        );

        self::assertSame(1, $store->getAttemptCount(LockoutKeyHelper::registration('203.0.113.1')));
    }
}
