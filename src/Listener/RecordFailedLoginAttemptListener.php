<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout\Listener;

use YiiRocks\Voyti\Event\Auth\FailedLoginEvent;
use YiiRocks\Voyti\Helper\LoginMetadataHelper;
use YiiRocks\Voyti\Lockout\FailedAttemptsStore;
use YiiRocks\Voyti\Lockout\LockoutConfig;
use YiiRocks\Voyti\Lockout\LockoutKeyHelper;

/**
 * Listens for {@see FailedLoginEvent} and records the failure against the request's IP address, so
 * {@see BlockLockedOutLoginListener} can later block further attempts once the threshold is reached.
 */
final readonly class RecordFailedLoginAttemptListener
{
    public function __construct(
        private FailedAttemptsStore $store,
        private LockoutConfig $config,
    ) {}

    public function onFailedLogin(FailedLoginEvent $event): void
    {
        $ip = LoginMetadataHelper::remoteAddr($event->getServerParams());
        $this->store->recordFailure(LockoutKeyHelper::login($ip), $this->config->loginWindowSeconds);
    }
}
