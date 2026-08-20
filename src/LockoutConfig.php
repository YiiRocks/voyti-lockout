<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout;

/**
 * Single source of truth for this package's settings: an immutable value object injected into
 * listeners instead of raw params. Default values live in `config/params.php`.
 */
final readonly class LockoutConfig
{
    public function __construct(
        public int $loginMinRetentionSeconds,
        public int $loginBaseDelaySeconds,
        public int $loginMaxDelaySeconds,
        public int $registrationMinRetentionSeconds,
        public int $registrationBaseDelaySeconds,
        public int $registrationMaxDelaySeconds,
    ) {}
}
