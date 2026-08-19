<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout\Tests\Listener;

use YiiRocks\Voyti\Event\Auth\BeforeLoginEvent;
use YiiRocks\Voyti\Exception\ActionPreventedException;
use YiiRocks\Voyti\Lockout\FailedAttemptsStore;
use YiiRocks\Voyti\Lockout\Listener\BlockLockedOutLoginListener;
use YiiRocks\Voyti\Lockout\LockoutConfig;
use YiiRocks\Voyti\Lockout\LockoutKeyHelper;
use YiiRocks\Voyti\Lockout\Tests\TestCase;
use YiiRocks\Voyti\Model\User;
use Yiisoft\Cache\ArrayCache;

final class BlockLockedOutLoginListenerTest extends TestCase
{
    public function testAllowsLoginBelowTheThreshold(): void
    {
        $cache = new ArrayCache();
        $store = new FailedAttemptsStore($cache);
        $store->recordFailure(LockoutKeyHelper::login('203.0.113.1'), 900);
        $store->recordFailure(LockoutKeyHelper::login('203.0.113.1'), 900);

        $listener = new BlockLockedOutLoginListener(
            $store,
            new LockoutConfig(5, 900, 10, 60),
            $this->createTranslator(),
        );

        $listener->onBeforeLogin(new BeforeLoginEvent(new User(), ['REMOTE_ADDR' => '203.0.113.1']));

        $this->addToAssertionCount(1);
    }

    public function testBlocksLoginOnceTheThresholdIsReached(): void
    {
        $store = new FailedAttemptsStore(new ArrayCache());
        for ($i = 0; $i < 5; $i++) {
            $store->recordFailure(LockoutKeyHelper::login('203.0.113.1'), 900);
        }

        $listener = new BlockLockedOutLoginListener(
            $store,
            new LockoutConfig(5, 900, 10, 60),
            $this->createTranslator(),
        );

        try {
            $listener->onBeforeLogin(new BeforeLoginEvent(new User(), ['REMOTE_ADDR' => '203.0.113.1']));
            self::fail('Expected ActionPreventedException to be thrown.');
        } catch (ActionPreventedException $exception) {
            self::assertSame('Too many failed login attempts. Please try again later.', $exception->getMessage());
            self::assertSame(['login'], $exception->getErrorDetails());
        }
    }

    public function testDoesNotBlockADifferentIpSharingNoFailures(): void
    {
        $store = new FailedAttemptsStore(new ArrayCache());
        for ($i = 0; $i < 5; $i++) {
            $store->recordFailure(LockoutKeyHelper::login('203.0.113.1'), 900);
        }

        $listener = new BlockLockedOutLoginListener(
            $store,
            new LockoutConfig(5, 900, 10, 60),
            $this->createTranslator(),
        );

        $listener->onBeforeLogin(new BeforeLoginEvent(new User(), ['REMOTE_ADDR' => '198.51.100.1']));

        $this->addToAssertionCount(1);
    }
}
