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
 * Listens for the cancellable {@see BeforeLoginEvent} and, once the request's IP address has any
 * failures recorded by {@see RecordFailedLoginAttemptListener} within the configured window, delays
 * the login - even with correct credentials - by an exponentially growing wait. This is the defense
 * against an attacker who eventually guesses the correct password after enough failures. There is no
 * attempt count that locks the IP out for good, per OWASP/NIST guidance against a lockout control
 * that itself becomes a denial-of-service against the legitimate user.
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
        $retryAfterSeconds = $this->store->getRetryAfterSeconds(
            LockoutKeyHelper::login($ip),
            $this->config->loginBaseDelaySeconds,
            $this->config->loginMaxDelaySeconds,
        );

        if ($retryAfterSeconds > 0) {
            throw new ActionPreventedException(
                $this->translator->translate(
                    'voyti-lockout.login.too_many_attempts',
                    ['retryAfterSeconds' => $retryAfterSeconds],
                    category: 'voyti-lockout',
                ),
                ['login'],
            );
        }
    }
}
