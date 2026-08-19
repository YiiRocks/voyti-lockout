<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout\Listener;

use YiiRocks\Voyti\Event\Auth\BeforeLoginEvent;
use YiiRocks\Voyti\Exception\ActionPreventedException;
use YiiRocks\Voyti\Helper\LoginMetadataHelper;
use YiiRocks\Voyti\Lockout\FailedAttemptsStore;
use YiiRocks\Voyti\Lockout\LockoutConfig;
use YiiRocks\Voyti\Lockout\LockoutKeyHelper;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Listens for the cancellable {@see BeforeLoginEvent} and blocks the login, even with correct
 * credentials, once the request's IP address has {@see LockoutConfig::$loginMaxAttempts} or more
 * failures recorded by {@see RecordFailedLoginAttemptListener} within the configured window - this
 * is the defense against an attacker who eventually guesses the correct password after enough
 * failures.
 */
final readonly class BlockLockedOutLoginListener
{
    public function __construct(
        private FailedAttemptsStore $store,
        private LockoutConfig $config,
        private TranslatorInterface $translator,
    ) {}

    public function onBeforeLogin(BeforeLoginEvent $event): void
    {
        $ip = LoginMetadataHelper::remoteAddr($event->getServerParams());
        $attempts = $this->store->getAttemptCount(LockoutKeyHelper::login($ip));

        if ($attempts >= $this->config->loginMaxAttempts) {
            throw new ActionPreventedException(
                $this->translator->translate('voyti-lockout.login.too_many_attempts', category: 'voyti-lockout'),
                ['login'],
            );
        }
    }
}
