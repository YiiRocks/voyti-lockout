<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout\Listener;

use YiiRocks\Voyti\Event\Auth\RegisterFormValidationFailedEvent;
use YiiRocks\Voyti\Helper\LoginMetadataHelper;
use YiiRocks\Voyti\Lockout\FailedAttemptsStore;
use YiiRocks\Voyti\Lockout\LockoutConfig;
use YiiRocks\Voyti\Lockout\LockoutKeyHelper;

/**
 * Listens for {@see RegisterFormValidationFailedEvent} and records the failure against the request's
 * IP address, so {@see BlockLockedOutRegistrationListener} can later block further attempts once the
 * threshold is reached.
 */
final readonly class RecordFailedRegistrationAttemptListener
{
    public function __construct(
        private FailedAttemptsStore $store,
        private LockoutConfig $config,
    ) {}

    public function onRegisterFormValidationFailed(RegisterFormValidationFailedEvent $event): void
    {
        $ip = LoginMetadataHelper::remoteAddr($event->getServerParams());
        $this->store->recordFailure(
            LockoutKeyHelper::registration($ip),
            minRetentionSeconds: $this->config->registrationMinRetentionSeconds,
            baseDelaySeconds: $this->config->registrationBaseDelaySeconds,
            maxDelaySeconds: $this->config->registrationMaxDelaySeconds,
        );
    }
}
