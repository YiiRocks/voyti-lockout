<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout;

/**
 * Computes the exponential backoff delay for a given attempt count: no delay with zero recorded
 * failures, then `baseDelaySeconds` doubling on every attempt, capped at `maxDelaySeconds`. There is
 * no hard ceiling on attempts - an attacker who keeps failing indefinitely just keeps hitting the
 * capped delay, per OWASP/NIST guidance against a control that itself becomes a denial-of-service.
 */
final class RetryDelayHelper
{
    public static function forAttempts(int $attempts, int $baseDelaySeconds, int $maxDelaySeconds): int
    {
        if ($attempts < 1) {
            return 0;
        }

        // 2 ** ($attempts - 1) overflows to float for very large attempt counts (no hard ceiling is
        // enforced on attempts) rather than erroring; min() below always then picks the smaller int
        // $maxDelaySeconds, so the final result is always a real int.
        /** @psalm-suppress InvalidOperand */
        $delay = $baseDelaySeconds * 2 ** ($attempts - 1);

        return (int) min($delay, $maxDelaySeconds);
    }
}
