<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout\Tests\Listener;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use YiiRocks\Voyti\Event\Auth\BeforeRegisterEvent;
use YiiRocks\Voyti\Exception\ActionPreventedException;
use YiiRocks\Voyti\Lockout\FailedAttemptsStore;
use YiiRocks\Voyti\Lockout\Listener\BlockLockedOutRegistrationListener;
use YiiRocks\Voyti\Lockout\LockoutConfig;
use YiiRocks\Voyti\Lockout\LockoutKeyHelper;
use YiiRocks\Voyti\Lockout\Tests\Support\FixedClock;
use YiiRocks\Voyti\Lockout\Tests\TestCase;
use YiiRocks\Voyti\Model\User;
use Yiisoft\Cache\ArrayCache;

final class BlockLockedOutRegistrationListenerTest extends TestCase
{
    public static function priorFailuresProvider(): array
    {
        return [
            'first failure, delay starts at the base' => [
                'priorFailures' => 1,
                'expectedRetryAfterSeconds' => 1,
            ],
            'several failures in, still below the cap' => [
                'priorFailures' => 9,
                'expectedRetryAfterSeconds' => 256,
            ],
            'well past enough failures, delay capped at the maximum' => [
                'priorFailures' => 11,
                'expectedRetryAfterSeconds' => 600,
            ],
        ];
    }

    public function testAllowsRegistrationWithNoPriorFailures(): void
    {
        $store = new FailedAttemptsStore(new ArrayCache(), new FixedClock(new DateTimeImmutable()));

        $listener = new BlockLockedOutRegistrationListener($store, self::createConfig(), $this->createTranslator());

        $user = new User();
        $user->setRegistrationIp('203.0.113.1');
        $listener->onBeforeRegister(new BeforeRegisterEvent([], $user));

        $this->addToAssertionCount(1);
    }

    #[DataProvider('priorFailuresProvider')]
    public function testBlocksRegistrationWithAGrowingDelayOnceAFailureIsRecorded(
        int $priorFailures,
        int $expectedRetryAfterSeconds,
    ): void {
        $store = new FailedAttemptsStore(new ArrayCache(), new FixedClock(new DateTimeImmutable()));
        for ($i = 0; $i < $priorFailures; $i++) {
            $store->recordFailure(
                LockoutKeyHelper::registration('203.0.113.1'),
                minRetentionSeconds: 60,
                baseDelaySeconds: 1,
                maxDelaySeconds: 600,
            );
        }

        $listener = new BlockLockedOutRegistrationListener($store, self::createConfig(), $this->createTranslator());

        $user = new User();
        $user->setRegistrationIp('203.0.113.1');

        try {
            $listener->onBeforeRegister(new BeforeRegisterEvent([], $user));
            self::fail('Expected ActionPreventedException to be thrown.');
        } catch (ActionPreventedException $exception) {
            self::assertSame(
                "Too many registration attempts. Please try again in $expectedRetryAfterSeconds seconds.",
                $exception->getMessage(),
            );
            self::assertSame(['email'], $exception->getErrorDetails());
        }
    }

    public function testFallsBackToLocalhostWhenRegistrationIpIsNotSet(): void
    {
        $store = new FailedAttemptsStore(new ArrayCache(), new FixedClock(new DateTimeImmutable()));
        $store->recordFailure(
            LockoutKeyHelper::registration('127.0.0.1'),
            minRetentionSeconds: 60,
            baseDelaySeconds: 1,
            maxDelaySeconds: 600,
        );

        $listener = new BlockLockedOutRegistrationListener($store, self::createConfig(), $this->createTranslator());

        $this->expectException(ActionPreventedException::class);
        $listener->onBeforeRegister(new BeforeRegisterEvent([], new User()));
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
