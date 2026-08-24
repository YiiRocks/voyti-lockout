<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout\Tests\Listener;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use YiiRocks\Voyti\Event\Auth\BeforeLoginEvent;
use YiiRocks\Voyti\Exception\ActionPreventedException;
use YiiRocks\Voyti\Lockout\FailedAttemptsStore;
use YiiRocks\Voyti\Lockout\Listener\BlockLockedOutLoginListener;
use YiiRocks\Voyti\Lockout\LockoutConfig;
use YiiRocks\Voyti\Lockout\LockoutKeyHelper;
use YiiRocks\Voyti\Lockout\Tests\Support\FixedClock;
use YiiRocks\Voyti\Lockout\Tests\TestCase;
use YiiRocks\Voyti\Model\User;
use Yiisoft\Cache\ArrayCache;

final class BlockLockedOutLoginListenerTest extends TestCase
{
    public static function priorFailuresProvider(): array
    {
        return [
            'first failure, delay starts at the base' => [
                'priorFailures' => 1,
                'expectedRetryAfterSeconds' => 1,
            ],
            'several failures in, still below the cap' => [
                'priorFailures' => 12,
                'expectedRetryAfterSeconds' => 2048,
            ],
            'well past enough failures, delay capped at the maximum' => [
                'priorFailures' => 13,
                'expectedRetryAfterSeconds' => 3600,
            ],
        ];
    }

    public function testAllowsLoginWithNoPriorFailures(): void
    {
        $store = new FailedAttemptsStore(new ArrayCache(), new FixedClock(new DateTimeImmutable()));

        $listener = new BlockLockedOutLoginListener($store, self::createConfig(), $this->createTranslator());

        $listener->onBeforeLogin(new BeforeLoginEvent(new User(), ['REMOTE_ADDR' => '203.0.113.1']));

        $this->addToAssertionCount(1);
    }

    #[DataProvider('priorFailuresProvider')]
    public function testBlocksLoginWithAGrowingDelayOnceAFailureIsRecorded(
        int $priorFailures,
        int $expectedRetryAfterSeconds,
    ): void {
        $store = new FailedAttemptsStore(new ArrayCache(), new FixedClock(new DateTimeImmutable()));
        for ($i = 0; $i < $priorFailures; $i++) {
            $store->recordFailure(
                LockoutKeyHelper::login('203.0.113.1'),
                minRetentionSeconds: 900,
                baseDelaySeconds: 1,
                maxDelaySeconds: 3600,
            );
        }

        $listener = new BlockLockedOutLoginListener($store, self::createConfig(), $this->createTranslator());

        try {
            $listener->onBeforeLogin(new BeforeLoginEvent(new User(), ['REMOTE_ADDR' => '203.0.113.1']));
            self::fail('Expected ActionPreventedException to be thrown.');
        } catch (ActionPreventedException $exception) {
            self::assertSame(
                "Too many failed login attempts. Please try again in $expectedRetryAfterSeconds seconds.",
                $exception->getMessage(),
            );
            self::assertSame(['login'], $exception->getErrorDetails());
        }
    }

    public function testDoesNotBlockADifferentIpSharingNoFailures(): void
    {
        $store = new FailedAttemptsStore(new ArrayCache(), new FixedClock(new DateTimeImmutable()));
        $store->recordFailure(
            LockoutKeyHelper::login('203.0.113.1'),
            minRetentionSeconds: 900,
            baseDelaySeconds: 1,
            maxDelaySeconds: 3600,
        );

        $listener = new BlockLockedOutLoginListener($store, self::createConfig(), $this->createTranslator());

        $listener->onBeforeLogin(new BeforeLoginEvent(new User(), ['REMOTE_ADDR' => '198.51.100.1']));

        $this->addToAssertionCount(1);
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
