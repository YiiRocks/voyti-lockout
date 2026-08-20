<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use YiiRocks\Voyti\Lockout\RetryDelayHelper;

final class RetryDelayHelperTest extends TestCase
{
    public static function attemptsProvider(): array
    {
        return [
            'no attempts yet, no delay' => ['attempts' => 0, 'expectedDelay' => 0],
            'first failure, delay starts at the base' => ['attempts' => 1, 'expectedDelay' => 1],
            'second failure, delay doubles' => ['attempts' => 2, 'expectedDelay' => 2],
            'several failures in, still below the cap' => ['attempts' => 12, 'expectedDelay' => 2048],
            'just enough failures to exceed the cap' => ['attempts' => 13, 'expectedDelay' => 3600],
            'far beyond the cap, no hard ceiling on attempts' => ['attempts' => 1_000_000, 'expectedDelay' => 3600],
        ];
    }

    #[DataProvider('attemptsProvider')]
    public function testForAttempts(int $attempts, int $expectedDelay): void
    {
        self::assertSame(
            $expectedDelay,
            RetryDelayHelper::forAttempts(attempts: $attempts, baseDelaySeconds: 1, maxDelaySeconds: 3600),
        );
    }

    public function testZeroAttemptsIsNoDelayRegardlessOfBaseDelay(): void
    {
        // With a base delay above 1, falling through to the formula instead of returning early
        // would compute a nonzero result (3 * 2^-1 = 1.5, truncated to 1), so this catches a
        // removed early-return guard that a base of 1 alone would mask (0.5 truncates to 0 too).
        self::assertSame(0, RetryDelayHelper::forAttempts(attempts: 0, baseDelaySeconds: 3, maxDelaySeconds: 3600));
    }
}
