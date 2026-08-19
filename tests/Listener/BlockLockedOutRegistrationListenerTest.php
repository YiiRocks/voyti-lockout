<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout\Tests\Listener;

use YiiRocks\Voyti\Event\Auth\BeforeRegisterEvent;
use YiiRocks\Voyti\Exception\ActionPreventedException;
use YiiRocks\Voyti\Lockout\FailedAttemptsStore;
use YiiRocks\Voyti\Lockout\Listener\BlockLockedOutRegistrationListener;
use YiiRocks\Voyti\Lockout\LockoutConfig;
use YiiRocks\Voyti\Lockout\LockoutKeyHelper;
use YiiRocks\Voyti\Lockout\Tests\TestCase;
use YiiRocks\Voyti\Model\User;
use Yiisoft\Cache\ArrayCache;

final class BlockLockedOutRegistrationListenerTest extends TestCase
{
    public function testAllowsRegistrationBelowTheThreshold(): void
    {
        $store = new FailedAttemptsStore(new ArrayCache());
        $store->recordFailure(LockoutKeyHelper::registration('203.0.113.1'), 60);

        $listener = new BlockLockedOutRegistrationListener(
            $store,
            new LockoutConfig(5, 900, 10, 60),
            $this->createTranslator(),
        );

        $user = new User();
        $user->setRegistrationIp('203.0.113.1');
        $listener->onBeforeRegister(new BeforeRegisterEvent([], $user));

        $this->addToAssertionCount(1);
    }

    public function testBlocksRegistrationOnceTheThresholdIsReached(): void
    {
        $store = new FailedAttemptsStore(new ArrayCache());
        for ($i = 0; $i < 10; $i++) {
            $store->recordFailure(LockoutKeyHelper::registration('203.0.113.1'), 60);
        }

        $listener = new BlockLockedOutRegistrationListener(
            $store,
            new LockoutConfig(5, 900, 10, 60),
            $this->createTranslator(),
        );

        $user = new User();
        $user->setRegistrationIp('203.0.113.1');

        try {
            $listener->onBeforeRegister(new BeforeRegisterEvent([], $user));
            self::fail('Expected ActionPreventedException to be thrown.');
        } catch (ActionPreventedException $exception) {
            self::assertSame(
                'Too many registration attempts. Please try again later.',
                $exception->getMessage(),
            );
            self::assertSame(['email'], $exception->getErrorDetails());
        }
    }

    public function testFallsBackToLocalhostWhenRegistrationIpIsNotSet(): void
    {
        $store = new FailedAttemptsStore(new ArrayCache());
        for ($i = 0; $i < 5; $i++) {
            $store->recordFailure(LockoutKeyHelper::registration('127.0.0.1'), 60);
        }

        $listener = new BlockLockedOutRegistrationListener(
            $store,
            new LockoutConfig(5, 900, 5, 60),
            $this->createTranslator(),
        );

        $this->expectException(ActionPreventedException::class);
        $listener->onBeforeRegister(new BeforeRegisterEvent([], new User()));
    }
}
